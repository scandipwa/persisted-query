<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use ScandiPWA\PersistedQuery\RedisClient;
use ScandiPWA\PersistedQuery\Model\PurgeCache;

class Response extends TagScope
{
    const TYPE_IDENTIFIER = 'PERSISTED_QUERY_RESPONSE';
    
    const CACHE_TAG = 'PERSISTED_QUERY_RESPONSE';
    
    const POOL_TAG = 'persisted_q_resp';
    
    /**
     * Type constructor.
     * @param FrontendPool $frontendPool
     * @param RedisClient  $redisClient
     */
    public function __construct(
        FrontendPool $frontendPool,
        RedisClient $redisClient,
        PurgeCache $purgeCache
    )
    {
        $this->client = $redisClient;
        $this->purgeCache = $purgeCache;
        parent::__construct($frontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
    
    /**
     * @param string $mode
     * @param array  $tags
     * @return bool|void
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = [])
    {
        $this->purgeCache->sendPurgeRequest(self::POOL_TAG);
    }
    
}