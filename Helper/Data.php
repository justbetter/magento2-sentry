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
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;
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
        'disable_default_integrations',
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
        return $this->config['dsn'];
    }

    public function isTracingEnabled(): bool
    {
        return $this->config['tracing_enabled'] ?? false;
    }

    public function getTracingSampleRate(): float
    {
        return (float) $this->config['tracing_sample_rate'] ?? 0.2;
    }

    public function getDisabledDefaultIntegrations(): array
    {
        return $this->config['disable_default_integrations'] ?? [];
    }

    /**
     * @return array|null
     */
    public function getIgnoreJsErrors()
    {
        $list = $this->config['ignore_js_errors'];

        if ($list === null) {
            return null;
        }

        try {
            $list = is_array($this->config['ignore_js_errors'])
                ? $this->config['ignore_js_errors']
                : $this->serializer->unserialize($this->config['ignore_js_errors']);
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
        return $this->config['js_sdk_version'] ?: SentryScript::CURRENT_VERSION;
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->config['environment'];
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
     * @return array
     */
    public function collectModuleConfig(): array
    {
        if (isset($this->config['enabled'])) {
            return $this->config;
        }

        $this->config['enabled'] = $this->deploymentConfig->get('sentry') !== null;

        foreach ($this->configKeys as $key) {
            $this->config[$key] = $this->deploymentConfig->get('sentry/' . $key);
        }

        if ($this->scopeConfig->isSetFlag('sentry/environment/override')) {
            $allowedConfigKeys = array_merge(['enabled'], $this->configKeys);
            foreach ($this->scopeConfig->getValue('sentry/environment') as $key => $value) {
                if ($value !== null && in_array($key, $allowedConfigKeys, true)) {
                    $this->config[$key] = $value;
                }
            }
        }

        return $this->config;
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
        return array_key_exists('mage_mode_development', $this->config) && $this->config['mage_mode_development'];
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
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_php_tracking');
    }

    /**
     * @return bool
     */
    public function useScriptTag(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_script_tag');
    }

    public function useSessionReplay(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_session_replay');
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
        return $this->config['logrocket_key'];
    }

    /**
     * @return bool
     */
    public function useLogrocket(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'use_logrocket') &&
            array_key_exists('logrocket_key', $this->config) &&
            $this->getLogrocketKey() !== null;
    }

    /**
     * @return bool
     */
    public function useLogrocketIdentify(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::XML_PATH_SRS.'logrocket_identify'
        );
    }

    /**
     * @return bool
     */
    public function stripStaticContentVersion(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::XML_PATH_SRS_ISSUE_GROUPING.'strip_static_content_version'
        );
    }

    /**
     * @return bool
     */
    public function stripStoreCode(): bool
    {
        return $this->scopeConfig->isSetFlag(
            static::XML_PATH_SRS_ISSUE_GROUPING.'strip_store_code'
        );
    }

    /**
     * @return int
     */
    public function getErrorExceptionReporting(): int
    {
        return (int) ($this->config['errorexception_reporting'] ?? E_ALL);
    }

    /**
     * @return array
     */
    public function getIgnoreExceptions(): array
    {
        if (is_array($this->config['ignore_exceptions'])) {
            return $this->config['ignore_exceptions'];
        }

        try {
            return $this->serializer->unserialize($this->config['ignore_exceptions']);
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
