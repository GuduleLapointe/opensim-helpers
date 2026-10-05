#!/usr/bin/env bash
# Releases this project, from where it stands to the end (build-tools release)

tool="$(dirname "$0")/../vendor/bin/build-tools"
[[ -x "$tool" ]] || { echo "build-tools is not installed: composer install" >&2; exit 1; }
exec "$tool" release "$@"
