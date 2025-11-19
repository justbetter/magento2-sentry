<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use JustBetter\Sentry\Helper\Version;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    public const CURRENT_VERSION = '8.7.0';

    /**
     * SentryScript constructor.
     *
     * @param DataHelper       $dataHelper
     * @param Version          $version
     * @param Template\Context $context
     * @param Json             $json
     * @param array            $data
     */
    public function __construct(
        private DataHelper $dataHelper,
        private Version $version,
        Template\Context $context,
        private Json $json,
        array $data = []
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
    public function canUseScriptTag($blockName)
    {
        if (!$this->dataHelper->isActive() || !$this->dataHelper->showScriptTagInThisBlock($blockName)) {
            return false;
        }

        if ($this->useScriptTag()) {
            return true;
        }

        if ($this->isSpotlightEnabled()) {
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
        return (string) $this->dataHelper->getDSN();
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

    /**
     * Whether to enable sentry js tracking.
     */
    public function useScriptTag(): bool
    {
        return $this->dataHelper->useScriptTag();
    }

    /**
     * Assembles and returns the JS script path
     */
    public function getJsUrl(): string
    {
        $bundleFile = $this->dataHelper->getLoaderScript();
        if ($bundleFile) {
            return $bundleFile;
        }

        $bundleFile = 'bundle';

        if ($this->isTracingEnabled()) {
            $bundleFile .= '.tracing';
        }

        if ($this->useSessionReplay()) {
            $bundleFile .= '.replay';
        }

        $bundleFile .= '.min.js';

        return sprintf(
            'https://browser.sentry-cdn.com/%s/%s',
            $this->getJsSdkVersion(),
            $bundleFile
        );
    }

    /**
     * Whether to enable session replay.
     */
    public function useSessionReplay(): bool
    {
        return $this->dataHelper->useSessionReplay();
    }

    /**
     * Get the session replay sample rate.
     */
    public function getReplaySessionSampleRate(): float
    {
        return $this->dataHelper->getReplaySessionSampleRate();
    }

    /**
     * Get the session replay error sample rate.
     */
    public function getReplayErrorSampleRate(): float
    {
        return $this->dataHelper->getReplayErrorSampleRate();
    }

    /**
     * Whether to block media during replay.
     */
    public function getReplayBlockMedia(): bool
    {
        return $this->dataHelper->getReplayBlockMedia();
    }

    /**
     * Whether to show mask text.
     */
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

    /**
     * Get Store code.
     *
     * @return string
     */
    public function getStoreCode()
    {
        return $this->_storeManager->getStore()->getCode();
    }

    /**
     * Whether tracing is enabled.
     */
    public function isTracingEnabled(): bool
    {
        return $this->dataHelper->isTracingEnabled();
    }

    /**
     * Whether spotlight is enabled.
     */
    public function isSpotlightEnabled(): bool
    {
        return $this->dataHelper->isSpotlightEnabled();
    }

    /**
     * Get sample rate for tracing.
     */
    public function getTracingSampleRate(): float
    {
        return $this->dataHelper->getTracingSampleRate();
    }

    /**
     * Get a list of js errors to ignore.
     */
    public function getIgnoreJsErrors(): string
    {
        return $this->json->serialize($this->dataHelper->getIgnoreJsErrors());
    }
}
