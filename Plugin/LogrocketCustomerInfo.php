<?php

namespace JustBetter\Sentry\Plugin;

use Magento\Customer\CustomerData\Customer;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Customer\Model\Session;

class LogrocketCustomerInfo
{
    public function __construct(
        protected CurrentCustomer $currentCustomer,
        protected Session $session
    ) {
    }

    public function afterGetSectionData(Customer $subject, $result)
    {
        if ($this->session->isLoggedIn()) {
            $customer = $this->currentCustomer->getCustomer();

            $result['email'] = $customer->getEmail();
            $result['fullname'] = $customer->getFirstname().' '.$customer->getLastname();
        }

        return $result;
    }
}
