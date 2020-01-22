<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Setup;

use Magento\Framework\Config\Data\ConfigData;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Setup\ConfigOptionsListInterface;
use Magento\Framework\Setup\Option\TextConfigOption;
use Magento\Framework\App\DeploymentConfig;

/**
 * Deployment configuration options needed for Backend module
 *
 */
class ConfigOptionsList implements ConfigOptionsListInterface
{
    /**
     * Input key for the persisted query host
     */
    const INPUT_KEY_PQ_HOST = 'pq-host';
    
    /**
     * Input key for the persisted query host
     */
    const INPUT_KEY_PQ_SCHEME = 'pq-scheme';
    
    /**
     * Input key for the persisted query port
     */
    const INPUT_KEY_PQ_PORT = 'pq-port';
    
    /**
     * Input key for the persisted query database
     */
    const INPUT_KEY_PQ_DATABASE = 'pq-database';
    
    /**
     * Input key for the persisted query password
     */
    const INPUT_KEY_PQ_PASSWORD = 'pq-password';
    
    /**
     * Default value for pq-scheme
     */
    const INPUT_DEFAULT_PQ_SCHEME = 'tcp';
    
    /**
     * Config path
     */
    const CONFIG_PATH_PERSISTED_QUERY = 'cache/persisted-query/redis';
    
    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return [
            new TextConfigOption(
                self::INPUT_KEY_PQ_HOST,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                $this->prefixerHelper('host'),
                'Persisted query redis host'
            ),
            new TextConfigOption(
                self::INPUT_KEY_PQ_SCHEME,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                $this->prefixerHelper('scheme'),
                'Persisted query redis scheme (default: TCP)'
            ),
            new TextConfigOption(
                self::INPUT_KEY_PQ_PORT,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                $this->prefixerHelper('port'),
                'Persisted query redis port'
            ),
            new TextConfigOption(
                self::INPUT_KEY_PQ_DATABASE,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                $this->prefixerHelper('database'),
                'Persisted query redis database'
            ),
            new TextConfigOption(
                self::INPUT_KEY_PQ_PASSWORD,
                TextConfigOption::FRONTEND_WIZARD_PASSWORD,
                $this->prefixerHelper('password'),
                'Persisted query redis password'
            )
        ];
    }
    
    protected function prefixerHelper(string $path)
    {
        return self::CONFIG_PATH_PERSISTED_QUERY . '/' . $path;
    }
    
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createConfig(array $options, DeploymentConfig $deploymentConfig)
    {
        $configData = new ConfigData(ConfigFilePool::APP_ENV);
        
        if (isset($options[self::INPUT_KEY_PQ_HOST])) {
            $configData->set($this->prefixerHelper('host'), $options[self::INPUT_KEY_PQ_HOST]);
        }
        
        if (isset($options[self::INPUT_KEY_PQ_PORT])) {
            $configData->set($this->prefixerHelper('port'), $options[self::INPUT_KEY_PQ_PORT]);
        }
        
        if (isset($options[self::INPUT_KEY_PQ_DATABASE])) {
            $configData->set($this->prefixerHelper('database'), $options[self::INPUT_KEY_PQ_DATABASE]);
        }
        
        if (isset($options[self::INPUT_KEY_PQ_SCHEME])) {
            $configData->set($this->prefixerHelper('scheme'), $options[self::INPUT_KEY_PQ_SCHEME]);
        }
        
        if (isset($options[self::INPUT_KEY_PQ_PASSWORD])) {
            $configData->set($this->prefixerHelper('password'), $options[self::INPUT_KEY_PQ_PASSWORD]);
        }
        
        return [$configData];
    }
    
    /**
     * {@inheritdoc}
     */
    public function validate(array $options, DeploymentConfig $deploymentConfig)
    {
        $errors = [];
        if (isset($options[self::INPUT_KEY_PQ_HOST]) &&
            !preg_match('/^[a-zA-Z0-9\-_\.]+$/', $options[self::INPUT_KEY_PQ_HOST])) {
            $errors[] = "Invalid persisted query redis host";
        }
        if (isset($options[self::INPUT_KEY_PQ_PORT]) &&
            !preg_match('/^[0-9]+$/', $options[self::INPUT_KEY_PQ_PORT])) {
            $errors[] = "Invlid persisted query redis port";
        }
        if (isset($options[self::INPUT_KEY_PQ_DATABASE]) &&
            !preg_match('/^[0-9]+$/', $options[self::INPUT_KEY_PQ_DATABASE])) {
            $errors[] = "Invlid persisted query redis database";
        }
        if (isset($options[self::INPUT_KEY_PQ_SCHEME]) &&
            !in_array($options[self::INPUT_KEY_PQ_SCHEME], ['tcp', 'udp'])) {
            $errors[] = "Invalid persisted query redis scheme";
        }
        if (isset($options[self::INPUT_KEY_PQ_PASSWORD]) &&
            !preg_match('/^\S+$/', $options[self::INPUT_KEY_PQ_PASSWORD])) {
            $errors[] = "Persisted query redis password can not be empty";
        }
        
        return $errors;
    }
}
