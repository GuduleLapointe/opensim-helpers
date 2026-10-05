#!/usr/bin/env bash
# Builds the Debian package and the zip into dist/ from the last commit (build-tools build)

tool="$(dirname "$0")/../vendor/bin/build-tools"
[[ -x "$tool" ]] || { echo "build-tools is not installed: composer install" >&2; exit 1; }
exec "$tool" build "$@"
