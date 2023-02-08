<?php

declare(strict_types=1);

namespace JustBetter\Sentry\Plugin;

use Magento\Customer\CustomerData\Customer;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Customer\Model\Session;

class LogrocketCustomerInfo
{
    /**
     * @param CurrentCustomer $currentCustomer
     * @param Session $customerSession
     */
    public function __construct(
        protected CurrentCustomer $currentCustomer,
        protected Session $customerSession
    ) {
    }

    /**
     * @param Customer $subject
     * @param $result
     * @return mixed
     */
    public function afterGetSectionData(Customer $subject, $result): mixed
    {
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->currentCustomer->getCustomer();

            $result['email'] = $customer->getEmail();
            $result['fullname'] = $customer->getFirstname().' '.$customer->getLastname();
        }

        return $result;
    }
}
