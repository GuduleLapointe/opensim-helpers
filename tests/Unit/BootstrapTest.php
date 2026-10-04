<?php
/**
 * includes/bootstrap.php: loads the config, completes what it leaves out, refuses to run without a database.
 * Each case runs the bootstrap in its own process, in a copy of the includes with the config under test.
 */

/**
 * Run the bootstrap with a config.
 *
 * @param string|null $config The content of config.php, null for none.
 * @return array{out: string, err: string, constants: array<string,mixed>}
 */
function bootstrap_with(?string $config)
{
    $root = sys_get_temp_dir() . '/helpers-bootstrap-' . bin2hex(random_bytes(4));
    mkdir("$root/includes", 0777, true);
    foreach (glob(dirname(__DIR__, 2) . '/includes/*.php') ?: [] as $file) {
        if (!in_array(basename($file), ['config.php', 'config.example.php'], true)) {
            copy($file, "$root/includes/" . basename($file));
        }
    }
    symlink(dirname(__DIR__, 2) . '/vendor', "$root/vendor");
    if ($config !== null) {
        file_put_contents("$root/includes/config.php", "<?php\n$config\n");
    }
    file_put_contents(
        "$root/run.php",
        '<?php require "includes/bootstrap.php"; echo json_encode(get_defined_constants(true)["user"]);',
    );

    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', 'run.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
    );
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($root));

    return ['out' => $out, 'err' => $err, 'constants' => json_decode($out, true) ?? []];
}

describe('Bootstrap', function () {
    test('says so when there is no config', function () {
        $run = bootstrap_with(null);

        expect($run['out'])->toBe('Not properly configured')->and($run['err'])->toContain('config.php is missing');
    });

    test('says so when the config gives no database', function () {
        $run = bootstrap_with("define('OPENSIM_GRID_NAME', 'Nowhere');");

        expect($run['out'])->toBe('Not properly configured')->and($run['err'])->toContain('no database');
    });

    test('completes a config made of the main database only', function () {
        $run = bootstrap_with(
            "define('OPENSIM_DB_HOST', '127.0.0.1'); define('OPENSIM_DB_NAME', 'grid');" .
                "define('OPENSIM_DB_USER', 'u'); define('OPENSIM_DB_PASS', 'p');",
        );
        $c = $run['constants'];

        expect($c)
            ->toHaveKey('OPENSIM_DB', true)
            ->and($c['SEARCH_DB_NAME'])
            ->toBe('grid')
            ->and($c['CURRENCY_DB_USER'])
            ->toBe('u')
            ->and($c['OFFLINE_DB_PASS'])
            ->toBe('p')
            ->and($c['CURRENCY_MONEY_TBL'])
            ->toBe('balances')
            ->and($c['OFFLINE_MESSAGE_TBL'])
            ->toBe('im_offline')
            ->and($c['OPENSIM_USE_UTC_TIME'])
            ->toBeTrue()
            ->and($c['CURRENCY_PROVIDER'])
            ->toBeNull();
    });

    test('keeps what the config defines', function () {
        $run = bootstrap_with(
            "define('OPENSIM_DB_HOST', '127.0.0.1'); define('OPENSIM_DB_NAME', 'grid');" .
                "define('OPENSIM_DB_USER', 'u'); define('OPENSIM_DB_PASS', 'p');" .
                "define('SEARCH_DB_NAME', 'search'); define('CURRENCY_MONEY_TBL', 'mine');",
        );
        $c = $run['constants'];

        expect($c['SEARCH_DB_NAME'])
            ->toBe('search')
            ->and($c['SEARCH_DB_HOST'])
            ->toBe('127.0.0.1')
            ->and($c['CURRENCY_MONEY_TBL'])
            ->toBe('mine');
    });

    test('runs with a search database only', function () {
        $run = bootstrap_with(
            "define('OPENSIM_DB', false); define('SEARCH_DB_HOST', '127.0.0.1'); define('SEARCH_DB_NAME', 's');" .
                "define('SEARCH_DB_USER', 'u'); define('SEARCH_DB_PASS', 'p');",
        );

        expect($run['out'])
            ->not->toBe('Not properly configured')
            ->and($run['constants'])
            ->not->toHaveKey('CURRENCY_DB_HOST');
    });
});
