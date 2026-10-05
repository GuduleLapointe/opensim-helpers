<?php
/**
 * includes/bootstrap.php: loads the config (includes/config.php, else the config of the grid), completes what it leaves
 * out, refuses to run without a database. Each case runs the bootstrap in its own process, in a copy of the helpers
 * with the config under test and an /etc of its own.
 */

/**
 * Run the bootstrap with a config.
 *
 * @param string|null $config The content of includes/config.php, null for none.
 * @param array<string,string> $grids The files of the grids, from etc/grids (Alpha/helpers.ini => content).
 * @param array<string,string> $env Variables of the environment (OPENSIM_GRID).
 * @param string $before PHP code to run before the bootstrap (to stand for an extension).
 * @return array{out: string, err: string, constants: array<string,mixed>}
 */
function bootstrap_with(?string $config, array $grids = [], array $env = [], string $before = '')
{
    $root = sys_get_temp_dir() . '/helpers-bootstrap-' . bin2hex(random_bytes(4));
    mkdir("$root/includes", 0777, true);
    mkdir("$root/classes", 0777, true);
    mkdir("$root/etc/grids", 0777, true);
    foreach (glob(dirname(__DIR__, 2) . '/includes/*.php') ?: [] as $file) {
        if (!in_array(basename($file), ['config.php', 'config.example.php'], true)) {
            copy($file, "$root/includes/" . basename($file));
        }
    }
    copy(dirname(__DIR__, 2) . '/classes/class-grid-config.php', "$root/classes/class-grid-config.php");
    symlink(dirname(__DIR__, 2) . '/vendor', "$root/vendor");
    if ($config !== null) {
        file_put_contents("$root/includes/config.php", "<?php\n$config\n");
    }
    foreach ($grids as $file => $content) {
        @mkdir(dirname("$root/etc/grids/$file"), 0777, true);
        file_put_contents("$root/etc/grids/$file", $content);
    }
    file_put_contents("$root/opensim.conf", "[Defaults]\nDefaultProfile = test\n[test]\nEtcRoot = $root/etc\n");
    file_put_contents(
        "$root/run.php",
        "<?php $before require \"includes/bootstrap.php\"; echo json_encode(get_defined_constants(true)[\"user\"]);",
    );

    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', 'run.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        ['PATH' => (string) getenv('PATH'), 'OPENSIM_CONF' => "$root/opensim.conf"] + $env,
    );
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($root));

    return ['out' => $out, 'err' => $err, 'constants' => json_decode($out, true) ?? []];
}

/** A config made of the main database only. */
const BOOTSTRAP_MAIN_DB = "define('OPENSIM_DB_HOST', '127.0.0.1'); define('OPENSIM_DB_NAME', 'grid');
    define('OPENSIM_DB_USER', 'u'); define('OPENSIM_DB_PASS', 'p');";

/** A helpers.ini giving a grid and its database. */
const BOOTSTRAP_HELPERS_INI = "[Helpers]\ngrid_name = \"Alpha World\"\n[robust_db]\nhostname = \"127.0.0.1\"\nprefix = \"alpha\"\nuser = \"u\"\npassword = \"p\"\n";

describe('Bootstrap with includes/config.php', function () {
    test('says so when there is no config at all', function () {
        $run = bootstrap_with(null);

        expect($run['out'])->toBe('Not properly configured')->and($run['err'])->toContain('no includes/config.php');
    });

    test('says so when the config gives no database', function () {
        $run = bootstrap_with("define('OPENSIM_GRID_NAME', 'Nowhere');");

        expect($run['out'])->toBe('Not properly configured')->and($run['err'])->toContain('no database');
    });

    test('completes a config made of the main database only', function () {
        $c = bootstrap_with(BOOTSTRAP_MAIN_DB)['constants'];

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
        $c = bootstrap_with(
            BOOTSTRAP_MAIN_DB . "define('SEARCH_DB_NAME', 'search'); define('CURRENCY_MONEY_TBL', 'mine');",
        )['constants'];

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

    test('is the config used when it exists, whatever the grids say', function () {
        $c = bootstrap_with(BOOTSTRAP_MAIN_DB . "define('OPENSIM_GRID_NAME', 'From the file');", [
            'Alpha/helpers.ini' => BOOTSTRAP_HELPERS_INI,
        ])['constants'];

        expect($c['OPENSIM_GRID_NAME'])->toBe('From the file');
    });
});

describe('Bootstrap where the xmlrpc extension is', function () {
    test('does not declare its functions again', function () {
        // The functions of the extension, stood for by functions of the same names
        $extension =
            'function xmlrpc_encode() {} function xmlrpc_decode() {} function xmlrpc_server_create() {}' .
            ' function xmlrpc_server_register_method() {} function xmlrpc_server_call_method() {}';

        $run = bootstrap_with(BOOTSTRAP_MAIN_DB, [], [], $extension);

        expect($run['out'])->not->toContain('Fatal')->and($run['constants'])->toHaveKey('OPENSIM_GRID_NAME');
    });
});

describe('Bootstrap with the config of a grid', function () {
    test('takes it when there is no includes/config.php', function () {
        $c = bootstrap_with(null, ['Alpha/helpers.ini' => BOOTSTRAP_HELPERS_INI])['constants'];

        expect($c['OPENSIM_GRID_NAME'])
            ->toBe('Alpha World')
            ->and($c['OPENSIM_DB_NAME'])
            ->toBe('alpha')
            ->and($c['SEARCH_DB_USER'])
            ->toBe('u')
            ->and($c['CURRENCY_MONEY_TBL'])
            ->toBe('balances');
    });

    test('serves the grid the environment names', function () {
        $grids = [
            'Alpha/helpers.ini' => BOOTSTRAP_HELPERS_INI,
            'Beta/helpers.ini' => str_replace('Alpha World', 'Beta World', BOOTSTRAP_HELPERS_INI),
        ];

        $c = bootstrap_with(null, $grids, ['OPENSIM_GRID' => 'Beta'])['constants'];

        expect($c['OPENSIM_GRID_NAME'])->toBe('Beta World');
    });

    test('says so when several grids and none is named', function () {
        $run = bootstrap_with(null, [
            'Alpha/helpers.ini' => BOOTSTRAP_HELPERS_INI,
            'Beta/helpers.ini' => BOOTSTRAP_HELPERS_INI,
        ]);

        expect($run['out'])
            ->toBe('Not properly configured')
            ->and($run['err'])
            ->toContain('several grids')
            ->toContain('Alpha, Beta')
            ->toContain('OPENSIM_GRID');
    });

    test('says so when the grid named is not there', function () {
        $run = bootstrap_with(null, ['Alpha/helpers.ini' => BOOTSTRAP_HELPERS_INI], ['OPENSIM_GRID' => 'Nowhere']);

        expect($run['out'])
            ->toBe('Not properly configured')
            ->and($run['err'])
            ->toContain('grid Nowhere not found')
            ->toContain('Alpha');
    });

    test('says so when it gives no database', function () {
        $run = bootstrap_with(null, ['Alpha/helpers.ini' => "[Helpers]\ngrid_name = \"Alpha\"\n"]);

        expect($run['out'])->toBe('Not properly configured')->and($run['err'])->toContain('no database');
    });
});
