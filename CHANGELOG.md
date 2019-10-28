# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.0] - 2019-10-28
### Changed
- Replace predis/predis with `colinmollenhour/credis`, that supports `PhpRedis` module for high load.

## [1.5.0] - 2019-10-25
### Changed
- Replace predis/predis with `colinmollenhour/credis`, that supports `PhpRedis` module for high load.

## [2.0.2] - 2019-10-23
### Changed
- Fix argument value partial escaping

## [1.4.8] - 2019-10-23
### Changed
- Fix argument value partial escaping

## [2.0.1] - 2019-10-18
### Changed
- Fix float parameter handling 

## [1.4.7] - 2019-10-18
### Changed
- Fix float parameter handling 

## [2.0.0] - 2019-07-29
### Changed
- Magento 2.3.2 adoption
- Fixes

## [1.4.3] - 2019-07-26
### Added
- verbose error messages for Varnish flush requests

## [1.3.0] - 2019-05-23
### Added
-  Event observer for`bin/magento cache:flush` to trigger persisted query varnish storage flush (happens also on 
setup:upgrade)
- `bin/magento scandipwa:pq:flush` - command for flushing query document (redis) storage (does not happen on 
setup:upgrade)

### Removed
- `persisted_query` cache is unlisted in CLI and Admin cache control menu

### Changed
- File restructuring improving readability
- Updating README.md & CHANGELOG.md
- Update Magento module version to fit release tag
- Minor CS improvements

## [1.2.0] - 2019-05-07
### Added
- flush option for persisted query redis
- cache control for PQ and PQ responses
- flushing logic for varnish and redis

### Removed
- version tag in composer.json  

### Changed    
- Update README.md    
- improve code styling and comments

## [1.1.1] - 2019-03-20
### Changed
- `--pq-scheme` is not setting `tcp` by default.
- Move CHANGELOG.md to the root of the module
- README.md

## [1.1.0] - 2019-03-20
### Added
- Added custom flags to `setup:config:set` CLI command

### Changed
- Changed README.md 

## [1.0.0] - 2019-03-08
### Added
- Initial commit
- predis/predis as dependency
- persisted query support
- Magento 2 module registration: ScandiPWA_PersistedQuery
- `Plugin\PersistedQuery` registered for `Magento\GraphQl\Controller\GraphQl`
- `Plugin\PersistedQuery` registered for `ScandiPWA\GraphQl\Controller\GraphQl`
