<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery;


use Magento\Framework\App\DeploymentConfig;

/**
 * Class RedisClient
 *
 * @package ScandiPWA\PersistentQuery
 */
class RedisClient
{

    protected const PERSISTENT_QUERY_CONFIG = 'cache/persisted-query';

    protected const TTL_QUERY_PREFIX = '_ttl';

    /**
     * @var mixed|null
     */
    private $cacheConfig;

    /**
     * @var \Predis\Client
     */
    private $client;

    /**
     * RedisClient constructor.
     *
     * @param DeploymentConfig $config
     * @param string $redisClientClass
     * @throws \Exception
     */
    public function __construct(
        DeploymentConfig $config,
        string $redisClientClass = \Predis\Client::class
    ) {
        $this->cacheConfig = $config->get(self::PERSISTENT_QUERY_CONFIG);
        if (!$this->configExists()) {
            throw new \Exception('Redis is not configured for persistent queries');
        }
        $this->client = new $redisClientClass($this->cacheConfig['redis']);
    }

    /**
     * @param string $hash
     * @param string $query
     * @return mixed
     */
    public function updatePersistentQuery(string $hash, string $query)
    {
        return $this->client->set($hash, $query);
    }

    /**
     * @return bool
     */
    private function configExists()
    {
        return (($this->cacheConfig !== null) && array_key_exists('redis', $this->cacheConfig));
    }

    /**
     * @param string $hash
     * @return mixed
     */
    public function getPersistentQuery(string $hash)
    {
        return $this->client->get($hash);
    }

    /**
     * @param string $hash
     * @return int
     */
    public function queryExists(string $hash)
    {
        return $this->client->exists($hash);
    }

    public function setQueryTTL(string $hash, int $ttl)
    {
        $hash .= self::TTL_QUERY_PREFIX;
        return $this->client->set($hash, $ttl);
    }

    public function getQueryTTL(string $hash)
    {
        $hash .= self::TTL_QUERY_PREFIX;
        return $this->client->get($hash);
    }
}
