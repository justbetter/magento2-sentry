# Magento 2 Sentry Logger

This Magento 2 module integrates the [Sentry sdk](https://github.com/getsentry/sentry-php) into magento 2. Depending on the log level configured in the backend of magento 2, notifications and errors can be send to sentry.

## Installation
- `composer require justbetter/magento2-sentry`
- `bin/magento module:enable JustBetter_Sentry`
- `bin/magento setup:upgrade && bin/magento setup:static-content:deploy`

## Configuration
- Options for the module are defined in the backend under Stores > Configuration > JustBetter > Sentry configuration

## Optional error page configuration
- Optional you can configure custom error pages in pub/errors. You can use the sentry feedback form and insert here the sentry log ID. The Sentry Log Id is captured in de customer session and can be retrieved in `processor.php`. Soon I'll integrate this in the module.

## Compability
The module is tested on magento version 2.2.3 with sentry sdk version 1.8.x

## Ideas, bugs or suggestions?
Please create a [issue](https://github.com/justbetter/magento2-sentry/issues) or a [pull request](https://github.com/justbetter/magento2-sentry/pulls).

## Todo
- Integrate custom error pages in composer package
- Integrate feedback sentry form in error pages

## License
[MIT](LICENSE)

---

<a href="https://justbetter.nl" title="JustBetter"><img src="https://raw.githubusercontent.com/justbetter/art/master/justbetter-logo.png" width="200px" alt="JustBetter logo"></a>
