<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use JustBetter\Sentry\Helper\Version;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    const CURRENT_VERSION = '7.39.0';

    /**
     * @var DataHelper
     */
    private $dataHelper;

    /**
     * @var Version
     */
    private $version;

    /**
     * SentryScript constructor.
     *
     * @param DataHelper       $dataHelper
     * @param Template\Context $context
     * @param array            $data
     */
    public function __construct(
        DataHelper $dataHelper,
        Version $version,
        Template\Context $context,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        $this->version = $version;

        parent::__construct($context, $data);
    }

    /**
     * Show script tag depending on blockName.
     *
     * @param string $blockName
     *
     * @return bool
     */
    public function canUseScriptTag($blockName)
    {
        if (!$this->dataHelper->isActive()) {
            return false;
        }

        if ($this->dataHelper->useScriptTag() && $this->dataHelper->showScriptTagInThisBlock($blockName)) {
            return true;
        }

        if ($this->dataHelper->useSessionReplay()) {
            return true;
        }

        return false;
    }

    /**
     * Get the DSN of Sentry.
     *
     * @return string
     */
    public function getDSN()
    {
        return $this->dataHelper->getDSN();
    }

    /**
     * Get the version of the JS-SDK of Sentry.
     *
     * @return string
     */
    public function getJsSdkVersion()
    {
        return $this->dataHelper->getJsSdkVersion();
    }

    /**
     * Get the current version of the Magento application.
     *
     * @return int|string
     */
    public function getVersion()
    {
        return $this->version->getValue();
    }

    /**
     * Get the current environment of Sentry.
     *
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->dataHelper->getEnvironment();
    }

    public function useSessionReplay(): bool
    {
        return $this->dataHelper->useSessionReplay();
    }

    public function getReplaySessionSampleRate(): float
    {
        return $this->dataHelper->getReplaySessionSampleRate();
    }

    public function getReplayErrorSampleRate(): float
    {
        return $this->dataHelper->getReplayErrorSampleRate();
    }

    public function getReplayBlockMedia(): bool
    {
        return $this->dataHelper->getReplayBlockMedia();
    }

    public function getReplayMaskText(): bool
    {
        return $this->dataHelper->getReplayMaskText();
    }

    /**
     * If LogRocket should be used.
     *
     * @return bool
     */
    public function useLogRocket()
    {
        return $this->dataHelper->useLogrocket();
    }

    /**
     * If LogRocket identify should be used.
     *
     * @return bool
     */
    public function useLogRocketIdentify()
    {
        return $this->dataHelper->useLogrocketIdentify();
    }

    /**
     * Gets the LogRocket key.
     *
     * @return string
     */
    public function getLogrocketKey()
    {
        return $this->dataHelper->getLogrocketKey();
    }

    /**
     * Whether we should strip the static content version from the URL.
     *
     * @return bool
     */
    public function stripStaticContentVersion()
    {
        return $this->dataHelper->stripStaticContentVersion();
    }

    /**
     * Whether we should strip the store code from the URL.
     *
     * @return bool
     */
    public function stripStoreCode()
    {
        return $this->dataHelper->stripStoreCode();
    }

    public function getStoreCode()
    {
        return $this->_storeManager->getStore()->getCode();
    }

    public function isTracingEnabled(): bool
    {
        return $this->dataHelper->isTracingEnabled();
    }

    public function getTracingSampleRate(): float
    {
        return $this->dataHelper->getTracingSampleRate();
    }
}
