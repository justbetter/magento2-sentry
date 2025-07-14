<?php

namespace JustBetter\Sentry\Helper;

use DomainException;
use ErrorException;
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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;
use Zend_Db_Adapter_Exception;

class Data extends AbstractHelper
{
    public const XML_PATH_SRS = 'sentry/general/';
    public const XML_PATH_SRS_ISSUE_GROUPING = 'sentry/issue_grouping/';

    public const NATIVE_SENTRY_CONFIG_KEYS = [
        // https://docs.sentry.io/platforms/php/configuration/options/#core-options
        'dsn'                   => ['type' => 'string'],
        'environment'           => ['type' => 'string'],
        'max_breadcrumbs'       => ['type' => 'int'],
        'attach_stacktrace'     => ['type' => 'bool'],
        'send_default_pii'      => ['type' => 'bool'],
        'server_name'           => ['type' => 'string'],
        'in_app_include'        => ['type' => 'array'],
        'in_app_exclude'        => ['type' => 'array'],
        'max_request_body_size' => ['type' => 'string'],
        'max_value_length'      => ['type' => 'int'],
        // https://docs.sentry.io/platforms/php/configuration/options/#error-monitoring-options
        'sample_rate'       => ['type' => 'float'],
        'ignore_exceptions' => ['type' => 'array'],
        'error_types'       => ['type' => 'int'],
        'context_lines'     => ['type' => 'int'],
        // https://docs.sentry.io/platforms/php/configuration/options/#tracing-options
        'traces_sample_rate'        => ['type' => 'float'],
        'ignore_transactions'       => ['type' => 'array'],
        'trace_propagation_targets' => ['type' => 'array'],
        // https://docs.sentry.io/platforms/php/profiling/#enabling-profiling
        'profiles_sample_rate'      => ['type' => 'float'],
        // https://docs.sentry.io/platforms/php/configuration/options/#transport-options
        'http_proxy'           => ['type' => 'string'],
        'http_connect_timeout' => ['type' => 'int'],
        'http_timeout'         => ['type' => 'int'],
        // https://spotlightjs.com/
        'spotlight'            => ['type' => 'bool'],
        'spotlight_url'        => ['type' => 'string'],
    ];

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
        ...self::NATIVE_SENTRY_CONFIG_KEYS,
        'logrocket_key'                       => ['type' => 'string'],
        'log_level'                           => ['type' => 'int'],
        'errorexception_reporting'            => ['type' => 'int'], /* @deprecated by @see: error_types https://docs.sentry.io/platforms/php/configuration/options/#error_types */
        'mage_mode_development'               => ['type' => 'bool'],
        'js_sdk_version'                      => ['type' => 'string'],
        'tracing_enabled'                     => ['type' => 'bool'],
        'tracing_sample_rate'                 => ['type' => 'float'], /* @deprecated by @see: traces_sample_rate https://docs.sentry.io/platforms/php/configuration/options/#error_types */
        'performance_tracking_enabled'        => ['type' => 'bool'],
        'performance_tracking_excluded_areas' => ['type' => 'array'],
        'ignore_js_errors'                    => ['type' => 'array'],
        'disable_default_integrations'        => ['type' => 'array'],
        'clean_stacktrace'                    => ['type' => 'bool'],
        'cron_monitoring_enabled'             => ['type' => 'bool'],
        'track_crons'                         => ['type' => 'array'],
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
     * Get the sentry DSN.
     *
     * @return mixed
     */
    public function getDSN()
    {
        return $this->collectModuleConfig()['dsn'];
    }

    /**
     * Whether cron monitoring (check-in events) is enabled.
     */
    public function isCronMonitoringEnabled(): bool
    {
        return $this->collectModuleConfig()['cron_monitoring_enabled'] ?? false;
    }

    /**
     * Get a list of crons to track.
     */
    public function getTrackCrons(): array
    {
        return $this->collectModuleConfig()['track_crons'] ?? [];
    }

    /**
     * Whether tracing is enabled.
     */
    public function isTracingEnabled(): bool
    {
        return $this->collectModuleConfig()['tracing_enabled'] ?? false;
    }

