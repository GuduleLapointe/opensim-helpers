#!/usr/bin/env bash
# Puts the projects of the family in development or release mode in composer.json (build-tools switch dev|release)

tool="$(dirname "$0")/../vendor/bin/build-tools"
[[ -x "$tool" ]] || { echo "build-tools is not installed: composer install" >&2; exit 1; }
exec "$tool" switch "$@"
