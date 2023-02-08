<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Button extends Field
{
    protected string $_template = 'system/config/button.phtml';

    /**
     * Unset scope.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope();

        return parent::render($element);
    }

    /**
     * Get the button and scripts contents.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();
        $this->addData(
            [
                'button_label' => $originalData['button_label'],
                'button_url'   => $this->getUrl($originalData['button_url']),
                'html_id'      => $element->getHtmlId(),
            ]
        );

        return $this->_toHtml();
    }
}