    /**
     * Whether spotlight is enabled.
     *
     * Enabling spotlight will implicitly enable override production mode.
     */
    public function isSpotlightEnabled(): bool
    {
        return ($this->collectModuleConfig()['spotlight'] ?? false) && !$this->isProductionMode();
    }

    /**
     * Get sample rate for tracing.
     */
    public function getTracingSampleRate(): float
    {
        return (float) ($this->collectModuleConfig()['traces_sample_rate'] ?? $this->collectModuleConfig()['tracing_sample_rate'] ?? 0.2);
    }

    /**
     * Get a list of integrations to disable.
     */
    public function getDisabledDefaultIntegrations(): array
    {
        return $this->config['disable_default_integrations'] ?? [];
    }

    /**
     * Get list of js errors to ignore.
     *
     * @return array|null
     */
    public function getIgnoreJsErrors()
    {
        return $this->collectModuleConfig()['ignore_js_errors'];
    }

    /**
     * Get the sdk version string.
     *
     * @return string the version of the js sdk of Sentry
     */
    public function getJsSdkVersion(): string
    {
        return $this->collectModuleConfig()['js_sdk_version'] ?: SentryScript::CURRENT_VERSION;
    }

    /**
     * Get the current environment.
     *
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->collectModuleConfig()['environment'] ?? 'default';
    }

    /**
     * Retrieve config values.
     *
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
     * Retrieve Sentry General config values.
     *
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
     * Get the store id of the current store.
     *
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->getStore()?->getId() ?? 0;
    }

    /**
     * Gather all configuration.
     *
     * @return array
     */
    public function collectModuleConfig(): array
    {
        $storeId = $this->getStoreId();
        if (isset($this->config[$storeId]['enabled'])) {
            return $this->config[$storeId];
        }

        try {
            $this->config[$storeId]['enabled'] = $this->scopeConfig->getValue('sentry/environment/enabled', ScopeInterface::SCOPE_STORE)
                ?? $this->deploymentConfig->get('sentry') !== null;
        } catch (TableNotFoundException|FileSystemException|RuntimeException|DomainException|Zend_Db_Adapter_Exception $e) {
            $this->config[$storeId]['enabled'] = $this->deploymentConfig->get('sentry') !== null;
        }

        foreach ($this->configKeys as $key => $config) {
            try {
                $value = $this->scopeConfig->getValue('sentry/environment/'.$key, ScopeInterface::SCOPE_STORE)
                    ?? $this->deploymentConfig->get('sentry/'.$key);
            } catch (TableNotFoundException|FileSystemException|RuntimeException|DomainException|Zend_Db_Adapter_Exception $e) {
                $value = $this->deploymentConfig->get('sentry/'.$key);
            }

            $this->config[$storeId][$key] = $this->processConfigValue(
                $value,
                $config
            );
        }

        return $this->config[$storeId];
    }

