CHANGELOG

2018-08-15 - 0.4.1

   * Removed plugin in di.xml to prevent fatal crash on storefrontend
   * Restored preference in di.xml

2018-08-10 - 0.4.0

   * Refactor of overwrite to plugin with monolog
   * Added user context
   * Added message context
   * Added extra magento store parameters
   * Refactor of ExceptionCatcher to use same SentryLogger
   * Bugfix area code not set
   * Refactor a lot of code
   * PSR2 compliance

2018-04-30 - 0.2.1

   * downgraded requirement monolog for magento 2.1.x

2018-04-30 - 0.2.0

   * Feature request to test sentry in development mode (https://github.com/justbetter/magento2-sentry/issues/4)
   * Added mage deploy mode to sentry in context_tags

2018-04-17 - 0.1.0

   * Feature request for sending test events (https://github.com/justbetter/magento2-sentry/issues/3)
   * Added ACL roles for editing sentry config

2018-03-25 - 0.0.6

   * Bugfix wrong file commit

2018-03-17 - 0.0.5

   * Initial commit module - basic working module
