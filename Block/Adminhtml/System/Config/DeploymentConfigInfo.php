<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Block\Adminhtml\System\Config;

use JustBetter\Sentry\Helper\Version;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DeploymentConfigInfo extends Field
{
    /**
     * DeploymentConfigInfo constructor.
     *
     * @param Context $context
     * @param Version $version
     * @param string $_template
     * @param array $data
     */
    public function __construct(
        protected Context $context,
        private readonly Version $version,
        protected string $_template = 'system/config/deployment-config-info.phtml',
        protected array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return mixed
     */
    public function render(AbstractElement $element): mixed
    {
        return $this->_toHtml();
    }

    /**
     * Get static version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version->getValue();
    }
}
