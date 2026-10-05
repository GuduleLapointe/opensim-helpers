#!/usr/bin/env bash
# Builds what this project distributes into dist/, from the committed tree (commit first: the version carries the hash of
# HEAD): the Debian package (nfpm) and the zip. Calls what packaging/ defines.
#
#   dev/build.sh            every format
#   dev/build.sh deb zip    the formats named

set -e
cd "$(dirname "$0")/.."
source dev/lib.sh

# A build is made from the last commit and from composer.lock: both are checked, not left to be remembered
if [[ -z "${DIRTY:-}" && -n "$(git status --porcelain --untracked-files=no)" ]]; then
    die "commit your changes first, a build is made from the last commit (DIRTY=1 builds it anyway):
$(git status --short --untracked-files=no)"
fi
if ! composer validate --no-check-publish --no-check-all --no-interaction >/dev/null 2>&1; then
    die "composer.lock is not up to date with composer.json (composer update the packages that changed, commit):
$(composer validate --no-check-publish --no-check-all --no-interaction 2>&1 || true)"
fi
# (the links to the folders git ignores, which the release makes in its own folder, are not files of the project)
untracked=$(git ls-files --others --exclude-standard | while IFS= read -r file; do [[ -L "$file" ]] || echo "$file"; done)
[[ -z "$untracked" ]] || log "note: files git does not track are not in the build: $(tr '\n' ' ' <<<"$untracked")"

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
            die 2 "no format $format (deb, zip)"
            ;;
    esac
done

success "Built from the commit $(git rev-parse --short HEAD), ${DIRTY:+with uncommitted changes, }\"$(git log -1 --format=%s)\"; in a version, g is for git and the hash follows it."
