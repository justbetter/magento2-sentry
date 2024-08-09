# Magento 2 Sentry Logger

This Magento 2 module integrates the [Sentry sdk](https://github.com/getsentry/sentry-php) into magento 2. Depending on the log level configured in the backend of magento 2, notifications and errors can be send to sentry.

## Installation
- `composer require justbetter/magento2-sentry`
- `bin/magento module:enable JustBetter_Sentry`
- `bin/magento setup:upgrade`
- `bin/magento setup:di:compile`
- `bin/magento setup:static-content:deploy`

## Configuration
For configuration with Adobe Cloud, [check below](#configuration-for-adobe-cloud).

This module uses the [Magento Deployment Configuration](https://devdocs.magento.com/guides/v2.3/config-guide/config/config-php.html) for most it's configuration. This means that you need to add this array to your `app/etc/env.php`:

```
'sentry' => [
    'dsn' => 'example.com',
    'logrocket_key' => 'example/example',
    'environment' => null,
    'log_level' => \Monolog\Logger::WARNING,
    'errorexception_reporting' => E_ALL,
    'ignore_exceptions' => [],
    'mage_mode_development' => false,
    'js_sdk_version' => \JustBetter\Sentry\Block\SentryScript::CURRENT_VERSION,
    'tracing_enabled' => true,
    'tracing_sample_rate' => 0.5,
    'ignore_js_errors' => [],
    'disable_default_integrations' => [
        \Sentry\Integration\ModulesIntegration::class,
    ]
]
```

Next to that there are some configuration options under Stores > Configuration > JustBetter > Sentry.

### Configuration values
* `dsn`: Please enter here the DSN you got from Sentry for your project. You can find the DSN in the project settings under "Client Key (DSN)"
* `environment`: Here you can specify the environment under which the deployed version is running. Common used environments are production, staging, and development. With this option you can differentiate between errors which happen on the staging and i.e. on the production system
* `log_level`: With this configuration you can specify from which logging level on Sentry should get the messages
* `errorexception_reporting`: If the Exception being thrown is an instance of [ErrorException](https://www.php.net/manual/en/class.errorexception.php) send the error to sentry if it matches the error reporting. This uses the same syntax as [Error Reporting](https://www.php.net/manual/en/function.error-reporting.php) eg. `E_ERROR | E_WARNING` to only log Errors and Warnings.
* `ignore_exceptions`: If the class being thrown matches any in this list do not send it to Sentry e.g. `[\Magento\Framework\Exception\NoSuchEntityException::class]`
* `mage_mode_development`: If this option is set to true you will receive issues in Sentry even if you're Magento is running in develop mode.
* `js_sdk_version`: if this option is set, it will load the explicit version of the javascript SDK of Sentry.
* `tracing_enabled` if this option is set to true, tracing got enabled (bundle file got loaded automatically). Default: `false`
* `tracing_sample_rate` if tracing is enabled, you should also set the sample rate. Default: `0.2`
* `ignore_js_errors` array of javascript error messages, which should be not send to Sentry. (see also `ignoreErrors` in [Sentry documentation](https://docs.sentry.io/clients/javascript/config/))
* `disable_default_integrations` provide a list of FQCN of default integrations, which you do not want to use. [List of default integrations](https://github.com/getsentry/sentry-php/tree/master/src/Integration). Default: `[]`

### Configuration for Adobe Cloud
Since Adobe Cloud doesn't allow you to add manually add content to the `env.php` file, the configuration can be done
using the "Variables" in Adobe Commerce using the following variables:

* `CONFIG__SENTRY__ENVIRONMENT__ENABLED`: boolean
* `CONFIG__SENTRY__ENVIRONMENT__DSN`: string
* `CONFIG__SENTRY__ENVIRONMENT__LOGROCKET_KEY`: string
* `CONFIG__SENTRY__ENVIRONMENT__ENVIRONMENT`: string
* `CONFIG__SENTRY__ENVIRONMENT__LOG_LEVEL`: integer
* `CONFIG__SENTRY__ENVIRONMENT__ERROREXCEPTION_REPORTING`: integer
* `CONFIG__SENTRY__ENVIRONMENT__IGNORE_EXCEPTIONS`: A JSON encoded array of classes
* `CONFIG__SENTRY__ENVIRONMENT__MAGE_MODE_DEVELOPMENT`: string
* `CONFIG__SENTRY__ENVIRONMENT__JS_SDK_VERSION`: string
* `CONFIG__SENTRY__ENVIRONMENT__TRACING_ENABLED`: boolean
* `CONFIG__SENTRY__ENVIRONMENT__TRACING_SAMPLE_RATE`: float
* `CONFIG__SENTRY__ENVIRONMENT__IGNORE_JS_ERRORS`: A JSON encoded array of error messages

The following configuration settings can be overridden in the Magento admin. This is limited to ensure that changes to
particular config settings can only be done on server level and can't be broken by changes in the admin.

## Optional error page configuration
- Optional you can configure custom error pages in pub/errors. You can use the sentry feedback form and insert here the sentry log ID. The Sentry Log Id is captured in de customer session and can be retrieved in `processor.php`.

## Sending additional data to Sentry when logging errors
- When calling any function from the [Psr\Log\LoggerInterface](https://github.com/php-fig/log/blob/master/src/LoggerInterface.php) you can pass any data to the parameter $context and it will be send to Sentry as 'Custom context'.

## Change / Filter events
This module has an event called `sentry_before_send` that is dispatched before setting the config [before_send](https://docs.sentry.io/platforms/php/configuration/filtering/#using-platformidentifier-namebefore-send-). This provides the means to edit / filter events. You could for example add extra criteria to determine if the exception should be captured to Sentry. To prevent the Exception from being captured you can set the event to `null` or unset it completly.

```PHP
public function execute(\Magento\Framework\Event\Observer $observer)
{
    $observer->getEvent()->getSentryEvent()->unsEvent();
}
```

Example: https://github.com/justbetter/magento2-sentry-filter-events

## Compatibility
The module is tested on Magento version 2.4.x with sentry sdk version 3.x. Magento 2.1.x is not supported by us anymore, feel free to fork this project or make a pull request.

## Ideas, bugs or suggestions?
Please create a [issue](https://github.com/justbetter/magento2-sentry/issues) or a [pull request](https://github.com/justbetter/magento2-sentry/pulls).

## About us
We’re a innovative development agency from The Netherlands building awesome websites, webshops and web applications with Laravel and Magento. Check out our website [justbetter.nl](https://justbetter.nl) and our [open source projects](https://github.com/justbetter).

## License
[MIT](LICENSE)

---

<a href="https://justbetter.nl" title="JustBetter"><img src="https://raw.githubusercontent.com/justbetter/art/master/justbetter-logo.png" width="200px" alt="JustBetter logo"></a>
