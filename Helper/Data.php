<?php

namespace JustBetter\Sentry\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ProductMetadataInterface;

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
        'domain',
        'enabled',
        'log_level',
        'mage_mode_development',
        'environment',
        'enable_script_tag',
        'script_tag_placement',
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
    public function __construct(Context $context, StoreManagerInterface $storeManager, State $appState, ProductMetadataInterface $productMetadataInterface)
    {
        $this->storeManager = $storeManager;
        $this->appState = $appState;
        $this->scopeConfig = $context->getScopeConfig();
        $this->productMetadataInterface = $productMetadataInterface;
        $this->collectModuleConfig();

        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function getDSN()
    {
        return $this->getGeneralConfig('domain');
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->getGeneralConfig('environment');
    }

    /**
     * @param      $field
     * @param null $storeId
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
     * @return mixed
     */
    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::XML_PATH_SRS . $code, $storeId);
    }

    /**
     * @return array
     */
    public function collectModuleConfig()
    {
        foreach ($this->configKeys as $key => $value) {
            $this->config[ $value ] = $this->getGeneralConfig($value);
        }

        return $this->config;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return !empty($this->config)
            && array_key_exists('enabled', $this->config)
            && $this->config['enabled']
            && $this->getDSN()
            && ($this->isProductionMode() || $this->isOverwriteProductionMode());
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
     *  Get the current magento version
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadataInterface->getVersion();
    }

    /**
     * Get the current store
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
        return isset($this->config['enable_script_tag']) && $this->config['enable_script_tag'];
    }

    /**
     * @param $blockName
     * @return bool
     */
    public function showScriptTagInThisBlock($blockName)
    {
        if (!isset($this->config['script_tag_placement'])) {
            return false;
        }

        $name = 'sentry.' . $this->config['script_tag_placement'];

        return $name == $blockName;
    }
}
