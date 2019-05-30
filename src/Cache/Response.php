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

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;
use ScandiPWA\PersistedQuery\Model\PurgeCache;

class Response extends TagScope
{
    public const TYPE_IDENTIFIER = 'persisted_query_response';
    
    public const CACHE_TAG = 'PERSISTED_QUERY_RESPONSE';
    
    public const POOL_TAG = 'persisted_q_resp';
    
    /**
     * @var PurgeCache
     */
    private $purgeCache;
    
    /**
     * Response constructor.
     * @param FrontendPool $frontendPool
     * @param PurgeCache   $purgeCache
     */
    public function __construct(
        FrontendPool $frontendPool,
        PurgeCache $purgeCache
    )
    {
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
        return $this->purgeCache->sendPoolPurgeRequest(self::POOL_TAG);
    }
    
}
