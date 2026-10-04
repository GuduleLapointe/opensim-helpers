# OpenSim Helpers development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the last commit (it refuses when changes are not committed or when `composer.lock` is not up to date; `DIRTY=1` builds the last commit anyway):

- a Debian package, `opensim-helpers_<version>_all.deb`, a webroot in `/usr/share/opensim-helpers` to be served by a web server: its `vendor` folder has the third-party libraries (Twig, phpxmlrpc), and `opensim-engine` and `opensim-rest-php` are links to the packages of those names, which it depends on. It has no `includes/config.php`: that file is made by hand from `includes/config.example.php`, or provided by what installs the helpers;
- a zip, `opensim-helpers-<version>.zip`: the files with a complete `vendor` folder (the engine and rest-php included, as files), to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. What is distributed is what git tracks (so what `.gitignore` ignores is not there) without what `.distignore` lists, plus the `vendor` folder composer makes without the development tools, from the repositories of `composer.json` (a path repository in development, else Packagist). The work is done on copies, the `vendor` folder of the project is not touched. The scripts are in `packaging/`: `version`, `stage` (the files and the vendor folder), `build`, `zip`, `siblings` (the projects of the family the Debian package gets from their own packages), and the nfpm definition `opensim-helpers.yaml`. It needs nfpm and composer. `.libignore` is another list, for `tools/install-library.sh` (the helpers installed as a library in another project).

`tests/Packaging/check` tries both in a clean container (podman) with the Debian packages of opensim-engine and opensim-rest-php (`DEPS_ENGINE=` and `DEPS_REST=` say where they are, by default the `dist` folders of the sibling projects): the home page must answer as an unprivileged user with a config made from the example. `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available.
