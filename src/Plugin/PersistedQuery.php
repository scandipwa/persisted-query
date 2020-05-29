<?php
/**
 * ScandiPWA_PersistedQuery
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Plugin;

use Closure;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Utils\AST;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\GraphQl\Exception\ExceptionFormatter;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface;
use Magento\Framework\Interception\InterceptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Response;
use Magento\GraphQl\Model\Query\ContextFactoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\GraphQl\Query\Fields as QueryFields;
use ScandiPWA\PersistedQuery\Cache\Response as ResponseCache;
use ScandiPWA\PersistedQuery\RedisClient;
use Throwable;
use Zend\Http\Exception\InvalidArgumentException;
use Zend\Http\Response as HttpResponse;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Response\Http as MagentoHttpResponse;
use Magento\Framework\App\ObjectManager;

// TODO: refactor file, it looks too complex!

/**
 * Class PersistedQuery
 * @package ScandiPWA\PersistedQuery\Plugin
 */
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
    protected $client;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var bool
     */
    protected $cacheState;

    /**
     * @var QueryFields
     */
    protected $queryFields;

    /**
     * @var SchemaGeneratorInterface
     */
    protected $schemaGenerator;

    /**
     * @var SerializerInterface
     */
    protected $jsonSerializer;

    /**
     * @var QueryProcessor
     */
    protected $queryProcessor;

    /**
     * @var ExceptionFormatter
     */
    protected $graphQlError;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var MagentoHttpResponse
     */
    protected $httpResponse;

    /**
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * PersistedQuery constructor.
     * @param Response $response
     * @param RedisClient $redisClient
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param StateInterface $cacheState
     * @param QueryFields $queryFields
     * @param SchemaGeneratorInterface $schemaGenerator
     * @param SerializerInterface $jsonSerializer
     * @param QueryProcessor $queryProcessor
     * @param ExceptionFormatter $graphQlError
     * @param JsonFactory|null $jsonFactory
     * @param MagentoHttpResponse|null $httpResponse
     * @param ContextFactoryInterface|null $contextFactory
     */
    public function __construct(
        Response $response,
        RedisClient $redisClient,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        StateInterface $cacheState,
        QueryFields $queryFields,
        SchemaGeneratorInterface $schemaGenerator,
        SerializerInterface $jsonSerializer,
        QueryProcessor $queryProcessor,
        ExceptionFormatter $graphQlError,
        JsonFactory $jsonFactory = null,
        MagentoHttpResponse $httpResponse = null,
        ContextFactoryInterface $contextFactory = null
    )
    {
        $this->serializer = $serializer;
        $this->response = $response;
        $this->client = $redisClient;
        $this->logger = $logger;
        $this->queryFields = $queryFields;
        $this->schemaGenerator = $schemaGenerator;
        $this->jsonSerializer = $jsonSerializer;
        $this->queryProcessor = $queryProcessor;
        $this->graphQlError = $graphQlError;
        $this->cacheState = $cacheState->isEnabled(strtolower(ResponseCache::CACHE_TAG));
        $this->jsonFactory = $jsonFactory ?: ObjectManager::getInstance()->get(JsonFactory::class);
        $this->httpResponse = $httpResponse ?: ObjectManager::getInstance()->get(MagentoHttpResponse::class);
        $this->contextFactory = $contextFactory ?: ObjectManager::getInstance()->get(ContextFactoryInterface::class);
    }

    /**
     * @param InterceptorInterface $interceptor
     * @param Closure $next
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws Throwable
     */
    public function aroundDispatch(
        InterceptorInterface $interceptor,
        Closure $next,
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

        return $this->processRequest($request);
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws Throwable
     */
    public function processRequest(
        RequestInterface $request
    ): ResponseInterface
    {
        $queryHash = $request->getParam('hash');
        $queryExists = $this->client->queryExists($queryHash);

        if (!$queryExists) {
            if ($request->getMethod() === 'GET') {
                return $this->response
                    ->setHeader('Content-Type', 'application/json')
                    ->setBody(json_encode([
                        'error' => true,
                        'code' => '410',
                        'message' => 'Query hash is unknown'
                    ]))
                    ->setStatusCode(HttpResponse::STATUS_CODE_410);
            }

            return $this->saveQuery($request);
        }

        $documentString = json_decode($this->client->getPersistentQuery($queryHash), true);
        $documentNode = AST::fromArray($documentString);
        $variables = $this->processVariables($request->getParams());

        $result = $this->processGraphqlRequest(
            $queryHash,
            $documentNode,
            $variables
        );

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @param string $queryHash
     * @param DocumentNode $documentNode
     * @param array $variables
     * @return MagentoHttpResponse
     * @throws Throwable
     */
    protected function processGraphqlRequest(
        string $queryHash,
        DocumentNode $documentNode,
        array $variables
    ): MagentoHttpResponse {
        $statusCode = 200;
        $jsonResult = $this->jsonFactory->create();

        try {
            // We must extract queried field names to avoid instantiation of unnecessary fields in webonyx schema
            // Temporal coupling is required for performance optimization
            $this->queryFields->setQuery($documentNode, $variables);
            $schema = $this->schemaGenerator->generate();

            $result = $this->queryProcessor->process(
                $schema,
                $documentNode,
                $this->contextFactory->create(),
                $variables ?? []
            );
        } catch (\Exception $error) {
            $result['errors'] = $result['errors'] ?? [];
            $result['errors'][] = $this->graphQlError->create($error);
            $statusCode = ExceptionFormatter::HTTP_GRAPH_QL_SCHEMA_ERROR_STATUS;
        }

        $responseHasError = array_key_exists('errors', $result) && count($result['errors']);

        if (!$responseHasError && $statusCode === 200) {
            $queryTTL = $this->cacheState ? $this->client->getQueryTTL($queryHash) : 0;
            $jsonResult->setHeader('X-Pool', ResponseCache::POOL_TAG);
            $jsonResult->setHeader('Cache-control', 'max-age=' . $queryTTL ?? self::QUERY_TTL);
        }

        $jsonResult->setHttpResponseCode($statusCode);
        $jsonResult->setData($result);
        $jsonResult->renderResult($this->httpResponse);

        return $this->httpResponse;
    }

    /**
     * @param string $query
     * @param        $args
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function processVariables($args): array
    {
        unset($args['hash']);

        return array_map(function ($item) {
            // Check for complex JSON structures
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
        }, $args);
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface|HttpResponse
     * @throws InvalidArgumentException
     */
    private function saveQuery(RequestInterface $request)
    {
        $requestQuery = $this->serializer->unserialize($request->getContent());
        if (is_array($requestQuery) && array_key_exists('query', $requestQuery)) {
            $requestQuery = $requestQuery['query'];
        }

        // Prepare document before-hand, otherwise it will drain CPU afterwards
        try {
            $documentNode = Parser::parse(new Source($requestQuery ?: '', 'GraphQL'));
            $json = json_encode($documentNode);
        } catch (SyntaxError $e) {
            $this->logger->error('GraphQL syntax error while saving query to persistence layer', $e);

            return $this->response
                ->setStatusCode(HttpResponse::STATUS_CODE_502)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'error' => true, 'code' => '502', 'message' => 'Can not save the query',
                ]));
        }

        $update = $this->client->updatePersistentQuery($request->getParam('hash'), $json);
        if (!$update) {
            $this->logger->error('Redis failed to save query', debug_backtrace());

            return $this->response
                ->setStatusCode(HttpResponse::STATUS_CODE_502)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'error' => true, 'code' => '502', 'message' => 'Can not save the query',
                ]));
        }

        if (is_integer($request->getHeader('SW-cache-age'))) {
            $this->client->setQueryTTL($request->getParam('hash'), $request->getHeader('SW-cache-age'));
        } else {
            $this->client->setQueryTTL($request->getParam('hash'), 0);
        }

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode(['error' => false, 'code' => '1', 'message' => 'Query registered']))
            ->setStatusCode(HttpResponse::STATUS_CODE_201);
    }
}
