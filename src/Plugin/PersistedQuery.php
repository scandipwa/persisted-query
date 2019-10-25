<?php
/**
 * ScandiPWA_PersistedQuery
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Interception\InterceptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Response;
use Psr\Log\LoggerInterface;
use ScandiPWA\PersistedQuery\Cache\Response as ResponseCache;
use ScandiPWA\PersistedQuery\RedisClient;
use Zend\Http\Exception\InvalidArgumentException;
use Zend\Http\Response as HttpResponse;
use Magento\Framework\App\Cache\StateInterface;

class PersistedQuery
{
    /**
     * How long to cache queries in Varnish
     */
    protected const QUERY_TTL = 60;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var RedisClient
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var bool
     */
    private $cacheState;

    /**
     * PersistedQuery constructor.
     * @param Response            $response
     * @param RedisClient         $redisClient
     * @param SerializerInterface $serializer
     * @param LoggerInterface     $logger
     * @param StateInterface      $cacheState
     */
    public function __construct(
        Response $response,
        RedisClient $redisClient,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        StateInterface $cacheState
    )
    {
        $this->serializer = $serializer;
        $this->response = $response;
        $this->client = $redisClient;
        $this->logger = $logger;
        $this->cacheState = $cacheState->isEnabled(strtolower(ResponseCache::CACHE_TAG));
    }

    /**
     * @param InterceptorInterface $interceptor
     * @param \Closure             $next
     * @param RequestInterface     $request
     * @return ResponseInterface
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function aroundDispatch(
        InterceptorInterface $interceptor,
        \Closure $next,
        RequestInterface $request
    ): ResponseInterface
    {

        // Skip unsupported methods, e.g. OPTIONS that could be used in some setups
        if (!in_array($request->getMethod(), ['GET', 'PUT'])) {
            return $next($request);
        }

        if (!array_key_exists('hash', $request->getParams())) {
            return $interceptor->___callParent('dispatch', [$request]);
        }

        return $this->processRequest($interceptor, $request);
    }

    /**
     * @param InterceptorInterface $interceptor
     * @param RequestInterface     $request
     * @return ResponseInterface|HttpResponse
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function processRequest(InterceptorInterface $interceptor, RequestInterface $request)
    {
        $queryHash = $request->getParam('hash');
        $queryExists = $this->client->queryExists($queryHash);

        if (!$queryExists) {
            if ($request->getMethod() === 'GET') {
                return $this->response
                    ->setHeader('Content-Type', 'application/json')
                    ->setBody(json_encode(['error' => true, 'code' => '410', 'message' => 'Query hash is unknown']))
                    ->setStatusCode(HttpResponse::STATUS_CODE_410);
            }

            return $this->saveQuery($request);
        }

        $graphQlQuery = $this->resolveCachedQuery($this->client->getPersistentQuery($queryHash), $request->getParams());
        $request->setMethod('post');
        $request->setContent($graphQlQuery);

        /**
         * @var Response $result
         */
        $result = $interceptor->___callParent('dispatch', [$request]);
        $json = $this->serializer->unserialize($result->getContent());
        $responseHasError = array_key_exists('errors', $json) && count($json['errors']);
        if (!$responseHasError && $result->getStatusCode() === 200) {
            $queryTTL = $this->cacheState ? $this->client->getQueryTTL($queryHash) : 0;
            $result->setHeader('X-Pool', ResponseCache::POOL_TAG);
            $result->setHeader('Cache-control', 'max-age=' . $queryTTL ?? self::QUERY_TTL);
        }

        return $result;
    }

    /**
     * @param string $query
     * @param        $args
     * @return string
     * @throws \InvalidArgumentException
     */
    private function resolveCachedQuery(string $query, $args): string
    {
        unset($args['hash']);
        $export = [
            'query'     => $query,
            // Preserve typing
            'variables' => array_map(function ($item) {
                // Check for complex JSON structs
                if (preg_match('/^.*:?[{|}].*$/', $item)) {
                    $rawKeys = str_replace(['{', '}', '[', ']'], '', $item);
                    $unifiedString = str_replace(":", ',', $rawKeys);
                    $valueList = explode(',', $unifiedString);
                    foreach ($valueList as $value) {
                        if (strpos($value, '"') !== false || !$value) {
                            continue;
                        }
                        $item = preg_replace("|(?<![\"\w])" . $value . "(?![\"\w])|", "\"$value\"", $item);
                    }

                    return $this->serializer->unserialize($item);
                }

                // Check for encoded array
                if (preg_match('/,/', $item)) {
                    $item = explode(',', $item);

                    return $item;
                }

                // String to bool if bool
                if ($item === 'true' || $item === 'false') {
                    return filter_var($item, FILTER_VALIDATE_BOOLEAN);
                }

                // String to int if number
                if (is_int($item)) {
                    return (int)$item;
                }
                
                // String to float if number with decimals
                if (is_float($item)) {
                    return (float)$item;
                }
                
                return $item;
            }, $args),
        ];

        return $this->serializer->serialize($export);
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface|HttpResponse
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     */
    private function saveQuery(RequestInterface $request)
    {
        $requestQuery = $this->serializer->unserialize($request->getContent());
        if (is_array($requestQuery) && array_key_exists('query', $requestQuery)) {
            $requestQuery = $requestQuery['query'];
        }

        $update = $this->client->updatePersistentQuery($request->getParam('hash'), $requestQuery);
        if (!$update) {
            $this->logger->error('Redis failed to save query', debug_backtrace());

            return $this->response
                ->setStatusCode(HttpResponse::STATUS_CODE_502)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'error' => true, 'code' => '502', 'message' => 'Can not save the query',
                ]));
        }

        $this->client->setQueryTTL($request->getParam('hash'), $request->getHeader('SW-cache-age'));

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode(['error' => false, 'code' => '1', 'message' => 'Query registered']))
            ->setStatusCode(HttpResponse::STATUS_CODE_201);
    }
}
