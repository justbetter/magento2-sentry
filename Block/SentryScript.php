<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use JustBetter\Sentry\Helper\Version;
use JustBetter\Sentry\Model\JavascriptContext;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    public const CURRENT_VERSION = '8.7.0';

    /**
     * SentryScript constructor.
     *
     * @param DataHelper        $dataHelper
     * @param Version           $version
     * @param Template\Context  $context
     * @param Json              $json
     * @param JavascriptContext $javascriptContext
     * @param array             $data
     */
    public function __construct(// @phpstan-ignore missingType.iterableValue
        private DataHelper $dataHelper,
        private Version $version,
        Template\Context $context,
        private Json $json,
        private JavascriptContext $javascriptContext,
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
    public function canUseScriptTag($blockName): bool
    {
        if (!$this->dataHelper->isActive() || !$this->dataHelper->showScriptTagInThisBlock($blockName)) {
            return false;
        }

        if ($this->useScriptTag()) {
            return true;
        }

        return $this->isSpotlightEnabled();
    }

    /**
     * Get the DSN of Sentry.
     *
     * @return string
     */
    public function getDSN(): string
    {
        return (string) $this->dataHelper->getDSN();
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
     * @return ?string
     */
    public function getVersion(): ?string
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
    public function getLogrocketKey()
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
     * Whether we should replace the document url in Javascript stacktraces.
     *
     * @return bool
     */
    public function stripDocumentUrl(): bool
    {
        return $this->dataHelper->stripDocumentUrl();
    }

    /**
     * Get the Magento request context for the browser SDK as a Javascript object literal.
     *
     * Returns null when the context is disabled or unavailable, in which case none of the
     * context based features may be rendered.
     *
     * @return ?string
     */
    public function getJavascriptContextJson(): ?string
    {
        $context = $this->javascriptContext->get();

        if (!$context) {
            return null;
        }

        // Json::serialize() has no JSON_HEX_* flags, so escape breakout characters ourselves.
        return strtr((string) $this->json->serialize($context), [
            '<'            => '\u003C',
            '>'            => '\u003E',
            '&'            => '\u0026',
            "\xE2\x80\xA8" => '\u2028',
            "\xE2\x80\xA9" => '\u2029',
        ]);
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
        return (string) $this->json->serialize($this->dataHelper->getIgnoreJsErrors() ?? []);
    }
}
