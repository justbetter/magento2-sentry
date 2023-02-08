<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use JustBetter\Sentry\Helper\Version;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class SentryScript extends Template
{
    const CURRENT_VERSION = '5.28.0';

    /**
     * SentryScript constructor.
     *
     * @param DataHelper $dataHelper
     * @param Version $version
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        private readonly DataHelper $dataHelper,
        private readonly Version $version,
        protected Context $context,
        protected array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Show script tag depending on blockName.
     *
     * @param string $blockName
     *
     * @return bool
     */
    public function canUseScriptTag(string $blockName): bool
    {
        return $this->dataHelper->isActive() &&
            $this->dataHelper->useScriptTag() &&
            $this->dataHelper->showScriptTagInThisBlock($blockName);
    }

    /**
     * Get the DSN of Sentry.
     *
     * @return string
     */
    public function getDSN(): string
    {
        return $this->dataHelper->getDSN();
    }

    /**
     * Get the version of the JS-SDK of Sentry.
     *
     * @return string
     */
    public function getJsSdkVersion(): string
    {
        return $this->dataHelper->getJsSdkVersion();
    }

    /**
     * Get the current version of the Magento application.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version->getValue();
    }

    /**
     * Get the current environment of Sentry.
     *
     * @return mixed
     */
    public function getEnvironment(): mixed
    {
        return $this->dataHelper->getEnvironment();
    }

    /**
     * If LogRocket should be used.
     *
     * @return bool
     */
    public function useLogRocket(): bool
    {
        return $this->dataHelper->useLogrocket();
    }

    /**
     * If LogRocket identify should be used.
     *
     * @return bool
     */
    public function useLogRocketIdentify(): bool
    {
        return $this->dataHelper->useLogrocketIdentify();
    }

    /**
     * Gets the LogRocket key.
     *
     * @return string
     */
    public function getLogrocketKey(): string
    {
        return $this->dataHelper->getLogrocketKey();
    }

    /**
     * Whether we should strip the static content version from the URL.
     *
     * @return bool
     */
    public function stripStaticContentVersion(): bool
    {
        return $this->dataHelper->stripStaticContentVersion();
    }

    /**
     * Whether we should strip the store code from the URL.
     *
     * @return bool
     */
    public function stripStoreCode(): bool
    {
        return $this->dataHelper->stripStoreCode();
    }

    /**
     * @return mixed
     */
    public function getStoreCode(): mixed
    {
        return $this->_storeManager->getStore()->getCode();
    }

    /**
     * @return bool
     */
    public function isTracingEnabled(): bool
    {
        return $this->dataHelper->isTracingEnabled();
    }

    /**
     * @return float
     */
    public function getTracingSampleRate(): float
    {
        return $this->dataHelper->getTracingSampleRate();
    }
}
