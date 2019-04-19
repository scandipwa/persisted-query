<?php


namespace ScandiPWA\PersistedQuery\Model\Cache;


use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use ScandiPWA\PersistedQuery\RedisClient;

class Type extends TagScope
{
    const TYPE_IDENTIFIER = 'PERSISTED_QUERY';
    
    const CACHE_TAG = 'PERSISTED_QUERY';
    
    public function __construct(
        FrontendPool $frontendPool,
        RedisClient $redisClient
    )
    {
        $this->client = $redisClient;
        parent::__construct($frontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
    
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = [])
    {
        $this->client->flushDb();
    }
    
}