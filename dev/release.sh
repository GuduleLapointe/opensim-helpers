#!/usr/bin/env bash
# Releases this project, from where it stands to the end, whatever was already done: after an interruption, run it
# again, it does what remains. The projects of the family are released in the order of their dependencies (rest-php,
# engine, helpers, kit): each one needs the previous one published.
#
#   dev/release.sh [VERSION]   the whole release, after one question: the release commit (.version, the family required
#                              by version, CHANGELOG; its message is v<version> then the changelog, verbatim, like the
#                              message of the tag), the tag, the push to RELEASE_REMOTE (github), the zip, the
#                              publication (apt repository, GitHub release with the Debian packages and the zip), then the
#                              next development version, pushed
#   dev/release.sh status      what is done and what remains
#
# VERSION is the version to release, by default the development version without -dev, or the version of .version when it
# has no -dev. RELEASE_YES=1 answers yes to the question.

set -e
cd "$(dirname "$0")/.."
source dev/lib.sh

remote=${RELEASE_REMOTE:-github}
gh=${GH:-gh}
branch=$(git branch --show-current)
project=$(basename "$PWD")

version() {
    tr -d '[:space:]' <.version
}
# The tags of this project start with v or not, as the last one did
tag_name() {
    local last
    last=$(git for-each-ref --sort=-creatordate --count=1 --format='%(refname:short)' refs/tags)
    [[ $last == v* ]] && echo "v$1" || echo "$1"
}
# The lines of a section of CHANGELOG.md (### title), verbatim, without the title and the blank lines around
changelog_section() {
    awk -v title="### $1" '
        $0 == title {on = 1; next}
        /^### / {on = 0}
        on {lines[++n] = $0}
        END {
            first = 1
            while (first <= n && lines[first] == "") first++
            last = n
            while (last >= first && lines[last] == "") last--
            for (i = first; i <= last; i++) print lines[i]
        }' CHANGELOG.md
}
# owner/name of the GitHub repository of the remote
github_repo() {
    [[ -z "${RELEASE_GITHUB_REPO:-}" ]] || {
        echo "$RELEASE_GITHUB_REPO"
        return
    }
    git remote get-url "$remote" | sed -nE 's#^(git@github\.com:|https?://([^@/]+@)?github\.com/|ssh://git@github\.com/)(.+)$#\3#p' | sed 's/\.git$//'
}
apt_package() {
    local found
    found=$(command -v apt-package || true)
    for candidate in /opt/apt-repo/bin/apt-package /opt/magic/apt-repo/bin/apt-package; do
        [[ -n "$found" || ! -x "$candidate" ]] || found=$candidate
    done
    echo "${APT_PACKAGE:-$found}"
}
# The folder of the apt repository (apt-package is in its bin/), and the key its packages are signed with
apt_repo_dir() {
    local tool
    tool=$(apt_package)
    [[ -z "$tool" ]] || (cd "$(dirname "$(realpath "$tool" 2>/dev/null || echo "$tool")")/.." && pwd)
}
signing_key() {
    awk '$1 == "SignWith:" {print $2; exit}' "$(apt_repo_dir)/conf/distributions" 2>/dev/null || true
}
# A step that fails leaves the files as they were
restore() {
    git checkout -q -- .version CHANGELOG.md composer.json composer.lock 2>/dev/null || true
}
plan_item() { # done|todo, what
    if [[ $1 == done ]]; then success "done: $2"; else log "to do: $2"; fi
}

[[ -z "$(git status --porcelain --untracked-files=no)" ]] || die "commit or stash your changes first. A release leaves nothing uncommitted, these were made by hand:
$(git status --short --untracked-files=no)"

current=$(version)
if [[ -n "${1:-}" && $1 != status ]]; then
    new=$1
elif [[ $current == *-dev ]]; then
    new=${current%-dev}
else
    new=$current
fi
tag=$(tag_name "$new")
next=
if [[ -n "${2:-}" ]]; then
    next=$2
elif [[ $new =~ ^(.*-[a-z]+\.)([0-9]+)$ ]]; then
    # 3.0.0-beta.4 -> 3.0.0-beta.5
    next=${BASH_REMATCH[1]}$((BASH_REMATCH[2] + 1))
