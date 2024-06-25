# Changelog
## [3.7.1] - 2024-06-25
### Fixed
    * Fix CSP after Polyfill changes (https://github.com/justbetter/magento2-sentry/pull/141) thanks to https://github.com/sprankhub
## [3.7.0] - 2024-06-25
### Fixed
    * Replace polyfill CDN with fastly (https://github.com/justbetter/magento2-sentry/pull/140) thanks to https://github.com/barryvdh
### Added
    * Enable INP when tracing is enabled (https://github.com/justbetter/magento2-sentry/pull/137) thanks to https://github.com/amenk
## [3.6.0] - 2024-03-29
This release drops support for php 7.4 as it has been completely EOL for over a year. [https://www.php.net/supported-versions.php](https://www.php.net/supported-versions.php)
### Fixed
    * Check if Sentry is defined before running init (https://github.com/justbetter/magento2-sentry/pull/129) thanks to https://github.com/netzkollektiv
### Changed
    * Use property promotions (https://github.com/justbetter/magento2-sentry/pull/130) thanks to https://github.com/cirolosapio
    * Raised sentry/sdk version to 4.0+
## [3.5.2] - 2024-03-18
### Fixed
    * Fix start errors without database connection (https://github.com/justbetter/magento2-sentry/pull/125) thanks to https://github.com/fredden
## [3.5.1] - 2023-12-21
### Fixed
    * Fix getting SCOPE_STORES from incorrect ScopeInterface (https://github.com/justbetter/magento2-sentry/pull/123) thanks to https://github.com/peterjaap
    * Improve extendibility by changing self to static (https://github.com/justbetter/magento2-sentry/pull/122) thanks to https://github.com/peterjaap
## [3.5.0] - 2023-12-20
### Added
    * Added support for configuration using System Configuration (https://github.com/justbetter/magento2-sentry/pull/121) thanks to https://github.com/ArjenMiedema
## [3.4.0] - 2023-06-14
### Added
    * Added `sentry_before_send` event (https://github.com/justbetter/magento2-sentry/pull/117) thanks to https://github.com/rbnmulder
    * Added support to ignore javascript errors (https://github.com/justbetter/magento2-sentry/pull/102) thanks to https://github.com/rommelfreddy
## [3.3.1] - 2023-03-15
### Fixed
    * Fixed PHP 8.2 deprecation errors (https://github.com/justbetter/magento2-sentry/pull/114) thanks to https://github.com/peterjaap
## [3.3.0] - 2023-03-01
### Added
    * Added Session Replay functionality
## [3.2.0] - 2022-07-07
### Fixed
    * Changed addAlert to addRecord for Test error (https://github.com/justbetter/magento2-sentry/pull/98) thanks to https://github.com/peterjaap
### Added
    * Send context data top Sentry as Custom Data (https://github.com/justbetter/magento2-sentry/pull/97) thanks to https://github.com/oneserv-heuser
## [3.1.0] - 2022-06-14
### Fixed
    * Update addRecord function for Monolog 2.7+ (https://github.com/justbetter/magento2-sentry/pull/92) thanks to https://github.com/torhoehn
## [3.0.2] - 2022-06-14
### Added
    * Added sentry_before_init event to GlobalExceptionCatcher (https://github.com/justbetter/magento2-sentry/pull/90) thanks to https://github.com/peterjaap
## [3.0.1] - 2022-05-10
### Fixed
    * Fix problems with logout during product edit in admin panel when Chrome DevTools is open (https://github.com/justbetter/magento2-sentry/pull/86) thanks to https://github.com/trungpq2711
## [3.0.0] - 2022-04-13
Breaking: New version (2.0.0+) for Monolog will be required.
### Fixed
    * Fixed PHP 8.1 support with monolog 2.0.0+ (https://github.com/justbetter/magento2-sentry/pull/85) thanks to https://github.com/peterjaap
## [2.6.1] - 2022-04-13
### Changed
    * Updated constraints for monolog versions lower than 2.0.0
## [2.6.0] - 2021-04-08
### Added
    * Add option to filter the severity of ErrorExceptions being sent to Sentry
    * Add option to ignore specific Exception classes
## [2.5.4] - 2021-02-10
### Fixed
    * Fixed capital in dataHelper (https://github.com/justbetter/magento2-sentry/pull/74) thanks to https://github.com/peterjaap
## [2.5.3] - 2021-02-03
### Added
    * Add option to strip static content version and store code from urls that get sent to Sentry (https://github.com/justbetter/magento2-sentry/pull/73) thanks to https://github.com/peterjaap
## [2.5.2] - 2021-01-18
### Fixed
    * Fix monolog plugin still adding monolog as argument
## [2.5.1] - 2021-01-18
### Fixed
    * Merged PR - Use isset to check for custom_tags in context (https://github.com/justbetter/magento2-sentry/pull/71) thanks to https://github.com/matthiashamacher
## [2.5.0] - 2021-01-15
### Changed
    * Merged PR - Remove monolog class in function, add custom tag functionality (https://github.com/justbetter/magento2-sentry/pull/66) thanks to https://github.com/matthiashamacher
    This is a breaking change on SentryLog::send(), the function required monolog before, this has been removed
## [2.4.0] - 2020-12-07
### Added
    * Merged PR - Add csp_whitelist.xml (https://github.com/justbetter/magento2-sentry/pull/65) thanks to https://github.com/matthiashamacher
### Changed
    * Merged PR - Update Sentry version (https://github.com/justbetter/magento2-sentry/pull/64) thanks to https://github.com/matthiashamacher
## [2.3.2] - 2020-11-30
### Added
    * Merged PR - Adding environment to js integration (https://github.com/justbetter/magento2-sentry/pull/62) thanks to https://github.com/matthiashamacher
## [2.3.1] - 2020-07-31
### Added
    * Merged PR - Add option to enable or disable Php error tracking (https://github.com/justbetter/magento2-sentry/pull/59) thanks to https://github.com/t9toqwerty
## [2.3.0] - 2020-06-29
### Added
    * Merged PR - Added version tagging to JavaScript (https://github.com/justbetter/magento2-sentry/pull/54) thanks to https://github.com/JKetelaar
    * Added LogRocket support
### Changed
    * Better feedback if configuration prevents the module from running

## [2.2.3] - 2020-02-25
### Fixed
    * Merged PR - Release version should be string (https://github.com/justbetter/magento2-sentry/pull/49) thanks to https://github.com/DominicWatts

## [2.2.2] - 2020-02-11
### Changed
    * Merged PR - Change behavior to check of script tag can be used (https://github.com/justbetter/magento2-sentry/pull/48) thanks to https://github.com/adamj88
    * Updated changelog format to folow more closely to https://keepachangelog.com/

## [2.2.1] - 2019-12-12
### Changed
    * Merged PR - fix for requirements sdk (https://github.com/justbetter/magento2-sentry/pull/42) thanks to https://github.com/jupiterhs

## [2.2.0] - 2019-12-12
### Changed
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/37) thanks to https://github.com/DominicWatts
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/38) thanks to https://github.com/matthiashamacher

## [2.1.0] - 2019-11-22
### Changed
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/33) thanks to https://github.com/DominicWatts
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/35) thanks to https://github.com/peterjaap
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/36) thanks to https://github.com/peterjaap
    * Refactor of proxy classes in di.xml
### Fixed
    * Fixed all PHPcs warnings and errors

## [2.0.0] - 2019-11-19
### Changed
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/29) thanks to https://github.com/michielgerritsen
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/26) thanks to https://github.com/JosephMaxwell
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/27) thanks to https://github.com/DominicWatts

## [0.8.0] - 2019-08-29
### Changed
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/24) thanks to https://github.com/kyriog
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/22) thanks to https://github.com/fredden
    * Merged PR (https://github.com/justbetter/magento2-sentry/pull/21) thanks to https://github.com/erikhansen

## [0.7.2] - 2019-06-19
### Changed
    * Reverted async attribute

## [0.7.1] - 2019-06-19
### Added
    * Added async and crossorigin attribute to script tags

## [0.7.0] - 2019-04-23
### Fixed
    * Merged pull request avoiding magento crash (https://github.com/justbetter/magento2-sentry/pull/19)

## [0.6.2] - 2019-04-05
### Fixed
    * Fixed issues useing referenceBlock when adding scripts (https://github.com/justbetter/magento2-sentry/issues/18)

## [0.6.1] - 2019-02-14
### Added
    * Added missing files of PR 13 (https://github.com/justbetter/magento2-sentry/pull/16)

## [0.6.0] - 2019-02-14
### Added
    * Added Sentry script tag to catch javascript errors (https://github.com/justbetter/magento2-sentry/pull/13)

## [0.5.1] - 2019-02-13
### Added
    * Support for setting environment (https://github.com/justbetter/magento2-sentry/pull/12)

## [0.5.0] - 2018-12-07
### Added
    * Send extra parameters to sentry (https://github.com/justbetter/magento2-sentry/issues/11)
    * Added Magento 2.3.x support & dropped 2.1.x support
### Fixed
    * Fixed area code not set or already set

## [0.4.2] - 2018-10-17
### Fixed
    * Bugfix with area code already set - removed area code from constructor with causes problems at random cases. (https://github.com/justbetter/magento2-sentry/issues/10)
### Changed
    * Removed info level - this log level is not useable in sentry.

## [0.4.1] - 2018-08-15
### Fixed
    * Removed plugin in di.xml to prevent fatal crash on storefrontend
    * Restored preference in di.xml

## [0.4.0] - 2018-08-10
### Added
    * Added user context
    * Added message context
    * Added extra magento store parameters
### Changed
    * Refactor of overwrite to plugin with monolog
    * Refactor of ExceptionCatcher to use same SentryLogger
    * Refactor a lot of code
    * PSR2 compliance
### Fixed
    * Bugfix area code not set

## [0.2.1] - 2018-04-30
### Changed
    * downgraded requirement monolog for magento 2.1.x

## [0.2.0] - 2018-04-30
### Added
    * Feature request to test sentry in development mode (https://github.com/justbetter/magento2-sentry/issues/4)
    * Added mage deploy mode to sentry in context_tags

## [0.1.0] - 2018-04-17
### Added
    * Feature request for sending test events (https://github.com/justbetter/magento2-sentry/issues/3)
    * Added ACL roles for editing sentry config

## [0.0.6] - 2018-03-25
### Fixed
    * Bugfix wrong file commit

## [0.0.5] - 2018-03-17
### Added
    * Initial commit module - basic working module
