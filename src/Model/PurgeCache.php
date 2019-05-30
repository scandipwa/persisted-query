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

use Magento\CacheInvalidate\Model\PurgeCache as CorePurgeCache;

/**
 * Class PurgeCache
 * @package ScandiPWA\PersistedQuery\Model
 */
class PurgeCache extends CorePurgeCache
{
    /**
     * @param string $poolTag
     * @return bool
     */
    public function sendPoolPurgeRequest($poolTag): bool
    {
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
                $socketAdapter->read();
                $socketAdapter->close();
            } catch (\Exception $e) {
                echo "Error!! ";
                return false;
            }
        }
        return true;
    }
    
}
