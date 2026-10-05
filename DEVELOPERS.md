# OpenSim Helpers development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project. The config of the grid (`/etc/opensim/grids/<grid>/`) is not one of them: it is the structure the projects share, the helpers read it as they read an `includes/config.php`.

## Keep it simple

The helpers are the most shared scripts of the community: whoever wrote the original `query.php`, or uses a modified one, must understand a change without reading ten files. No framework architecture. Each script starts with one `require_once 'includes/bootstrap.php'`, a short file that loads the libraries, then the settings, then the database and the functions. The settings come from one of two places, the first that exists:

1. `includes/config.php`, constants (see `config.example.php`): the historic way, kept for the installs that have it and for the applications that embed the helpers (they provide their own);
2. the config of the grid, `/etc/opensim/grids/<grid>/helpers.ini` and the Robust config next to it (`classes/class-grid-config.php`, see `includes/helpers.example.ini`): the recommended way. Only these files are read, nothing is mixed with constants. `OPENSIM_GRID` names the grid when there are several.

A constant the settings leave out takes the default of `bootstrap.php` (a service database is the main one), so a new constant does not break an existing config. The helpers answer 503 and say why in the log when there is no config, or no database in it.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the last commit (it refuses when changes are not committed or when `composer.lock` is not up to date; `DIRTY=1` builds the last commit anyway):

- a Debian package, `opensim-helpers_<version>_all.deb`, a webroot in `/usr/share/opensim-helpers` to be served by a web server: its `vendor` folder has the third-party libraries (Twig, phpxmlrpc), and `opensim-engine` and `opensim-rest-php` are links to the packages of those names, which it depends on. It has no `includes/config.php`: the config is the `helpers.ini` of the grid in `/etc/opensim/grids/<grid>/` (made by hand from `includes/helpers.example.ini`, or by the OpenSim kit);
- a zip, `opensim-helpers-<version>.zip`: the files with a complete `vendor` folder (the engine and rest-php included, as files), to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. What is distributed is what git tracks (so what `.gitignore` ignores is not there) without what `.distignore` lists, plus the `vendor` folder composer makes without the development tools, from the repositories of `composer.json` (a path repository in development, else Packagist). The work is done on copies, the `vendor` folder of the project is not touched. The scripts are those of [build-tools](https://github.com/magicoli/build-tools) (`vendor/bin/build-tools`, a development dependency); the project keeps its own `packaging/`: the nfpm definition `opensim-helpers.yaml`, `build` (the files of the package), `siblings` (the projects of the family the Debian package gets from their own packages), `opensim-helpers.preinst`. It needs nfpm and composer. `.libignore` is another list, for `tools/install-library.sh` (the helpers installed as a library in another project).

`tests/Packaging/check` tries both in a clean container (podman) with the Debian packages of opensim-engine and opensim-rest-php (`DEPS_ENGINE=` and `DEPS_REST=` say where they are, by default the `dist` folders of the sibling projects): the home page must answer as an unprivileged user with a config made from the example. `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available. What the container needs is sent to it as a tar stream: `CONTAINER_CONNECTION=name` (a `podman system connection`) runs it on the podman of another machine, `MEMORY=` sets its memory (default 400m); both can be set in `tests/.env` (see `tests/.env.example`), read after the `.env` of the project (bash-tools `read_env`: what the files set wins over the environment of the command).

## Release

One command in each project, in the order of the family (rest-php, engine, helpers, kit), each one completely before the next:

```bash
dev/release.sh          # the whole release, after one question
dev/release.sh status   # what is done and what remains
dev/release.sh beta     # patch|minor|major|stable|dev|alpha|beta|rc|1.2.3-beta.4: see the versions in the README of build-tools
```

It needs the `.env` of the project to say where the apt repository is (`APT_REPO_DIR`, see `.env.example` of build-tools), `gh` (logged in) and `nfpm`, and says what is missing before it does anything. `dev/switch.sh dev|release` does the composer part alone: the projects of the family linked next to this one (path repositories, `@dev`), or required by version (`^` the latest version tag of each, refused when a project changed since that release).

## Shell scripts

The scripts of `dev/` are wrappers of build-tools. `tests/Packaging/check` uses the functions of [bash-tools](https://github.com/magicoli/bash-tools) to ask, tell and fail (`log`, `success`, `warning`, `die`, `end`, `yesno`, `require`, `usage`, `read_env`), loaded from `vendor/bin/bash-helpers`: write the new ones the same way, not with their own prompts and `echo`.
