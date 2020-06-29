<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use JustBetter\Sentry\Helper\Version;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    const CURRENT_VERSION = '5.17.0';

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
        return $this->dataHelper->isActive() &&
            $this->dataHelper->useScriptTag() &&
            $this->dataHelper->showScriptTagInThisBlock($blockName);
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
     * Get the current version of the Magento application.
     *
     * @return int|string
     */
    public function getVersion()
    {
        return $this->version->getValue();
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
}
