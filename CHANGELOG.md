## Changelog

### 3.0.0-beta.2

- new `includes/opensim-kit-config.php` is the `config.php`, keeping the constants of the installation read by the engine from `opensim.conf`
- fix(xmlrpc): xmlrpc_encode returns the XML like the extension
- fix(xmlrpc): serve methods with the signature of the extension
- docs: use with the OpenSim kit
- chore(deps): lock the engine with the OAR class
- chore(composer): lock from packagist again
- chore(composer): lock on dev-opensim-kit of engine and rest-php
- chore: update formatting rules, reindent composer.json

### 3.0.0-beta.1

First beta of the 3.0 helpers, on the engine and the REST library of the OpenSim kit.

- new the engine (`magicoli/opensim-engine`) and the REST client (`magicoli/opensim-rest-php`) are composer packages; the legacy composer tasks are moved in `legacy`
- new the XML-RPC polyfill uses `phpxmlrpc/phpxmlrpc`, and a Laravel-backed configuration polyfill serves the legacy helpers
- new `get_grid_info` accepts an external grid (cached, fetched once per grid in the same HTTP request); `osdb_cache_get()` and `osdb_cache_set()` give a persistent cache
- new `directory_info.php` gives the statistics of the search directory, `textgen.php` creates a dynamic texture from a URL, `register.php` stops at once when the search database is not connected
- new `opensim_sanitize_uri()`, and `includes/config.php` is included when it exists
- update PHP 8.2 is the minimum (composer platform 8.2.0), the code runs clean on PHP 8.2 to 8.5, the required extensions are declared
- update the parcels use only the Laravel logic, and an exporter saves to the OpenSim database: the main code is free of the historical discrepancies of that database
- update the code is formatted from `.editorconfig` and `.prettierrc.json` (single quotes, PSR-12), `${var}` syntax is replaced
- update tests: a pest suite checks the PHP minimum and compatibility
- fix `opensim_format_tp()` hop links (a position is not mandatory, no scheme in a text link) and `opensim_link_region()` no longer crashes with incomplete arguments
- fix the gatekeeper address has its scheme, `parser.php` does not warn when a region is offline, `eventsparser.php` does not crash on an undefined variable
- fix an error notification displayed three times when a place search has no result, the XML error (expat error code 3) for empty search results, duplicate `TPLINK*` constants, wrong URLs in the cron example
