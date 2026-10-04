#!/usr/bin/env bash
# Builds what this project distributes into dist/, from the committed tree (commit first: the version carries the hash of
# HEAD): the Debian package (nfpm) and the zip. Calls what packaging/ defines.
#
#   dev/build.sh            every format
#   dev/build.sh deb zip    the formats named

set -e
cd "$(dirname "$0")/.."

# A build is made from the last commit and from composer.lock: both are checked, not left to be remembered
if [[ -z "${DIRTY:-}" && -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "dev/build.sh: commit your changes first, a build is made from the last commit (DIRTY=1 builds it anyway):" >&2
    git status --short --untracked-files=no >&2
    exit 1
fi
if ! composer validate --no-check-publish --no-check-all --no-interaction >/dev/null 2>&1; then
    echo "dev/build.sh: composer.lock is not up to date with composer.json (composer update the packages that changed, commit):" >&2
    composer validate --no-check-publish --no-check-all --no-interaction >&2 || true
    exit 1
fi
untracked=$(git ls-files --others --exclude-standard)
[[ -z "$untracked" ]] || echo "note: files git does not track are not in the build: $(tr '\n' ' ' <<<"$untracked")" >&2

formats=${*:-deb zip}
mkdir -p dist

for format in $formats; do
    case $format in
        deb)
            # dist/ holds the build just made, not the former ones
            rm -f dist/opensim-helpers_*.deb
            packaging/build
            set -a
            # shellcheck disable=SC1091
            source build/packaging.env
            set +a
            nfpm package --config packaging/opensim-helpers.yaml --packager deb --target dist/
            ;;
        zip)
            rm -f dist/opensim-helpers-*.zip
            packaging/zip
            ;;
        *)
            echo "dev/build.sh: no format $format (deb, zip)" >&2
            exit 2
            ;;
    esac
done

echo "Built from the commit $(git rev-parse --short HEAD), ${DIRTY:+with uncommitted changes, }\"$(git log -1 --format=%s)\"; in a version, g is for git and the hash follows it."
