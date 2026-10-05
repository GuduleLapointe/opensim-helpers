#!/usr/bin/env bash
# The steps of a release of this project, one command each. The projects of the family are released in the order of
# their dependencies (rest-php, engine, helpers, kit), each one completely before the next: the next one requires the
# version just published.
#
#   dev/release.sh prepare [VERSION]   the release commit: .version (the development version without -dev, or VERSION),
#                                      the family required by version (dev/switch.sh release), CHANGELOG.md
#   dev/release.sh publish             tag and push the release commit (to RELEASE_REMOTE, github by default), then a
#                                      clean build of the tagged commit
#   dev/release.sh next [VERSION]      after the publication: the next development version, the family linked again
#                                      (dev/switch.sh dev), a new Unreleased section in CHANGELOG.md

set -e
cd "$(dirname "$0")/.."

step=${1:-}
branch=$(git branch --show-current)
remote=${RELEASE_REMOTE:-github}

die() {
    echo "dev/release.sh: $*" >&2
    exit 1
}
clean_tree() {
    [[ -z "$(git status --porcelain --untracked-files=no)" ]] || die "commit or stash your changes first:
$(git status --short --untracked-files=no)"
}
version() {
    tr -d '[:space:]' <.version
}
# The tags of this project start with v or not, as the last one did
tag_name() {
    local last
    last=$(git for-each-ref --sort=-creatordate --count=1 --format='%(refname:short)' refs/tags)
    [[ $last == v* ]] && echo "v$1" || echo "$1"
}
# A step that fails leaves the files as they were (the tree was clean when it began)
restore() {
    git checkout -q -- .version CHANGELOG.md composer.json composer.lock 2>/dev/null || true
}
# The lines of a section of CHANGELOG.md (### title), without the title
changelog_section() {
    awk -v title="### $1" '$0 == title {on = 1; next} /^### / {on = 0} on' CHANGELOG.md
}

case $step in
    prepare)
        clean_tree
        current=$(version)
        new=${2:-${current%-dev}}
        [[ -z "$(git tag -l "$new" "v$new")" ]] || die "the tag $new exists already"
        grep -qx '### Unreleased' CHANGELOG.md || die "CHANGELOG.md has no ### Unreleased section"
        [[ -n "$(changelog_section Unreleased | tr -d '[:space:]')" ]] || die "the Unreleased section of CHANGELOG.md is empty"

        trap restore ERR
        echo "$new" >.version
        dev/switch.sh release
        awk -v title="### $new" '$0 == "### Unreleased" && !done {print title; done = 1; next} {print}' CHANGELOG.md >CHANGELOG.md.new
        mv CHANGELOG.md.new CHANGELOG.md

        git add .version CHANGELOG.md
        [[ ! -f composer.json ]] || git add composer.json
        [[ ! -f composer.lock ]] || git add composer.lock
        git commit -q -m "chore(release): $new"
        trap - ERR
        echo "Release commit for $new: $(git log --oneline -1)"
        echo "Look at it, then: dev/release.sh publish"
        ;;
    publish)
        clean_tree
        new=$(version)
        [[ $(git log -1 --format=%s) == "chore(release): $new" ]] || die "HEAD is not the release commit of $new (dev/release.sh prepare)"
        if grep -q '"type": "path"' composer.json 2>/dev/null; then
            die "composer.json still has local projects (dev/switch.sh release)"
        fi
        tag=$(tag_name "$new")
        [[ -z "$(git tag -l "$tag")" ]] || die "the tag $tag exists already"
        git remote get-url "$remote" >/dev/null 2>&1 || die "no remote named $remote (RELEASE_REMOTE=name)"

        # The message of the tag is the notes of the release: the section of the changelog
        git tag -a "$tag" -m "$new

$(changelog_section "$new")"
        git push "$remote" "$branch" "$tag"
        dev/build.sh
        echo "Published $tag to $remote. Next: dev/release.sh next"
        ;;
    next)
        clean_tree
        current=$(version)
        [[ $current != *-dev ]] || die "$current is a development version, there is nothing to continue from"
        [[ -n "$(git tag -l "$current" "v$current")" ]] || die "$current is not released (no tag): dev/release.sh prepare, then publish"
        if [[ -n "${2:-}" ]]; then
            new=$2
        elif [[ $current =~ ^(.*-[a-z]+\.)([0-9]+)$ ]]; then
            # 3.0.0-beta.4 -> 3.0.0-beta.5
            new=${BASH_REMATCH[1]}$((BASH_REMATCH[2] + 1))
        elif [[ $current =~ ^([0-9]+\.[0-9]+\.)([0-9]+)$ ]]; then
            # 1.1.2 -> 1.1.3-dev
            new=${BASH_REMATCH[1]}$((BASH_REMATCH[2] + 1))-dev
        else
            die "cannot guess the version after $current: dev/release.sh next VERSION"
        fi

        trap restore ERR
        echo "$new" >.version
        dev/switch.sh dev
        awk 'BEGIN {done = 0} {print} /^## Changelog/ && !done {print ""; print "### Unreleased"; done = 1}' CHANGELOG.md >CHANGELOG.md.new
        mv CHANGELOG.md.new CHANGELOG.md

        git add .version CHANGELOG.md
        [[ ! -f composer.json ]] || git add composer.json
        [[ ! -f composer.lock ]] || git add composer.lock
        git commit -q -m "chore(version): $new, the family linked again"
        trap - ERR
        echo "Now at $new: $(git log --oneline -1)"
        ;;
    *)
        echo "usage: dev/release.sh prepare [VERSION] | publish | next [VERSION]" >&2
        exit 2
        ;;
esac
