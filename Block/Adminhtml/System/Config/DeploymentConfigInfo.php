<?php

namespace JustBetter\Sentry\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DeploymentConfigInfo extends Field
{
    /**
     * @var string
     */
    protected $_template = 'system/config/deployment-config-info.phtml';

    public function render(AbstractElement $element)
    {
        return $this->_toHtml();
    }
}
