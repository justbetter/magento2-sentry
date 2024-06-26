<?php

namespace JustBetter\Sentry\Helper;

use ErrorException;
use InvalidArgumentException;
use JustBetter\Sentry\Block\SentryScript;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\TableNotFoundException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class Data extends AbstractHelper
{
    const XML_PATH_SRS = 'sentry/general/';
    const XML_PATH_SRS_ISSUE_GROUPING = 'sentry/issue_grouping/';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $configKeys = [
        'dsn',
        'logrocket_key',
        'log_level',
        'errorexception_reporting',
        'ignore_exceptions',
        'mage_mode_development',
        'environment',
        'js_sdk_version',
        'tracing_enabled',
        'tracing_sample_rate',
        'ignore_js_errors',
    ];

    /**
     * Data constructor.
     *
     * @param Context                  $context
     * @param StoreManagerInterface    $storeManager
     * @param State                    $appState
     * @param Json                     $serializer
     * @param ProductMetadataInterface $productMetadataInterface
     * @param DeploymentConfig         $deploymentConfig
     */
    public function __construct(
        Context $context,
        protected StoreManagerInterface $storeManager,
        protected State $appState,
        private Json $serializer,
        protected ProductMetadataInterface $productMetadataInterface,
        protected DeploymentConfig $deploymentConfig
    ) {
        $this->scopeConfig = $context->getScopeConfig();
        $this->collectModuleConfig();

        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function getDSN()
    {
        return $this->collectModuleConfig()['dsn'];
    }

    public function isTracingEnabled(): bool
    {
        return $this->collectModuleConfig()['tracing_enabled'] ?? false;
    }

    public function getTracingSampleRate(): float
    {
        return (float) $this->collectModuleConfig()['tracing_sample_rate'] ?? 0.2;
    }

    /**
     * @return array|null
     */
    public function getIgnoreJsErrors()
    {
        $list = $this->collectModuleConfig()['ignore_js_errors'];

        if ($list === null) {
            return null;
        }

        try {
            $config = $this->collectModuleConfig();
            $list = is_array($config['ignore_js_errors'])
                ? $config['ignore_js_errors']
                : $this->serializer->unserialize($config['ignore_js_errors']);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(
                'Sentry configuration error: `ignore_js_errors` has to be an array or `null`. Given type: '.gettype($list)
            );
        }

        return $list;
    }

    /**
     * @return string the version of the js sdk of Sentry
     */
    public function getJsSdkVersion(): string
    {
        return $this->collectModuleConfig()['js_sdk_version'] ?: SentryScript::CURRENT_VERSION;
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->collectModuleConfig()['environment'] ?? 'default';
    }

    /**
     * @param string          $field
     * @param int|string|null $storeId
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
     * @param string $code
     * @param null   $storeId
     *
     * @return mixed
     */
    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(static::XML_PATH_SRS.$code, $storeId);
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->getStore()?->getId() ?? 0;
    }

    /**
     * @return array
     */
    public function collectModuleConfig(): array
    {
        if (isset($this->config[$this->getStoreId()]['enabled'])) {
            return $this->config[$this->getStoreId()];
        }

        try {
            $this->config[$this->getStoreId()]['enabled'] = $this->scopeConfig->getValue('sentry/environment/enabled', ScopeInterface::SCOPE_STORE)
                ?? $this->deploymentConfig->get('sentry') !== null;
        } catch (TableNotFoundException|FileSystemException|RuntimeException $e) {
            $this->config[$this->getStoreId()]['enabled'] = null;
        }

        foreach ($this->configKeys as $value) {
            try {
                $this->config[$this->getStoreId()][$value] = $this->scopeConfig->getValue('sentry/environment/'.$value, ScopeInterface::SCOPE_STORE)
                    ?? $this->deploymentConfig->get('sentry/'.$value);
            } catch (TableNotFoundException|FileSystemException|RuntimeException $e) {
                $this->config[$this->getStoreId()][$value] = null;
            }
        }

        return $this->config[$this->getStoreId()];
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActiveWithReason()['active'];
    }

    /**
     * @return array
     */
    public function isActiveWithReason(): array
    {
        $reasons = [];
        $config = $this->collectModuleConfig();
        $emptyConfig = empty($config);
        $configEnabled = isset($config['enabled']) && $config['enabled'];
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
    public function isProductionMode(): bool
    {
        return $this->appState->emulateAreaCode(Area::AREA_GLOBAL, [$this, 'getAppState']) === 'production';
    }

    /**
     * @return string
     */
    public function getAppState(): string
    {
        return $this->appState->getMode();
    }

    /**
     * @return bool
     */
    public function isOverwriteProductionMode(): bool
    {
        $config = $this->collectModuleConfig();

        return isset($config['mage_mode_development']) && $config['mage_mode_development'];
    }

    /**
     *  Get the current magento version.
     *
     * @return string
     */
    public function getMagentoVersion(): string
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
    public function isPhpTrackingEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_php_tracking', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return bool
     */
    public function useScriptTag(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_script_tag', ScopeInterface::SCOPE_STORE);
    }

    public function useSessionReplay(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_session_replay', ScopeInterface::SCOPE_STORE);
    }

    public function getReplaySessionSampleRate(): float
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_session_sample_rate') ?? 0.1;
    }

    public function getReplayErrorSampleRate(): float
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_error_sample_rate') ?? 1;
    }

    public function getReplayBlockMedia(): bool
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_block_media') ?? true;
    }

    public function getReplayMaskText(): bool
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_mask_text') ?? true;
    }

    /**
     * @param string $blockName
     *
     * @return bool
     */
    public function showScriptTagInThisBlock($blockName): bool
    {
        $config = $this->getGeneralConfig('script_tag_placement');

        if (!$config) {
            return false;
        }

        $name = 'sentry.'.$config;

        return $name === $blockName;
    }

    /**
     * @return mixed
     */
    public function getLogrocketKey()
    {
        return $this->collectModuleConfig()['logrocket_key'];
    }

    /**
     * @return bool
     */
    public function useLogrocket(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'use_logrocket') &&
            isset($this->collectModuleConfig()['logrocket_key']) &&
            $this->getLogrocketKey() !== null;
    }

    /**
     * @return bool
     */
    public function useLogrocketIdentify(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::XML_PATH_SRS.'logrocket_identify',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function stripStaticContentVersion(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::XML_PATH_SRS_ISSUE_GROUPING.'strip_static_content_version',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function stripStoreCode(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::XML_PATH_SRS_ISSUE_GROUPING.'strip_store_code',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return int
     */
    public function getErrorExceptionReporting(): int
    {
        return (int) ($this->collectModuleConfig()['errorexception_reporting'] ?? E_ALL);
    }

    /**
     * @return array
     */
    public function getIgnoreExceptions(): array
    {
        $config = $this->collectModuleConfig();
        if (is_array($config['ignore_exceptions'])) {
            return $config['ignore_exceptions'];
        }

        try {
            return $this->serializer->unserialize($config['ignore_exceptions']);
        } catch (InvalidArgumentException $e) {
            return [];
        }
    }

    /**
     * @param Throwable $ex
     *
     * @return bool
     */
    public function shouldCaptureException(Throwable $ex): bool
    {
        if ($ex instanceof ErrorException && !($ex->getSeverity() & $this->getErrorExceptionReporting())) {
            return false;
        }

        if (in_array(get_class($ex), $this->getIgnoreExceptions())) {
            return false;
        }

        return true;
    }
}
