<?php

namespace JustBetter\Sentry\Controller\Adminhtml\Test;

use JustBetter\Sentry\Helper\Data;
use JustBetter\Sentry\Model\SentryLog;
use JustBetter\Sentry\Plugin\MonologPlugin;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
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
    const ADMIN_RESOURCE = 'JustBetter_Sentry::sentry';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Json
     */
    private $jsonSerializer;
    /**
     * @var Data
     */
    private $helperSentry;
    /**
     * @var JustBetter\Sentry\Model\SentryLog|SentryLog
     */
    private $monologPlugin;

    /**
     * @var ShellInterface
     */
    private $shellBackground;

    /**
     * Sentry constructor.
     *
     * @param Context         $context
     * @param PageFactory     $resultPageFactory
     * @param Json            $jsonSerializer
     * @param LoggerInterface $logger
     * @param Data            $helperSentry
     * @param MonologPlugin   $monologPlugin
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Json $jsonSerializer,
        LoggerInterface $logger,
        Data $helperSentry,
        MonologPlugin $monologPlugin
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->helperSentry = $helperSentry;
        $this->monologPlugin = $monologPlugin;

        parent::__construct($context);
    }

    /**
     * Execute view action.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = ['status' => false];

        $activeWithReason = $this->helperSentry->isActiveWithReason();

        if ($activeWithReason['active']) {
            try {
                $this->monologPlugin->addAlert('TEST message from Magento 2', []);
                $result['status'] = true;
                $result['content'] = __('Check sentry.io which should hold an alert');
            } catch (\Exception $e) {
                $result['content'] = $e->getMessage();
                $this->logger->critical($e);
            }
        } else {
            $result['content'] = implode(PHP_EOL, $activeWithReason['reasons']);
        }

        return $this->getResponse()->representJson(
            $this->jsonSerializer->serialize($result)
        );
    }
}
