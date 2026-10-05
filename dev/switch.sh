#!/usr/bin/env bash
# Puts the projects of the family this one requires (packaging/siblings) in development mode or in release mode in
# composer.json, and updates composer.lock, so that nobody edits them by hand:
#
#   dev/switch.sh dev       the projects next to this one, linked (path repositories, @dev)
#   dev/switch.sh release   their versions, from Packagist (^ the .version of each project, which must be released
#                           and known by Packagist: the script waits for it, SWITCH_WAIT seconds, 180 by default)

set -e
cd "$(dirname "$0")/.."

mode=${1:-}
if [[ $mode != dev && $mode != release ]]; then
    echo "usage: dev/switch.sh dev|release" >&2
    exit 2
fi
names=$(awk '!/^#/ && NF {print $1}' packaging/siblings 2>/dev/null || true)
if [[ -z "$names" ]]; then
    echo "dev/switch.sh: this project requires none of the family, nothing to switch"
    exit 0
fi

# Whether Packagist knows a version of a package
on_packagist() { # name version
    curl -fsS --max-time 20 "https://repo.packagist.org/p2/$1.json" 2>/dev/null | php -r '
        $json = json_decode(stream_get_contents(STDIN), true);
        foreach ($json["packages"][$argv[1]] ?? [] as $package) {
            if (ltrim($package["version"], "v") === $argv[2]) {
                exit(0);
            }
        }
        exit(1);' "$1" "$2"
}

# The wanted value of each project: its folder (dev), or the constraint of its version (release)
spec="{"
for name in $names; do
    folder=../${name#*/}
    if [[ $mode == dev ]]; then
        value="$folder/"
    else
        if [[ ! -s "$folder/.version" ]]; then
            echo "dev/switch.sh: $folder/.version is missing" >&2
            exit 1
        fi
        version=$(tr -d '[:space:]' <"$folder/.version")
        if [[ $version == *-dev ]]; then
            echo "dev/switch.sh: $name is at $version, a development version: release it first (dev/release.sh in that project)" >&2
            exit 1
        fi
        value="^$version"
    fi
    spec+="\"$name\":\"$value\","
done
spec="${spec%,}}"

# A released version is on Packagist before it is required: the tag of that project is pushed, Packagist takes a moment
if [[ $mode == release ]]; then
    end=$((SECONDS + ${SWITCH_WAIT:-180}))
    while read -r name constraint; do
        until on_packagist "$name" "${constraint#^}"; do
            if [[ $SECONDS -ge $end ]]; then
                echo "dev/switch.sh: Packagist does not know $name ${constraint#^} (is the tag pushed, and the package updated there?)" >&2
                exit 1
            fi
            echo "Waiting for Packagist: $name ${constraint#^}"
            sleep 10
        done
    done < <(php -r 'foreach (json_decode($argv[1], true) as $name => $constraint) { echo "$name $constraint\n"; }' "$spec")
fi

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

# shellcheck disable=SC2086
composer update $names --no-interaction --quiet
echo "composer.json and composer.lock: $mode"
