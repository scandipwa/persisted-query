<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Cache\Observer;


use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ScandiPWA\PersistedQuery\Cache\Response as ResponseCache;

class FlushAllPQ implements ObserverInterface
{
    /**
     * @var bool
     */
    private $cacheState;
    
    /**
     * @var ResponseCache
     */
    private $responseCache;
    
    public function __construct(
        StateInterface $cacheState,
        ResponseCache $responseCache
    )
    {
        $this->cacheState = $cacheState->isEnabled(strtolower(ResponseCache::CACHE_TAG));
        $this->responseCache = $responseCache;
    }
    
    public function execute(Observer $observer)
    {
        if ($this->cacheState) {
            $this->responseCache->clean();
        }
    }
    
}
