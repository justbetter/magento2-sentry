# Changelog
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
