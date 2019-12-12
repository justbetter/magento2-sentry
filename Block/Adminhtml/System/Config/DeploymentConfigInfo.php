<?php

namespace JustBetter\Sentry\Block\Adminhtml\System\Config;

use JustBetter\Sentry\Helper\Version;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DeploymentConfigInfo extends Field
{
    /**
     * @var Version
     */
    private $version;

    /**
     * @var string
     */
    protected $_template = 'system/config/deployment-config-info.phtml';

    /**
     * DeploymentConfigInfo constructor.
     *
     * @param Context $context
     * @param array   $data
     * @param Version $version
     */
    public function __construct(
        Context $context,
        Version $version,
        array $data = []
    ) {
        $this->version = $version;
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get static version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version->getValue();
    }
}
