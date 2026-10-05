# Loads the functions of bash-tools (yesno, log, success, error, die, end, require, read_env, debug...), which every script
# of the family uses to ask, tell and fail the same way. Sourced by the scripts, not run. Sourcing also reads the .env of the
# project (BASE_DIR, the root of the project unless the script says otherwise).
#
# The copy of this project comes first (composer, a development dependency), then the bash-tools package, then the PATH.

_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
for _helpers in "$_root/vendor/magicoli/bash-tools/bin/bash-helpers" /usr/share/bash-tools/bin/bash-helpers; do
    [[ -f "$_helpers" ]] && break
    _helpers=
done
[[ -n "$_helpers" ]] || _helpers=$(command -v bash-helpers) || {
    echo "${0##*/}: bash-helpers not found: composer install, bash-tools is a development dependency of the project" >&2
    exit 1
}
# Before bash-tools 1.0.5, bash-helpers is not made for set -e (what it ends with, read_env, returns 1 when APP_ENV is not
# set): kept for a package or a PATH copy older than the one of vendor
_errexit=
[[ $- == *e* ]] && _errexit=1
set +e
# shellcheck disable=SC1090
. "$_helpers"
[[ -z "$_errexit" ]] || set -e
unset _root _helpers _errexit
