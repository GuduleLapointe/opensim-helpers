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

## Description

Collection of PHP scripts to enable OpenSimulator features that are not implemented in the core, like search, currency, events in OpenSimulator grids (see Features below).

This library can be used as-is, or integrated into another project.

Some projects based on this library:

- [w4os (WordPress plugin)](https://w4os.org)
- [2do.directory](https://2do.directory)
- [OpenSim Kit (Linux OpenSimulator framework)](https://github.com/GuduleLapointe/opensim-kit)

## Requirements

PHP 8.2 or newer, with the `curl`, `dom`, `filter`, `gettext`, `intl`, `json`, `mbstring`, `pdo`, `session` and `simplexml` extensions, plus those of [opensim-engine](https://github.com/magicoli/opensim-engine). `imagick` is needed by `textgen.php` only.

On Debian and Ubuntu: `sudo apt install php-cli php-curl php-intl php-mbstring php-mysql php-xml`, and `php-imagick` for `textgen.php`.

The `xmlrpc_*` functions, needed by OpenSimulator for the search, the currencies and the land exchanges, are not an extension to install: `includes/xmlrpc-polyfill.php` provides them on top of `phpxmlrpc/phpxmlrpc`.

## URLs

`index.php` is the single entry: a web server that sends it every URL it has no file for serves the helpers at any URL. The scripts answer under any prefix (`/helpers/query.php`), the services at the paths set by `[Urls]` in `helpers.ini` (or `OPENSIM_ROUTES` in `config.php`): `/search` for `query.php`, `/guide` for `guide.php`..., and two pages, `/` (home) and `/welcome` (the splash page of the viewer), are rendered from the Twig templates of `templates/twig/pages`. `OPENSIM_TEMPLATES_DIR` gives a folder of templates that replace them. The logo is `assets/logos/logo.svg` or `.png`, or `OPENSIM_GRID_LOGO_URL`.

## Configuration

The scripts read their settings from the config of the grid, `/etc/opensim/grids/<grid>/helpers.ini` (the folder of the grid, where its Robust config is when the OpenSim kit made it). Copy `includes/helpers.example.ini` there and edit it: the grid (name, login URI, web URL), its database, the currency, the message of the day. The web server user must be able to read it (mode 640, group `www-data`), it holds the password of the database. With several grids on a machine, the web server gives the nick of the one to serve in `OPENSIM_GRID`.

What the file leaves out takes a default, only a database is required: without one the helpers answer 503 and say why in the web server log.

The historic config still works: constants in `includes/config.php` (copy `includes/config.example.php`). When that file exists, it is the only config read.

An application that embeds the helpers (a WordPress plugin, a management tool) provides its own `includes/config.php`, which defines the same constants from the settings it manages. Nothing else is needed: the helpers work with any `config.php` that defines them.
