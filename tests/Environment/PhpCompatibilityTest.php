<?php
/**
 * The PHP versions the code supports: the minimum declared in composer.json is
 * the one the code needs, and nothing in it breaks or is deprecated up to the
 * newest version it is checked against.
 */

$root = dirname(__DIR__, 2);
$composer = json_decode((string) file_get_contents("$root/composer.json"), true);
$minimum = preg_replace('/^[^0-9]*/', '', $composer['require']['php'] ?? '');
$newest = '8.5';

/**
 * The PHP files git knows and does not ignore: what .gitignore leaves out is not the code of the project.
 *
 * @return list<string> Absolute paths, none when this is not a checkout.
 */
function compat_php_files(string $root): array
{
    $files = [];
    if (is_dir("$root/.git")) {
        exec('git -C ' . escapeshellarg($root) . ' ls-files --cached --others --exclude-standard -- "*.php"', $files);
    }

    return array_values(array_filter(array_map(fn($file) => "$root/$file", $files), 'is_file'));
}

describe('PHP', function () use ($root, $composer, $minimum, $newest) {
    test('minimum declared in composer.json', function () use ($minimum) {
        expect($minimum)->toMatch('/^\d+\.\d+$/');
    });

    test('composer platform follows the minimum', function () use ($composer, $minimum) {
        expect($composer['config']['platform']['php'] ?? '')->toBe("$minimum.0");
    })->depends('minimum declared in composer.json');

    test('tests run on the minimum or newer', function () use ($minimum) {
        expect(version_compare(PHP_VERSION, $minimum, '>='))->toBeTrue();
    })->depends('minimum declared in composer.json');

    test('code has no brace glob flag, Alpine (musl, lerd) lacks it', function () use ($root) {
        $needle = 'GLOB_' . 'BRACE'; // built, so that this file does not match itself
        $found = [];
        foreach (compat_php_files($root) as $file) {
            if (str_contains((string) file_get_contents($file), $needle)) {
                $found[] = str_replace("$root/", '', $file);
            }
        }

        expect($found)->toBe([]);
    });

    test('code needs nothing newer, nothing deprecated up to the newest', function () use ($root, $minimum, $newest) {
        // The whole folder when it is not a checkout
        $listed = compat_php_files($root);

        $command = [
            PHP_BINARY,
            "$root/vendor/bin/phpcs",
            "--standard=$root/phpcs.xml.dist",
            '--runtime-set',
            'testVersion',
            "$minimum-$newest",
            '--report=json',
            '-q',
            ...$listed ?: [$root],
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        proc_close($process);

        $report = json_decode($output, true);
        expect($report, 'phpcs did not answer: ' . $errors . $output)->toBeArray();

        $findings = [];
        foreach ($report['files'] as $file => $info) {
            foreach ($info['messages'] as $message) {
                $findings[] = str_replace("$root/", '', $file) . ':' . $message['line'] . ' ' . $message['message'];
            }
        }

        expect($findings)->toBe([]);
    })->depends('minimum declared in composer.json');
});
