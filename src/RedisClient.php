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


use Credis_Client;
use Exception;
use Magento\Framework\App\DeploymentConfig;
use Psr\Log\LoggerInterface;

/**
 * Class RedisClient
 *
 * @package ScandiPWA\PersistentQuery
 */
class RedisClient
{
    protected const PERSISTENT_QUERY_CONFIG = 'cache/persisted-query';

    protected const TTL_QUERY_PREFIX = '_ttl';

    /** @var mixed|null */
    private $cacheConfig;

    /** @var Credis_Client */
    private $client;

    /** @var string */
    private $redisClientClass;

    /** @var LoggerInterface */
    private $logger;

    /**
     * RedisClient constructor.
     *
     * @param DeploymentConfig $config
     * @param LoggerInterface $logger
     * @param string $redisClientClass
     */
    public function __construct(
        DeploymentConfig $config,
        LoggerInterface $logger,
        string $redisClientClass = Credis_Client::class
    ) {
        $this->cacheConfig = $config->get(self::PERSISTENT_QUERY_CONFIG);
        $this->redisClientClass = $redisClientClass;
        $this->logger = $logger;

        /**
         * Do not initialize client, if configuration is missing
         */
        if ($this->configExists()) {
            $this->client = $this->redisClientFactory($this->cacheConfig['redis']);
        } else {
            $this->logConfigurationWarning();
            $this->client = null;
        }
    }

    /**
     * Simply check for configuration existence
     * @throws Exception
     */
    private function validateConfiguration() {
        if (!$this->configExists()) {
            throw new Exception('Redis is not configured for persistent queries');
        }
    }

    /**
     * @param array $redisConfig
     * @return Credis_Client
     */
    private function redisClientFactory(array $redisConfig): Credis_Client
    {
        return new $this->redisClientClass(
            $redisConfig['host'],
            $redisConfig['port'],
            10,
            '',
            $redisConfig['database']
        );
    }

    /**
     * @param string $hash
     * @param string $query
     * @return bool
     * @throws Exception
     */
    public function updatePersistentQuery(string $hash, string $query): bool
    {
        $this->validateConfiguration();
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
     * @throws Exception
     */
    public function getPersistentQuery(string $hash)
    {
        $this->validateConfiguration();
        return $this->client->get($hash);
    }

    /**
     * @param string $hash
     * @return int
     * @throws Exception
     */
    public function queryExists(string $hash)
    {
        $this->validateConfiguration();
        return $this->client->exists($hash);
    }

    /**
     * @param string $hash
     * @param int $ttl
     * @return bool
     * @throws Exception
     */
    public function setQueryTTL(string $hash, int $ttl): bool
    {
        $this->validateConfiguration();
        $hash .= self::TTL_QUERY_PREFIX;
        return $this->client->set($hash, $ttl);
    }

    /**
     * @param string $hash
     * @return string
     * @throws Exception
     */
    public function getQueryTTL(string $hash)
    {
        $this->validateConfiguration();
        $hash .= self::TTL_QUERY_PREFIX;
        return $this->client->get($hash);
    }

    private function logConfigurationWarning() {
        $this->logger->warning("Cache flush attempt, while Redis is not configured for persistent queries. Flush request ignored. Update env.php with \"cache/persisted-query\" configuration.");
    }

    public function flushDb()
    {
        if ($this->configExists()) {
            return $this->client->flushdb();
        } else {
            $this->logConfigurationWarning();
            return true;
        }
    }
}
