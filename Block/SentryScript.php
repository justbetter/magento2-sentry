<?php

namespace JustBetter\Sentry\Block;

use JustBetter\Sentry\Helper\Data as DataHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;

class SentryScript extends Template
{
    /**
     * SentryScript constructor.
     *
     * @param DataHelper $dataHelper
     * @param Session $customerSession
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        DataHelper $dataHelper,
        Session $customerSession,
        Template\Context $context,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        $this->customerSession = $customerSession;

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
     * If LogRocket should be used
     *
     * @return bool
     */
    public function useLogRocket()
    {
        return $this->dataHelper->useLogrocket();
    }

    /**
     * If LogRocket identify should be used
     *
     * @return bool
     */
    public function useLogRocketIdentify()
    {
        return $this->dataHelper->useLogrocketIdentify() && $this->customerSession->isLoggedIn();
    }

    /**
     * Gets the LogRocket key
     *
     * @return string
     */
    public function getLogrocketKey()
    {
        return $this->dataHelper->getLogrocketKey();
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->customerSession->getCustomerId();
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomerName()
    {
        $firstName = $this->customerSession->getCustomerData()->getFirstname();
        $lastName = $this->customerSession->getCustomerData()->getLastname();

        return $firstName . ' ' . $lastName;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomerEmail()
    {
        return $this->customerSession->getCustomerData()->getEmail();
    }
}