elif [[ $new =~ ^([0-9]+\.[0-9]+\.)([0-9]+)$ ]]; then
    # 1.1.2 -> 1.1.3-dev
    next=${BASH_REMATCH[1]}$((BASH_REMATCH[2] + 1))-dev
else
    die "cannot guess the version after $new: dev/release.sh $new NEXT_VERSION"
fi

# Where the release stands
prepared=0
tag_local=0
tag_remote=0
published=0
git rev-parse -q --verify "refs/tags/$tag" >/dev/null && tag_local=1
[[ $tag_local == 1 || $(git log -1 --format=%s) == "v$new" ]] && prepared=1
[[ -z "$(git ls-remote --tags "$remote" "refs/tags/$tag" 2>/dev/null)" ]] || tag_remote=1
repo=$(github_repo)
if [[ $tag_remote == 1 && -n "$repo" ]] && "$gh" release view "$tag" -R "$repo" --json assets -q '.assets[].name' 2>/dev/null | grep -q '\.zip$'; then
    published=1
fi

# A project with nothing under Unreleased and no release in progress has nothing to release: not an error
if [[ $prepared == 0 ]] && ! { grep -qx '### Unreleased' CHANGELOG.md && [[ -n "$(changelog_section Unreleased | tr -d '[:space:]')" ]]; }; then
    last=$(git describe --tags --abbrev=0 --match '[0-9]*' --match 'v[0-9]*' 2>/dev/null || echo none)
    end 0 "$project: nothing to release, CHANGELOG.md has nothing under Unreleased (last release: $last). Write what changed, then run again."
fi

show_plan() {
    log "$project $new (tag $tag), then $next"
    [[ $prepared == 1 ]] && plan_item done "release commit" || plan_item todo "release commit: .version, the family by version, CHANGELOG"
    [[ $tag_local == 1 ]] && plan_item done "tag $tag" || plan_item todo "tag $tag"
    [[ $tag_remote == 1 ]] && plan_item done "pushed to $remote" || plan_item todo "push to $remote"
    [[ $published == 1 ]] && plan_item done "published (GitHub release with the zip)" ||
        plan_item todo "zip, Debian packages, publication: apt repository, GitHub release with the packages and the zip"
    [[ $tag_local == 1 && $current != "$new" ]] && plan_item done "next version $current" || plan_item todo "next version $next, the family linked again, pushed"
}

if [[ ${1:-} == status ]]; then
    show_plan
    exit 0
fi

# What is needed, before anything is done
[[ -n "$repo" ]] || die "the remote $remote is not a GitHub repository (RELEASE_REMOTE=name)"
require "$gh" nfpm composer php
"$gh" auth status >/dev/null 2>&1 || die "gh is not logged in (gh auth login): the GitHub release needs it"
[[ -n "$(apt_package)" ]] || die "apt-package is not installed (the apt-repo project)"

show_plan
[[ -n "${RELEASE_YES:-}" ]] || yesno "Go on?" || die "stopped, nothing done"

# The packages are signed with a key whose passphrase is asked: asked now, while you are here and before anything is
# pushed, and kept by the agent for the publication (a prompt left alone times out, and the publication stops half done)
key=
if [[ $published == 0 ]]; then
    key=$(signing_key)
    if [[ -n "$key" ]]; then
        log "Signing key $key: give its passphrase now, the publication needs it"
        echo "release $project $new" | gpg --local-user "$key" --clearsign >/dev/null || die "cannot sign with the key $key (gpg): the packages could not be published"
    fi
fi

if [[ $prepared == 0 ]]; then
    [[ -z "$(git tag -l "$new" "v$new")" ]] || die "the tag $new exists already"
    trap restore ERR
    echo "$new" >.version
    dev/switch.sh release
    awk -v title="### $new" '$0 == "### Unreleased" && !done {print title; done = 1; next} {print}' CHANGELOG.md >CHANGELOG.md.new
    mv CHANGELOG.md.new CHANGELOG.md
    git add .version CHANGELOG.md
    [[ ! -f composer.json ]] || git add composer.json
    [[ ! -f composer.lock ]] || git add composer.lock
    # The release commit and the tag say the same: v<version>, then the lines of the changelog, verbatim
    git commit -q -m "v$new" -m "$(changelog_section "$new")"
    trap - ERR
