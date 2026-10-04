<?php
/**
 * The Debian package and the zip, built by dev/build.sh and tried in a clean container (podman). Slow, so only when
 * asked: PACKAGING=1 vendor/bin/pest
 */

$root = dirname(__DIR__, 2);
$tools = trim((string) shell_exec('command -v podman')) !== '' && trim((string) shell_exec('command -v nfpm')) !== '';

if (!getenv('PACKAGING')) {
    $skip = 'slow, PACKAGING=1 to run';
} elseif (!$tools) {
    $skip = 'podman and nfpm are needed';
} else {
    $skip = '';
}

/**
 * Runs a script of the project and gives its exit status and the end of its output.
 *
 * @param list<string> $command Script and arguments.
 * @return array{0:int,1:string}
 */
function packaging_run(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, dirname(__DIR__, 2));
    $lines = [];
    while (false !== ($line = fgets($pipes[1]))) {
        $lines[] = rtrim($line);
    }

    return [proc_close($process), implode("\n", array_slice($lines, -40))];
}

describe('Packaging', function () use ($root, $skip) {
    test('dev/build.sh makes the deb and the zip', function () use ($root) {
        [$status, $output] = packaging_run(["$root/dev/build.sh"]);

        expect($status, $output)->toBe(0);
        expect(glob("$root/dist/*.deb"))->not->toBeEmpty();
        expect(glob("$root/dist/*.zip"))->not->toBeEmpty();
    })->skip('' !== $skip, $skip);

    test('they work on Debian 12', function () use ($root) {
        [$status, $output] = packaging_run(["$root/tests/Packaging/check"]);

        expect($status, $output)->toBe(0);
    })
        ->depends('dev/build.sh makes the deb and the zip')
        ->skip('' !== $skip, $skip);
});
