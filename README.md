# OpenSimulator Helpers

![Stable](https://img.shields.io/github/release/GuduleLapointe/opensim-helpers?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/GuduleLapointe/opensim-helpers?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/GuduleLapointe/opensim-helpers/latest?label=dev)
![PHP](https://img.shields.io/badge/PHP-8.2+-7884bf)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/GuduleLapointe/opensim-helpers/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

This branch is in development, it is intended for integration in another project.

Use master or 3.x branch instead for up-to-date code.

<https://github.com/magicoli/opensim-helpers>

## Requirements

PHP 8.2 or newer, with the `curl`, `dom`, `filter`, `gettext`, `intl`, `json`, `mbstring`, `pdo`, `session` and `simplexml` extensions, plus those of [opensim-engine](https://github.com/magicoli/opensim-engine). `imagick` is needed by `textgen.php` only.

On Debian and Ubuntu: `sudo apt install php-cli php-curl php-intl php-mbstring php-mysql php-xml`, and `php-imagick` for `textgen.php`.

The `xmlrpc_*` functions, needed by OpenSimulator for the search, the currencies and the land exchanges, are not an extension to install: `includes/xmlrpc-polyfill.php` provides them on top of `phpxmlrpc/phpxmlrpc`.

## Configuration

The scripts read their settings from the constants `includes/config.php` defines. Copy `includes/config.example.php` to `includes/config.php` and edit it: the grid (name, login URI), its databases, the currency, the message of the day (`OPENSIM_MOTD`).

An application that embeds the helpers (a WordPress plugin, a management tool) provides its own `includes/config.php`, which defines the same constants from the settings it manages. Nothing else is needed: the helpers work with any `config.php` that defines them.

