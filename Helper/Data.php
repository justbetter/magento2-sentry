<?php

namespace JustBetter\Sentry\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    const XML_PATH_SRS = 'sentry/general/';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var array
     */
    protected $configKeys = [
        'dsn',
        'logrocket_key',
        'log_level',
        'mage_mode_development',
        'environment',
    ];

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var State
     */
    protected $appState;

    /**
     * Data constructor.
     *
     * @param Context               $context
     * @param StoreManagerInterface $storeManager
     * @param State                 $appState
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        State $appState,
        ProductMetadataInterface $productMetadataInterface,
        DeploymentConfig $deploymentConfig
    ) {
        $this->storeManager = $storeManager;
        $this->appState = $appState;
        $this->scopeConfig = $context->getScopeConfig();
        $this->productMetadataInterface = $productMetadataInterface;
        $this->deploymentConfig = $deploymentConfig;
        $this->collectModuleConfig();

        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function getDSN()
    {
        return $this->config['dsn'];
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->config['environment'];
    }

    /**
     * @param      $field
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param      $code
     * @param null $storeId
     *
     * @return mixed
     */
    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::XML_PATH_SRS.$code, $storeId);
    }

    /**
     * @return array
     */
    public function collectModuleConfig()
    {
        $this->config['enabled'] = $this->deploymentConfig->get('sentry') !== null;

        foreach ($this->configKeys as $value) {
            $this->config[$value] = $this->deploymentConfig->get('sentry/'.$value);
        }

        return $this->config;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->isActiveWithReason()['active'];
    }

    /**
     * @param string $reason : Reason to tell the user why it's not active (Github issue #53)
     *
     * @return bool
     */
    public function isActiveWithReason()
    {
        $reasons = [];
        $emptyConfig = empty($this->config);
        $configEnabled = array_key_exists('enabled', $this->config) && $this->config['enabled'];
        $dsnNotEmpty = $this->getDSN();
        $productionMode = ($this->isProductionMode() || $this->isOverwriteProductionMode());

        if ($emptyConfig) {
            $reasons[] = __('Config is empty.');
        }
        if (!$configEnabled) {
            $reasons[] = __('Module is not enabled in config.');
        }
        if (!$dsnNotEmpty) {
            $reasons[] = __('DSN is empty.');
        }
        if (!$productionMode) {
            $reasons[] = __('Not in production and development mode is false.');
        }

        return count($reasons) > 0 ? ['active' => false, 'reasons' => $reasons] : ['active' => true];
    }

    /**
     * @return bool
     */
    public function isProductionMode()
    {
        return $this->appState->emulateAreaCode(Area::AREA_GLOBAL, [$this, 'getAppState']) == 'production';
    }

    /**
     * @return string
     */
    public function getAppState()
    {
        return $this->appState->getMode();
    }

    /**
     * @return mixed
     */
    public function isOverwriteProductionMode()
    {
        return array_key_exists('mage_mode_development', $this->config) && $this->config['mage_mode_development'];
    }

    /**
     *  Get the current magento version.
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadataInterface->getVersion();
    }

    /**
     * Get the current store.
     */
    public function getStore()
    {
        return $this->storeManager ? $this->storeManager->getStore() : null;
    }

    /**
     * @return bool
     */
    public function useScriptTag()
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_script_tag');
    }

    /**
     * @param $blockName
     *
     * @return bool
     */
    public function showScriptTagInThisBlock($blockName)
    {
        $config = $this->getGeneralConfig('script_tag_placement');
        if (!$config) {
            return false;
        }

        $name = 'sentry.'.$config;

        return $name == $blockName;
    }

    /**
     * @return mixed
     */
    public function getLogrocketKey()
    {
        return $this->config['logrocket_key'];
    }

    /**
     * @return bool
     */
    public function useLogrocket()
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'use_logrocket') &&
            array_key_exists('logrocket_key', $this->config) &&
            $this->config['logrocket_key'] != null;
    }

    /**
     * @return bool
     */
    public function useLogrocketIdentify()
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'logrocket_identify');
    }
}