fi

if [[ $tag_local == 0 ]]; then
    [[ $(git log -1 --format=%s) == "v$new" ]] || die "HEAD is not the release commit of $new (commits were added after it)"
    if grep -q '"type": "path"' composer.json 2>/dev/null; then
        die "composer.json still has local projects (dev/switch.sh release)"
    fi
    # The message of the tag is the notes of the release: the changelog
    git tag -a "$tag" -m "v$new" -m "$(changelog_section "$new")"
fi

git push "$remote" "$branch"
[[ $tag_remote == 1 ]] || git push "$remote" "$tag"

# The tagged commit is built and published in a folder of its own, whatever the current commit is. It has the files git
# does not hold that a build needs (.env, src, dist, build, vendor, the submodules), linked to those of this folder
work=tmp/release-$tag
cleanup() {
    git worktree remove --force "$work" 2>/dev/null || true
    rm -f "$TMP" "$TMP".* "$LOCK"
}
trap cleanup EXIT
git worktree remove --force "$work" 2>/dev/null || true
mkdir -p tmp dist build
git worktree add -q --detach "$work" "$tag"
while IFS= read -r entry; do
    entry=${entry%/}
    [[ -z "$entry" || $entry == tmp || -e "$work/$entry" ]] || ln -s "$PWD/$entry" "$work/$entry"
done < <(
    git ls-files --others --ignored --exclude-standard --directory | cut -d/ -f1
    git ls-files -s | awk '$1 == 160000 {print $4}' | cut -d/ -f1
)
(
    cd "$work"
    dev/build.sh zip
    # The packages are published by apt-package: the apt repository, and the GitHub release (created from the tag, with
    # the packages and their signed checksums)
    "$(apt_package)" --publish || die "the publication failed, see above. If reprepro could not export the indices (the signature), the packages are in its database but nobody sees them: reprepro -b $(apt_repo_dir) export, then run dev/release.sh again"
)
eval "$(cd "$work" && packaging/version)"
cleanup
if ! "$gh" release view "$tag" -R "$repo" >/dev/null 2>&1; then
    prerelease=()
    [[ $VERSION != *-* ]] || prerelease=(--prerelease)
    "$gh" release create "$tag" -R "$repo" --verify-tag --title "$tag" --notes "$(git tag -l --format='%(contents:body)' "$tag")" "${prerelease[@]}"
fi
assets=(dist/*"-$VERSION.zip")
for deb in dist/*_"${DEB_VERSION}"_*.deb; do
    [[ ! -e "$deb" ]] || assets+=("$deb")
done
# apt-package signs the checksums of what it publishes; after an interrupted publication it publishes nothing more, so
# they are made here, the same way
if [[ -n "$key" && ${#assets[@]} -gt 1 ]]; then
    names=()
    for asset in "${assets[@]:1}"; do names+=("${asset##*/}"); done
    (cd dist && shasum -a 256 "${names[@]}" >SHA256SUMS)
    gpg --yes --armor --detach-sign --local-user "$key" --output dist/SHA256SUMS.asc dist/SHA256SUMS
    assets+=(dist/SHA256SUMS dist/SHA256SUMS.asc)
fi
"$gh" release upload "$tag" "${assets[@]}" -R "$repo" --clobber
success "Published: https://github.com/$repo/releases/tag/$tag"

# The next development version, unless it was already done
if [[ $(version) != "$new" ]]; then
    end 0 "$project $new is released, $(version) is already the version in progress."
fi
trap restore ERR
echo "$next" >.version
dev/switch.sh dev
awk 'BEGIN {done = 0} {print} /^## Changelog/ && !done {print ""; print "### Unreleased"; done = 1}' CHANGELOG.md >CHANGELOG.md.new
mv CHANGELOG.md.new CHANGELOG.md
git add .version CHANGELOG.md
[[ ! -f composer.json ]] || git add composer.json
[[ ! -f composer.lock ]] || git add composer.lock
git commit -q -m "chore(version): $next, the family linked again"
trap - ERR
git push "$remote" "$branch"
success "$project $new is released, now at $next."
