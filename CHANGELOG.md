## Changelog

### 3.0.0-beta.5

- update: `dev/` runs [build-tools](https://github.com/magicoli/build-tools) instead of its own copy of the scripts
- update: [bash-tools](https://github.com/magicoli/bash-tools) 1.0.7 in require-dev, the scripts use its functions

### 3.0.0-beta.4

- new: `dev/release.sh` makes the whole release, `dev/switch.sh` the composer part
- new: `dev/build.sh` makes the Debian package and the zip
- update: the scripts load `includes/bootstrap.php` (libraries, config, database, functions), `config.php` only defines constants, a missing one is a 503
- fix: the xmlrpc polyfill is loaded only without the extension, a server that has it got a 500
- new: the config of the grid, `/etc/opensim/grids/<grid>/helpers.ini`, is read when there is no `includes/config.php`
- update: `directory_info.php` and `classes/init.php` load the bootstrap
- update: a constant the config leaves out takes a default, a service database the main one
- fix: the helpers refuse to run without a database, and say so in the log
- new: `index.php` router, `OPENSIM_ROUTES` sets the URL of each service
- new: home and splash pages, Twig templates

### 3.0.0-beta.3

- new: `OPENSIM_MOTD` (`config.php`) is the message of the day `motd.php` gives
- fix(xmlrpc): xmlrpc_encode returns the XML like the extension
- fix(xmlrpc): serve methods with the signature of the extension
- chore(deps): lock the engine with the OAR class
- chore(composer): lock from packagist again
- chore(composer): lock on dev-opensim-kit of engine and rest-php
- chore: update formatting rules, reindent composer.json

### 3.0.0-beta.1

First beta of the 3.0 helpers, on the engine and the REST library.

- new: engine and REST client are composer packages, legacy composer tasks moved to `legacy`
- new: XML-RPC polyfill on `phpxmlrpc/phpxmlrpc`, Laravel-backed config polyfill for legacy helpers
- new: `get_grid_info` accepts an external grid (cached), persistent cache `osdb_cache_get/set()`
- new: `directory_info.php`, `textgen.php`; `register.php` stops when the search database is down
- new `opensim_sanitize_uri()`, and `includes/config.php` is included when it exists
- update: PHP 8.2 minimum, runs clean on 8.2 to 8.5, extensions declared
- update: parcels use only the Laravel logic, an exporter saves to the OpenSim database
- update: code formatted from `.editorconfig` and `.prettierrc.json`, `${var}` syntax replaced
- update tests: a pest suite checks the PHP minimum and compatibility
- fix: `opensim_format_tp()` hop links, `opensim_link_region()` crash on incomplete arguments
- fix: gatekeeper address has its scheme, warnings and crash in `parser.php`, `eventsparser.php`
- fix: error shown 3 times on an empty place search, expat error 3, duplicate `TPLINK*`, cron URLs
