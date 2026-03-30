<?php

namespace JustBetter\Sentry\Plugin;

use Magento\Customer\CustomerData\Customer;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Customer\Model\Session;

class LogrocketCustomerInfo
{
    /**
     * LogrocketCustomerInfo construct.
     *
     * @param CurrentCustomer $currentCustomer
     * @param Session         $customerSession
     */
    public function __construct(
        protected CurrentCustomer $currentCustomer,
        protected Session $customerSession
    ) {
    }

    /**
     * Add customer info to the section.
     *
     * @param Customer $subject
     * @param array    $result
     *
     * @return array $result // @phpstan-ignore missingType.iterableValue
     */
    public function afterGetSectionData(Customer $subject, array $result): array // @phpstan-ignore missingType.iterableValue
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        $customer = $this->currentCustomer->getCustomer();

        $result['email'] = $customer->getEmail();
        $result['fullname'] = $customer->getFirstname().' '.$customer->getLastname();

        return $result;
    }
}