    /**
     * Parse the config value to the type defined in the config.
     *
     * @param mixed $value
     * @param array $config
     *
     * @return mixed
     */
    public function processConfigValue(mixed $value, array $config): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($config['type']) {
            'array'  => is_array($value) ? $value : $this->serializer->unserialize($value),
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => (bool) $value,
            'string' => (string) $value,
            default  => $value,
        };
    }

    /**
     * Whether Sentry is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActiveWithReason()['active'];
    }

    /**
     * Whether sentry is active, adding a reason why not.
     *
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
        if (!$dsnNotEmpty && !$this->isSpotlightEnabled()) {
            $reasons[] = __('DSN is empty.');
        }
        if (!$productionMode) {
            $reasons[] = __('Not in production and development mode is false.');
        }

        return count($reasons) > 0 ? ['active' => false, 'reasons' => $reasons] : ['active' => true];
    }

    /**
     * Whether the application is in production.
     *
     * @return bool
     */
    public function isProductionMode(): bool
    {
        return $this->appState->emulateAreaCode(Area::AREA_GLOBAL, [$this, 'getAppState']) === 'production';
    }

    /**
     * Get the current mode.
     *
     * @return string
     */
    public function getAppState(): string
    {
        return $this->appState->getMode();
    }

    /**
     * Is sentry enabled on development mode?
     *
     * @return bool
     */
    public function isOverwriteProductionMode(): bool
    {
        $config = $this->collectModuleConfig();

        return (isset($config['mage_mode_development']) && $config['mage_mode_development']) || $this->isSpotlightEnabled();
    }

    /**
     * Get the current magento version.
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
    public function getStore(): ?\Magento\Store\Api\Data\StoreInterface
    {
        try {
            return $this->storeManager->getStore();
        } catch (DomainException|Zend_Db_Adapter_Exception|NoSuchEntityException $e) {
            // If the store is not available, return null
            return null;
        }
    }

    /**
     * Should the stacktrace get cleaned up?
     *
     * @return bool
     */
    public function getCleanStacktrace(): bool
    {
        return $this->collectModuleConfig()['clean_stacktrace'] ?? true;
    }

    /**
     * Is php tracking enabled?
     *
     * @return bool
     */
    public function isPhpTrackingEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_php_tracking', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Is php performance tracking enabled?
     *
     * @return bool
     */
    public function isPerformanceTrackingEnabled(): bool
    {
        return $this->isTracingEnabled() && ($this->collectModuleConfig()['performance_tracking_enabled'] ?? false);
    }

    /**
     * Get excluded Magento areas which should be not profiled.
     *
     * @return string[]
     */
    public function getPerformanceTrackingExcludedAreas(): array
    {
        return $this->collectModuleConfig()['performance_tracking_excluded_areas'] ?? ['adminhtml', 'crontab'];
    }

    /**
     * Is the script tag enabled?
     *
     * @return bool
     */
    public function useScriptTag()
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_script_tag', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether to enable session replay.
     */
    public function useSessionReplay(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'enable_session_replay', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the session replay sample rate.
     */
    public function getReplaySessionSampleRate(): float
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_session_sample_rate') ?? 0.1;
    }

    /**
     * Get the session replay error sample rate.
     */
    public function getReplayErrorSampleRate(): float
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_error_sample_rate') ?? 1;
    }

    /**
     * Whether to block media during replay.
     */
    public function getReplayBlockMedia(): bool
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_block_media') ?? true;
    }

    /**
     * Whether to show mask text.
     */
    public function getReplayMaskText(): bool
    {
        return $this->getConfigValue(static::XML_PATH_SRS.'replay_mask_text') ?? true;
    }

    /**
     * Should we load the script tag in the current block?
     *
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
     * Get logrocket key.
     *
     * @return mixed
     */
    public function getLogrocketKey()
    {
        return $this->collectModuleConfig()['logrocket_key'];
    }

    /**
     * Whether to use logrocket.
     *
     * @return bool
     */
    public function useLogrocket(): bool
    {
        return $this->scopeConfig->isSetFlag(static::XML_PATH_SRS.'use_logrocket') &&
            isset($this->collectModuleConfig()['logrocket_key']) &&
            $this->getLogrocketKey() !== null;
    }

    /**
     * Should logrocket identify.
     *
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
     * Whether to remove static content versioning from the url sent to sentry.
     *
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
     * Whether to remove store code from the url sent to sentry.
     *
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
     * Get the ErrorException reporting level we will send at.
     *
     * @return int
     */
    public function getErrorTypes(): int
    {
        return (int) ($this->collectModuleConfig()['error_types'] ?? $this->collectModuleConfig()['errorexception_reporting'] ?? error_reporting());
    }

    /**
     * Get a list of exceptions we should never send to Sentry.
     *
     * @return array
     */
    public function getIgnoreExceptions(): array
    {
        return $this->collectModuleConfig()['ignore_exceptions'] ?? [];
    }

    /**
     * Check whether we should capture the given exception based on severity and ignore exceptions.
     *
     * @param Throwable $ex
     *
     * @return bool
     */
    public function shouldCaptureException(Throwable $ex): bool
    {
        if ($ex instanceof ErrorException && !($ex->getSeverity() & $this->getErrorTypes())) {
            return false;
        }

        if (in_array(get_class($ex), $this->getIgnoreExceptions())) {
            return false;
        }

        if ($ex->getPrevious()) {
            return $this->shouldCaptureException($ex->getPrevious());
        }

        return true;
    }
}
