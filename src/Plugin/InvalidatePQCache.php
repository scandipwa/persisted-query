<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_Cache
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Plugin;

use Magento\Catalog\Model\Indexer\Category\Product\AbstractAction;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use ScandiPWA\PersistedQuery\Cache\Response as ResponseCache;

class InvalidatePQCache
{
    /**
     * @var bool
     */
    private $cacheState;
    
    /**
     * @var TypeListInterface
     */
    protected $typeList;
    
    /**
     * @param StateInterface    $cacheState
     * @param TypeListInterface $typeList
     */
    public function __construct(
        TypeListInterface $typeList,
        StateInterface $cacheState
    )
    {
        $this->cacheState = $cacheState->isEnabled(strtolower(ResponseCache::CACHE_TAG));
        $this->typeList = $typeList;
    }
    
    /**
     * @param AbstractAction $subject
     * @param AbstractAction $result
     * @return AbstractAction
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(AbstractAction $subject, AbstractAction $result)
    {
        if ($this->cacheState) {
            $this->typeList->invalidate(ResponseCache::TYPE_IDENTIFIER);
        }
        return $result;
    }
}
