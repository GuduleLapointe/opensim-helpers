# OpenSimulator Helpers

This branch is out of sync, it is a snapshot with some custom adaptations intended for integration in another project.

Use master or 3.x branch instead for up-to-date code.

<https://github.com/magicoli/opensim-helpers>

## Requirements

PHP 8.2 or newer, with the `curl`, `dom`, `filter`, `gettext`, `intl`, `json`, `mbstring`, `pdo`, `session` and `simplexml` extensions, plus those of [opensim-engine](https://github.com/magicoli/opensim-engine). `imagick` is needed by `textgen.php` only.

On Debian and Ubuntu: `sudo apt install php-cli php-curl php-intl php-mbstring php-mysql php-xml`, and `php-imagick` for `textgen.php`.

The `xmlrpc_*` functions, needed by OpenSimulator for the search, the currencies and the land exchanges, are not an extension to install: `includes/xmlrpc-polyfill.php` provides them on top of `phpxmlrpc/phpxmlrpc`.
