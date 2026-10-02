<?php
/**
 * includes/opensim-kit-config.php: the constants of the helpers come from the OpenSim kit.
 */

/**
 * Run the config in a PHP of its own (it defines constants) with a given environment, and tell the constants.
 *
 * @param array<string,string> $environment Variables of the environment (OPENSIM_CONF, OPENSIM_GRID).
 * @param list<string> $names Constants to give back.
 * @return array{0:int,1:array<string,mixed>,2:string} Exit status, the constants, the errors.
 */
function kit_config_run(array $environment, array $names): array
{
    $root = dirname(__DIR__, 2);
    $code =
        'require ' .
        var_export("$root/includes/opensim-kit-config.php", true) .
        '; echo json_encode(array_combine(' .
        var_export($names, true) .
        ', array_map("constant", ' .
        var_export($names, true) .
        ')));';
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
        $environment + ['PATH' => getenv('PATH')],
    );
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    return [proc_close($process), json_decode((string) $output, true) ?? [], trim((string) $errors)];
}

/**
 * A setup of the kit in a temporary tree, with one grid.
 *
 * @return string The opensim.conf.
 */
function kit_config_tree(): string
{
    $root = sys_get_temp_dir() . '/kit-config-' . bin2hex(random_bytes(4));
    mkdir("$root/etc/grids/alpha", 0o755, true);
    file_put_contents("$root/opensim.conf", "[Defaults]\nDefaultProfile = p\n[p]\nEtcRoot = $root/etc\n");
    file_put_contents(
        "$root/etc/grids/alpha/Robust.HG.ini",
        "[Const]\nBaseURL = \"http://play.example.org\"\nWebURL = \"https://play.example.org\"\nPublicPort = 8002\n" .
            "[DatabaseService]\nConnectionString = \"Data Source=localhost;Database=alpha_robust;User ID=opensim;Password=pw;\"\n" .
            "[GridInfoService]\ngridname = \"Alpha\"\n",
    );

    return "$root/opensim.conf";
}

describe('opensim-kit-config.php', function () {
    test('defines the constants of the helpers from the grid of the kit', function () {
        [$status, $constants] = kit_config_run(
            ['OPENSIM_CONF' => kit_config_tree()],
            ['OPENSIM_GRID_NAME', 'OPENSIM_DB_NAME', 'SEARCH_DB_USER', 'CURRENCY_HELPER_URL'],
        );

        expect($status)->toBe(0);
        expect($constants)->toBe([
            'OPENSIM_GRID_NAME' => 'Alpha',
            'OPENSIM_DB_NAME' => 'alpha_robust',
            'SEARCH_DB_USER' => 'opensim',
            'CURRENCY_HELPER_URL' => 'https://play.example.org/helpers/currency.php',
        ]);
    });

    test('refuses to run when the grid cannot be found', function () {
        [, $constants, $errors] = kit_config_run(
            ['OPENSIM_CONF' => kit_config_tree(), 'OPENSIM_GRID' => 'nowhere'],
            ['OPENSIM_GRID_NAME'],
        );

        expect($constants)->toBe([]);
        expect($errors)->toContain('no grid found in opensim.conf');
    });
});
