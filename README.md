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

## With the OpenSim kit

The [OpenSim kit](https://github.com/GuduleLapointe/opensim-kit) installs and configures them for a grid, nothing is to be edited by hand.

```bash
sudo apt install opensim-helpers          # the scripts, in /usr/share/opensim-helpers
sudo opensim setup                        # a grid: the setup asks whether the helpers serve its economy and its search
```

The setup writes the `helpers.ini` of the grid (`/etc/opensim/grids/<grid>/helpers.ini`), which is all the helpers read: the grid, its web URL, the database (the one of Robust unless another one is given for a service), the path of the helpers on the web site (`/helpers` by default, any path you already use works) and, under `[Urls]`, the path of a service that has its own (`search = "/search"`, `guide = "/guide"`...). The Robust config of the grid tells the viewers where the services are (`economy`, `SearchURL`, `DestinationGuide`, `MessageUrl`).

The web server is yours: the setup writes the configuration for Caddy, nginx and Apache in `/etc/opensim/grids/<grid>/web/` (`<grid>.caddyfile`, `<grid>-nginx.conf`, `<grid>-apache.conf`), to include in the site of the grid. `opensim web <grid>` tells where each service is, `opensim web <grid> check` asks each one, `opensim web <grid> snippet caddy|nginx|apache` writes the configuration again.

With several grids on a machine, the virtual host of each one sets `OPENSIM_GRID` (the configuration files do), and the helpers read the settings of that grid.

The message of the day (`motd.php`) is the `motd` of `[Helpers]` in the `helpers.ini` of the grid, `\n` for a new line and `<USERNAME>` for the name of the avatar.

More in the [installation guide of the kit](https://github.com/GuduleLapointe/opensim-kit/blob/opensim-kit/INSTALLATION.md).

