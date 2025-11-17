<a href="https://github.com/justbetter/magento2-sentry" title="JustBetter">
    <img src="./.github/assets/banner.svg" alt="Package banner">
</a>


# Magento 2 Sentry Logger
[![Latest Version on Packagist](https://img.shields.io/packagist/v/justbetter/magento2-sentry.svg?style=flat-square)](https://packagist.org/packages/justbetter/magento2-sentry)
[![Total Downloads](https://img.shields.io/packagist/dt/justbetter/magento2-sentry.svg?style=flat-square)](https://packagist.org/packages/justbetter/magento2-sentry)
![Magento Support](https://img.shields.io/badge/magento-2.4-orange.svg?logo=magento&longCache=true&style=flat-square)
[![PHPStan passing](https://img.shields.io/github/actions/workflow/status/justbetter/magento2-sentry/analyse.yml?label=PHPStan&style=flat-square)](https://github.com/justbetter/magento2-sentry/actions/workflows/analyse.yml)

This Magento 2 module integrates [Sentry](https://github.com/getsentry/sentry-php) into magento 2. 
Depending on the log level configured in the backend of magento 2, notifications and errors can be sent to sentry.

## Features

- Send exceptions and logs to Sentry
- Show detailed context on thrown exceptions (Like Magento user/api consumer id)
- Easily control which events get sent to Sentry
- Backend and frontend error reporting
- Performance sampling
- Cron Monitoring
- Session replay
- Logrocket support
- Sentry feedback form after an error

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
    'log_level' => \Monolog\Level::Warning,
    'error_types' => E_ALL,
    'ignore_exceptions' => [],
    'mage_mode_development' => false,
    'js_sdk_version' => \JustBetter\Sentry\Block\SentryScript::CURRENT_VERSION,
    'tracing_enabled' => true,
    'traces_sample_rate' => 0.5,
    'disable_default_integrations' => [
        \Sentry\Integration\ModulesIntegration::class,
    ],
    'performance_tracking_enabled' => true,
    'performance_tracking_excluded_areas' => [\Magento\Framework\App\Area::AREA_ADMINHTML, \Magento\Framework\App\Area::AREA_CRONTAB],
    'profiles_sample_rate' => 0.5,
    'ignore_js_errors' => [],
    'enable_csp_report_url' => true,
]
```

Next to that there are some configuration options under Stores > Configuration > JustBetter > Sentry.

### Configuration values
| Name | Default | Description |
|---|---|---|
| `dsn`                       | — | The DSN you got from Sentry for your project. You can find the DSN in the project settings under "Client Key (DSN)" |
| `release`                   | the current deployed version in `deployed_version.txt` | Specify the current release version. Example with dynamic git hash: `trim(exec('git --git-dir ' . BP . '/.git' . ' log --pretty="%h" -n1 HEAD'))` |
| `mage_mode_development`     | `false` | If set to true, you will receive issues in Sentry even if Magento is running in develop mode. |
| `environment`               | — | Specify the environment under which the deployed version is running. Common values: production, staging, development. Helps differentiate errors between environments. |
| `max_breadcrumbs`           | `100` | This variable controls the total amount of breadcrumbs that should be captured. |
| `attach_stacktrace`         | `false` | When enabled, stack traces are automatically attached to all messages logged. Even if they are not exceptions. |
| `prefixes`                  | [[BP](https://github.com/magento/magento2/blob/9a62604c5a7ab70db1386d307b0dbfe596611102/app/autoload.php#L16)] | A list of prefixes that should be stripped from the filenames of captured stacktraces to make them relative. |
| `sample_rate`               | `1.0` | Configures the sample rate for error events, in the range of 0.0 to 1.0. |
| `ignore_exceptions`         | `[]` | If the class being thrown matches any in this list, do not send it to Sentry, e.g., `[\Magento\Framework\Exception\NoSuchEntityException::class]` |
| `error_types`               | `E_ALL` | If the Exception is an instance of [ErrorException](https://www.php.net/manual/en/class.errorexception.php), send the error to Sentry if it matches the error reporting. Uses the same syntax as [Error Reporting](https://www.php.net/manual/en/function.error-reporting.php), e.g., `E_ERROR` | E_WARNING`. |
| `log_level`                 | `\Monolog\Level::Warning` | Specify from which logging level on Sentry should get the [messages](https://docs.sentry.io/platforms/php/usage/#capturing-messages). |
| `clean_stacktrace`          | `true` | Whether unnecessary files (like Interceptor.php, Proxy.php, and Factory.php) should be removed from the stacktrace. (They will not be removed if they threw the error.) |
| `tracing_enabled`           | `false` | If set to true, tracing is enabled (bundle file is loaded automatically). |
| `traces_sample_rate`        | `0.2` | If tracing is enabled, set the sample rate. A number between 0 and 1, controlling the percentage chance a given transaction will be sent to Sentry. |
| `traces_sample_rate_cli`    | The value of `traces_sample_rate` | If tracing is enabled, set the sample rate for CLI. A number between 0 and 1, controlling the percentage chance a given transaction will be sent to Sentry. |
| `profiles_sample_rate`      | `0` (disabled) | if this option is larger than 0 (zero), the module will create a profile of the request. Please note that you have to install [Excimer](https://www.mediawiki.org/wiki/Excimer) on your server to use profiling. [Sentry documentation](https://docs.sentry.io/platforms/php/profiling/). You have to enable tracing too. |
| `performance_tracking_enabled` | `false` | if performance tracking is enabled, a performance report gets generated for the request. |
| `performance_tracking_excluded_areas` | `['adminhtml', 'crontab']` | if `performance_tracking_enabled` is enabled, we recommend to exclude the `adminhtml` & `crontab` area. |
| `enable_logs`               | `false` | This option enables the [logging integration](https://sentry.io/product/logs/), which allows the SDK to capture logs and send them to Sentry. |
| `logger_log_level`          | `\Monolog\Level::Notice` | If the logging integration is enabled, specify from which logging level the logger should log |
| `js_sdk_version`            | `\JustBetter\Sentry\Block\SentryScript::CURRENT_VERSION` | If set, loads the explicit version of the JavaScript SDK of Sentry. |
| `ignore_js_errors`          | `[]` | Array of JavaScript error messages which should not be sent to Sentry. (See also `ignoreErrors` in [Sentry documentation](https://docs.sentry.io/clients/javascript/config/)) |
| `disable_default_integrations` | `[]` | Provide a list of FQCN of default integrations you do not want to use. [List of default integrations](https://github.com/getsentry/sentry-php/tree/master/src/Integration).|
| `cron_monitoring_enabled` | `false` | Wether to enable [cron check ins](https://docs.sentry.io/platforms/php/crons/#upserting-cron-monitors) |
| `track_crons` | `[]` | Cron handles of crons to track with cron monitoring, [Sentry only supports 6 check-ins per minute](https://docs.sentry.io/platforms/php/crons/#rate-limits) Magento does many more. |
| `spotlight` | `false` | Enable [Spotlight](https://spotlightjs.com/) on the page |
| `spotlight_url` | - | Override the [Sidecar url](https://spotlightjs.com/sidecar/) |         
| `enable_csp_report_url` | `false` | If set to true, the report-uri will be automatically added based on the DSN. |

### Configuration for Adobe Cloud
Since Adobe Cloud doesn't allow you to add manually add content to the `env.php` file, the configuration can be done
using the "Variables" in Adobe Commerce using the following variables:

| Name                                             | Type    |
|--------------------------------------------------|---------|
| `CONFIG__SENTRY__ENVIRONMENT__ENABLED`           | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__DSN`               | string  |
| `CONFIG__SENTRY__ENVIRONMENT__MAGE_MODE_DEVELOPMENT` | string  |
| `CONFIG__SENTRY__ENVIRONMENT__ENVIRONMENT`       | string  |
| `CONFIG__SENTRY__ENVIRONMENT__MAX_BREADCRUMBS`   | integer  |
| `CONFIG__SENTRY__ENVIRONMENT__ATTACH_STACKTRACE` | boolean  |
| `CONFIG__SENTRY__ENVIRONMENT__SAMPLE_RATE`       | float  |
| `CONFIG__SENTRY__ENVIRONMENT__IGNORE_EXCEPTIONS` | JSON array of classes |
| `CONFIG__SENTRY__ENVIRONMENT__ERROR_TYPES`       | integer |
| `CONFIG__SENTRY__ENVIRONMENT__LOG_LEVEL`         | integer |
| `CONFIG__SENTRY__ENVIRONMENT__CLEAN_STACKTRACE`  | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__TRACING_ENABLED`   | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__TRACES_SAMPLE_RATE`| float |
| `CONFIG__SENTRY__ENVIRONMENT__PROFILES_SAMPLE_RATE`| float |
| `CONFIG__SENTRY__ENVIRONMENT__PERFORMANCE_TRACKING_ENABLED` | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__PERFORMANCE_TRACKING_EXCLUDED_AREAS` | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__ENABLE_LOGS`       | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__LOGGER_LOG_LEVEL`  | boolean |
| `CONFIG__SENTRY__ENVIRONMENT__JS_SDK_VERSION`    | string  |
| `CONFIG__SENTRY__ENVIRONMENT__IGNORE_JS_ERRORS`  | JSON array of error messages |

The following configuration settings can be overridden in the Magento admin. This is limited to ensure that changes to
particular config settings can only be done on server level and can't be broken by changes in the admin.

Please note, that it is not possible to use profiling within the Adobe Cloud.

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

This same thing is the case for
|                                |                                                                                     |
|--------------------------------|-------------------------------------------------------------------------------------|
| sentry_before_send             | https://docs.sentry.io/platforms/php/configuration/options/#before_send             |
| sentry_before_send_transaction | https://docs.sentry.io/platforms/php/configuration/options/#before_send_transaction |
| sentry_before_send_check_in    | https://docs.sentry.io/platforms/php/configuration/options/#before_send_check_in    |
| sentry_before_breadcrumb       | https://docs.sentry.io/platforms/php/configuration/options/#before_breadcrumb       |
| sentry_before_send_log         | https://docs.sentry.io/platforms/php/configuration/options/#before_send_log         |

## Compatibility
The module is tested on Magento version 2.4.x with sentry sdk version 4.x. feel free to fork this project or make a pull request.

## Ideas, bugs or suggestions?
Please create a [issue](https://github.com/justbetter/magento2-sentry/issues) or a [pull request](https://github.com/justbetter/magento2-sentry/pulls).

## Contributing
Contributing? Awesome! Thank you for your help improving the module!

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

Most importantly:
- When making a PR please add a description what you've added, and if relevant why.
- To save time on codestyle feedback, please run
    - `composer install`
    - `composer run codestyle`
    - `composer run analyse`

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

<a href="https://justbetter.nl" title="JustBetter">
    <img src="./.github/assets/footer.svg" alt="We’re a innovative development agency from The Netherlands.">
</a>
