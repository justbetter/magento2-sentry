<?php

namespace JustBetter\Sentry\Controller\Adminhtml\Test;

use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action;
use Magento\Framework\Json\Helper\Data;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Filesystem\DirectoryList;

/**
 * Class Sentry
 *
 * @package JustBetter\Sentry\Controller\Adminhtml\Test
 */
class Sentry extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'JustBetter_Sentry::sentry';

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * Sentry constructor.
     *
     * @param \Magento\Backend\App\Action\Context        $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data        $jsonHelper
     * @param \Psr\Log\LoggerInterface                   $logger
     * @param DirectoryList                              $directoryList
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $jsonHelper,
        LoggerInterface $logger,
        DirectoryList $directoryList
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper        = $jsonHelper;
        $this->logger            = $logger;
        $this->directoryList     = $directoryList;

        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = ['status' => false];
        $sentryDomain = $this->getRequest()->getParam('domainSentry');
        $composerBin = $this->directoryList->getRoot() .'/vendor/bin/';

        if ($sentryDomain && is_dir($composerBin)) {
            try {
                $result['status']  = true;
                $result['content'] = nl2br(shell_exec(
                    $composerBin . 'sentry test ' . escapeshellarg($sentryDomain) . ' -v'
                ));
            } catch (\Exception $e) {
                $result['content'] = $e->getMessage();
                $this->logger->critical($e);
            }
        } else {
            $result['content'] = __('Sentry Domain not filled or composer bin not found!');
        }


        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($result)
        );
    }
}
