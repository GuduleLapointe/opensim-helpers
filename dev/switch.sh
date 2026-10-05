#!/usr/bin/env bash
# Puts the projects of the family this one requires (packaging/siblings) in development mode or in release mode in
# composer.json, and updates composer.lock, so that nobody edits them by hand:
#
#   dev/switch.sh dev       the projects next to this one, linked (path repositories, @dev)
#   dev/switch.sh release   their last release, from Packagist: ^ the latest version tag of each project, which must
#                           have nothing more than the files of its release. If composer does not find it, it is tried
#                           again (the new tag may not be known yet) for SWITCH_WAIT seconds, 1200 by default, and says why

set -e
cd "$(dirname "$0")/.."
source dev/lib.sh

mode=${1:-}
if [[ $mode != dev && $mode != release ]]; then
    USAGE="dev/switch.sh dev|release"
    usage >&2
    exit 2
fi
names=$(awk '!/^#/ && NF {print $1}' packaging/siblings 2>/dev/null || true)
if [[ -z "$names" ]]; then
    end 0 "this project requires none of the family, nothing to switch"
fi

# The files of a project changed since a release that are in its packages (not the version, the changelog, composer,
# nor what its .distignore leaves out)
packaged_changes() { # folder, tag
    local pattern
    pattern=$(awk '!/^#/ && NF {
        entry = $0; sub(/\/$/, "", entry)
        if (entry ~ /[*?]/) next
        gsub(/\./, "\\.", entry)
        if (sub(/^\//, "", entry)) printf "%s^%s(/|$)", sep, entry; else printf "%s(^|/)%s(/|$)", sep, entry
        sep = "|"
    }' "$1/.distignore" 2>/dev/null)
    git -C "$1" diff --name-only "$2" HEAD | grep -vE '^(\.version|CHANGELOG\.md|composer\.(json|lock))$' | { if [[ -n "$pattern" ]]; then grep -vE "$pattern" || true; else cat; fi; }
}

# The wanted value of each project: its folder (dev), or the constraint of its version (release)
spec="{"
for name in $names; do
    folder=../${name#*/}
    if [[ $mode == dev ]]; then
        value="$folder/"
    else
        tag=$(git -C "$folder" describe --tags --abbrev=0 --match '[0-9]*' --match 'v[0-9]*' 2>/dev/null) ||
            die "$name has no release, no version tag in $folder"
        changed=$(packaged_changes "$folder" "$tag")
        if [[ -n "$changed" ]]; then
            die "$name has changed since its release $tag: release it first (cd $folder && dev/release.sh):
$(sed 's/^/    /' <<<"$changed")"
        fi
        value="^${tag#v}"
    fi
    spec+="\"$name\":\"$value\","
done
spec="${spec%,}}"

php -- "$mode" "$spec" <<'PHP'
<?php
// composer.json with the projects in the wanted mode, everything else as it was
[, $mode, $spec] = $argv;
$spec = json_decode($spec, true);
$json = json_decode(file_get_contents('composer.json'));

foreach ($spec as $name => $value) {
    $json->require->$name = $mode === 'dev' ? '@dev' : $value;
}

$repositories = array_values(array_filter(
    $json->repositories ?? [],
    fn($repository) => !array_key_exists($repository->name ?? '', $spec),
));
if ($mode === 'dev') {
    $linked = [];
    foreach ($spec as $name => $folder) {
        $linked[] = (object) [
            'name' => $name,
            'url' => $folder,
            'type' => 'path',
            'options' => (object) ['symlink' => true],
        ];
    }
    $repositories = array_merge($linked, $repositories);
}

// Rebuilt in the same order, the repositories at their place: after require-dev, else after require
$result = new stdClass();
$after = isset($json->{'require-dev'}) ? 'require-dev' : 'require';
foreach ($json as $key => $value) {
    if ($key === 'repositories') {
        continue;
    }
    $result->$key = $value;
    if ($key === $after && $repositories) {
        $result->repositories = $repositories;
    }
}
if ($repositories && !isset($result->repositories)) {
    $result->repositories = $repositories;
}

file_put_contents(
    'composer.json',
    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
);
PHP

# In release mode composer is tried again until it finds the versions, in case Packagist does not know the new tag yet
end=$((SECONDS + ${SWITCH_WAIT:-1200}))
# shellcheck disable=SC2086
until output=$(composer update $names --no-interaction 2>&1); do
    if [[ $mode == dev || $SECONDS -ge $end ]]; then
        echo "$output" >&2
        die "composer could not update $names"
    fi
    log "Composer cannot resolve the versions yet ($((end - SECONDS)) s left before giving up): $(grep -m1 -iE 'problem|could not|cannot|error' <<<"$output" | cut -c1-200)"
    sleep 20
done
success "composer.json and composer.lock: $mode"
