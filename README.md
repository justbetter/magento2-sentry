# Magento 2 Sentry Logger

This Magento 2 module integrates the [Sentry sdk](https://github.com/getsentry/sentry-php) into magento 2. Depending on the log level configured in the backend of magento 2, notifications and errors can be send to sentry.

## Installation
- `composer require justbetter/magento2-sentry`
- `bin/magento module:enable JustBetter_Sentry`
- `bin/magento setup:upgrade`
- `bin/magento setup:di:compile`
- `bin/magento setup:static-content:deploy`

## Configuration
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
    'js_sdk_version' => \JustBetter\Sentry\Block\SentryScript::CURRENT_VERSION
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

## Optional error page configuration
- Optional you can configure custom error pages in pub/errors. You can use the sentry feedback form and insert here the sentry log ID. The Sentry Log Id is captured in de customer session and can be retrieved in `processor.php`.

## Sending additional data to Sentry when logging errors
- When calling any function from the [Psr\Log\LoggerInterface](https://github.com/php-fig/log/blob/master/src/LoggerInterface.php) you can pass any data to the parameter $context and it will be send to Sentry as 'Custom context'.

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
