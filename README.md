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
    'domain' => 'example.com',
    'environment' => null,
    'log_level' => \Monolog\Logger::WARNING,
    'mage_mode_development' => false,
]
```

Next to that there are some configuration options under Stores > Configuration > JustBetter > Sentry.

## Optional error page configuration
- Optional you can configure custom error pages in pub/errors. You can use the sentry feedback form and insert here the sentry log ID. The Sentry Log Id is captured in de customer session and can be retrieved in `processor.php`. Soon(2020-Q1) I'll integrate this in the module.

## Compability
The module is tested on Magento version 2.2.x & 2.3.x with sentry sdk version 2.x. Magento 2.1.x is not supported by us anymore, feel free to fork this project or make a pull request.

## Ideas, bugs or suggestions?
Please create a [issue](https://github.com/justbetter/magento2-sentry/issues) or a [pull request](https://github.com/justbetter/magento2-sentry/pulls).

## Todo
- Integrate custom error pages in composer package - 2020-Q1
- Integrate feedback sentry form in error pages - 2020-Q1

## About us
We’re a innovative development agency from The Netherlands building awesome websites, webshops and web applications with Laravel and Magento. Check out our website [justbetter.nl](https://justbetter.nl) and our [open source projects](https://github.com/justbetter).

## License
[MIT](LICENSE)

---

<a href="https://justbetter.nl" title="JustBetter"><img src="https://raw.githubusercontent.com/justbetter/art/master/justbetter-logo.png" width="200px" alt="JustBetter logo"></a>
