<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    public function __construct(DataHelper $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    public function canUseScriptTag($blockName)
    {
        if (!$dataHelper->isActive() ||
            !$dataHelper->useScriptTag() ||
            !$dataHelper->showScriptTagInThisBlock($blockName)
        ) {
            return false;
        }

        return true;
    }

    public function getDSN()
    {
        return $this->dataHelper->getDSN();
    }
}
