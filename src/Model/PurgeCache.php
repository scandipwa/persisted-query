<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Model;

use Exception;
use Magento\CacheInvalidate\Model\PurgeCache as CorePurgeCache;
use Magento\CacheInvalidate\Model\SocketFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Cache\InvalidateLogger;
use Magento\PageCache\Model\Cache\Server;
use Magento\PageCache\Model\Config;
use Magento\Store\Model\ScopeInterface;
use Zend\Uri\Uri;

/**
 * Class PurgeCache
 * @package ScandiPWA\PersistedQuery\Model
 */
class PurgeCache extends CorePurgeCache
{
    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /**
     * Constructor
     *
     * @param Server $cacheServer
     * @param SocketFactory $socketAdapterFactory
     * @param InvalidateLogger $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Server $cacheServer,
        SocketFactory $socketAdapterFactory,
        InvalidateLogger $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;

        parent::__construct(
            $cacheServer,
            $socketAdapterFactory,
            $logger
        );
    }

    /**
     * Skip non-varnish instances
     * @return bool
     */
    protected function getIsPurgeNecessary(): bool {
        $cachingApp = $this->scopeConfig->getValue(Config::XML_PAGECACHE_TYPE, ScopeInterface::SCOPE_STORE);
        return $cachingApp == Config::VARNISH;
    }

    /**
     * @param $poolTag string
     * @return bool
     * @throws PurgeCacheException
     */
    public function sendPoolPurgeRequest(string $poolTag): bool
    {
        if (!$this->getIsPurgeNecessary()) {
            return true;
        }

        $socketAdapter = $this->socketAdapterFactory->create();
        $servers = $this->cacheServer->getUris();
        $socketAdapter->setOptions(['timeout' => 10]);
        $headers = ['X-Pool' => $poolTag];

        foreach ($servers as $server) {
            $headers['Host'] = $server->getHost();

            try {
                $socketAdapter->connect($server->getHost(), $server->getPort());
                $socketAdapter->write(
                    'PURGE',
                    $server,
                    '1.1',
                    $headers
                );
                $response = $socketAdapter->read();
                $socketAdapter->close();
            } catch (Exception $e) {
                throw new PurgeCacheException(sprintf('Error reaching Varnish: %s', $e->getMessage()));
            }

            $this->validateResponse($server, $response);
        }

        return true;
    }

    /**
     * @param Uri    $server
     * @param string $response
     * @return bool|null
     * @throws PurgeCacheException
     */
    private function validateResponse(Uri $server, string $response): ?bool
    {
        $regexParse = [];
        preg_match('/^HTTP\/\d+.\d+\s(\d+)([\s\S]+)Date:/', $response, $regexParse);
        if (count($regexParse) >= 2) {
            $responseCode = $regexParse[1];
            if ($responseCode === '200') {
                return true;
            }

            $message = sprintf(
                'Error flushing Varnish server. Host: "%s". PURGE response code: %s',
                $server->getHost(),
                $responseCode
            );

            if (isset($regexParse[2])) {
                $responseMessage = $regexParse[2];
                $message .= sprintf(' message: %s', trim($responseMessage));
            }
            throw new PurgeCacheException($message);
        }
    }
}
