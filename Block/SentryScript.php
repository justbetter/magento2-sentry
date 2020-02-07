<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    /**
     * SentryScript constructor.
     * @param DataHelper $dataHelper
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        DataHelper $dataHelper,
        Template\Context $context,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;

        parent::__construct($context, $data);
    }

    /**
     * Show script tag depending on blockName.
     *
     * @param string $blockName
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
}
