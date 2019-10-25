<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Cache;


use ScandiPWA\PersistedQuery\RedisClient;

/**
 * Class Query
 * @package ScandiPWA\PersistedQuery\Model\Cache
 */
class Query
{
    public const TYPE_IDENTIFIER = 'PERSISTED_QUERY';
    
    public const CACHE_TAG = 'PERSISTED_QUERY';
    
    /**
     * @var Response
     */
    private $responseCache;
    
    /**
     * @var RedisClient
     */
    private $client;
    
    /**
     * Query constructor.
     * @param RedisClient $redisClient
     * @param Response    $responseCache
     */
    public function __construct(
        RedisClient $redisClient,
        Response $responseCache
    )
    {
        $this->client = $redisClient;
        $this->responseCache = $responseCache;
    }
    
    /**
     * @param string $mode
     * @param array  $tags
     * @return bool
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = [])
    {
        $varnishPurge = $this->responseCache->clean();
        $redisFlush = $this->client->flushDb();
        
        return $varnishPurge && $redisFlush;
    }
    
}
