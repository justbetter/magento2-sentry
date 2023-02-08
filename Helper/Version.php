<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Helper;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\App\View\Deployment\Version\StorageInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Deployment version of static files.
 */
class Version extends AbstractHelper
{
    /**
     * @param State $appState
     * @param StorageInterface $versionStorage
     * @param string $cachedValue
     * @param LoggerInterface $logger
     * @param DeploymentConfig|null $deploymentConfig
     */
    public function __construct(
        private readonly State $appState,
        private readonly StorageInterface $versionStorage,
        private string $cachedValue,
        private LoggerInterface $logger,
        private ?DeploymentConfig $deploymentConfig = null
    ) {
        $this->deploymentConfig = $deploymentConfig ?: ObjectManager::getInstance()->get(DeploymentConfig::class);
    }

    /**
     * Retrieve deployment version of static files.
     *
     * @return string
     */
    public function getValue(): string
    {
        if (!$this->cachedValue) {
            $this->cachedValue = $this->readValue($this->appState->getMode());
        }

        return $this->cachedValue;
    }

    /**
     * Load or generate deployment version of static files depending on the application mode.
     *
     * @param string $appMode
     *
     * @return string
     */
    protected function readValue(string $appMode): string
    {
        $result = $this->versionStorage->load();
        if (!$result) {
            if ($appMode == State::MODE_PRODUCTION
                && !$this->deploymentConfig->getConfigData(
                    ConfigOptionsListConstants::CONFIG_PATH_SCD_ON_DEMAND_IN_PRODUCTION
                )
            ) {
                $this->getLogger()->critical('Can not load static content version.');

                throw new UnexpectedValueException(
                    'Unable to retrieve deployment version of static files from the file system.'
                );
            }
            $result = $this->generateVersion();
            $this->versionStorage->save($result);
        }

        return (string)$result;
    }

    /**
     * Generate version of static content.
     *
     * @return int
     */
    private function generateVersion(): int
    {
        return time();
    }

    /**
     * Get logger.
     *
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        if ($this->logger == null) {
            $this->logger = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(LoggerInterface::class);
        }

        return $this->logger;
    }
}
