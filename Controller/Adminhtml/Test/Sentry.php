<?php

namespace JustBetter\Sentry\Controller\Adminhtml\Test;

use JustBetter\Sentry\Helper\Data;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Sentry extends Action
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'JustBetter_Sentry::sentry';

    /**
     * Sentry constructor.
     *
     * @param Context         $context
     * @param PageFactory     $resultPageFactory
     * @param Json            $jsonSerializer
     * @param LoggerInterface $logger
     * @param Data            $helperSentry
     * @param Monolog         $monolog
     */
    public function __construct(
        Context $context,
        protected PageFactory $resultPageFactory,
        private Json $jsonSerializer,
        protected LoggerInterface $logger,
        private Data $helperSentry,
        private Monolog $monolog
    ) {
        parent::__construct($context);
    }

    /**
     * Execute view action.
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $result = ['status' => false];

        $activeWithReason = $this->helperSentry->isActiveWithReason();

        if ($activeWithReason['active']) {
            try {
                if ($this->helperSentry->isPhpTrackingEnabled()) {
                    $this->monolog->addRecord(\Monolog\Logger::ALERT, 'TEST message from Magento 2', []);
                    $result['status'] = true;
                    $result['content'] = __('Check sentry.io which should hold an alert');
                } else {
                    $result['content'] = __('Php error tracking must be enabled for testing');
                }
            } catch (\Exception $e) {
                $result['content'] = $e->getMessage();
                $this->logger->critical($e);
            }
        } else {
            $result['content'] = implode(PHP_EOL, $activeWithReason['reasons']);
        }

        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();

        return $response->representJson(
            $this->jsonSerializer->serialize($result)
        );
    }
}
