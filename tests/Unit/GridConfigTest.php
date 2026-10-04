<?php
/**
 * classes/class-grid-config.php: the profile, the Robust config of a grid, its helpers.ini, and the constants they give.
 */

require_once dirname(__DIR__, 2) . '/classes/class-grid-config.php';

/**
 * A setup in a temporary tree: a profile, one grid.
 *
 * @param string $helpers The content of helpers.ini of the grid, none when empty.
 * @param bool $robust Whether the grid has a Robust config.
 * @return array{0:string,1:string} The opensim.conf, the grid folder.
 */
function grid_config_tree(string $helpers = '', bool $robust = true): array
{
    $root = sys_get_temp_dir() . '/helpers-grid-' . bin2hex(random_bytes(4));
    $grid = "$root/etc/grids/Alpha";
    mkdir($grid, 0o755, true);
    file_put_contents(
        "$root/opensim.conf",
        "[Defaults]\nDefaultProfile = 0.9.3.0\nSystemUser = opensim\n\n[0.9.3.0]\nEtcRoot = $root/etc\n",
    );
    if ($robust) {
        file_put_contents(
            "$grid/Robust.HG.ini",
            <<<'INI'
            ; Robust of the grid
            [Const]
                BaseHostname = "play.example.org"
                BaseURL = "http://${Const|BaseHostname}"
                WebURL = "https://${Const|BaseHostname}"
                PublicPort = 8002
            [DatabaseService]
                ConnectionString = "Data Source=localhost;Database=alpha_robust;User ID=opensim;Password=s3cret;Old Guids=true;"
            [Hypergrid]
                GatekeeperURI = "${Const|BaseURL}:${Const|PublicPort}"
            [GridInfoService]
                gridname = "Alpha World"
            INI
            ,
        );
    }
    if ($helpers !== '') {
        file_put_contents("$grid/helpers.ini", $helpers);
    }

    return ["$root/opensim.conf", $grid];
}

describe('Grid config', function () {
    test('reads an ini, constants expanded', function () {
        [, $grid] = grid_config_tree();

        $ini = OpenSim_Helpers_GridConfig::read_ini("$grid/Robust.HG.ini");

        expect($ini['Const']['BaseURL'])
            ->toBe('http://play.example.org')
            ->and($ini['Hypergrid']['GatekeeperURI'])
            ->toBe('http://play.example.org:8002')
            ->and($ini['GridInfoService']['gridname'])
            ->toBe('Alpha World')
            ->and(OpenSim_Helpers_GridConfig::read_ini('/nonexistent.ini'))
            ->toBe([]);
    });

    test('gives the default profile and the grids it has', function () {
        [$conf] = grid_config_tree();

        $profile = OpenSim_Helpers_GridConfig::profile($conf);

        expect($profile['SystemUser'])
            ->toBe('opensim')
            ->and(array_keys(OpenSim_Helpers_GridConfig::grids($profile)))
            ->toBe(['Alpha'])
            ->and(OpenSim_Helpers_GridConfig::grid_nick(null, $conf))
            ->toBe('Alpha')
            ->and(OpenSim_Helpers_GridConfig::grid_nick('Beta', $conf))
            ->toBeNull();
    });

    test('takes the settings from Robust', function () {
        [$conf] = grid_config_tree();

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect($settings['grid_name'])
            ->toBe('Alpha World')
            ->and($settings['login_uri'])
            ->toBe('http://play.example.org:8002')
            ->and($settings['web_url'])
            ->toBe('https://play.example.org')
            ->and($settings['hypergrid'])
            ->toBeTrue()
            ->and($settings['databases']['robust_db'])
            ->toBe([
                'hostname' => 'localhost',
                'prefix' => 'alpha_robust',
                'user' => 'opensim',
                'password' => 's3cret',
            ])
            // Without anything in helpers.ini every database is the one of Robust
            ->and($settings['databases']['search_db'])
            ->toBe($settings['databases']['robust_db']);
    });

    test('lets helpers.ini override', function () {
        [$conf] = grid_config_tree(
            <<<'INI'
            [Helpers]
            path = "/helper"
            mail_sender = "no-reply@example.org"
            currency_provider = "gloebit"
            [search_db]
            hostname = "db2"
            prefix = "ossearch"
            user = "search"
            password = "pw"
            [Urls]
            search = "/search"
            INI
            ,
        );

        $settings = OpenSim_Helpers_GridConfig::settings('Alpha', $conf);
        $constants = OpenSim_Helpers_GridConfig::constants($settings);

        expect($constants['SEARCH_DB_HOST'])
            ->toBe('db2')
            ->and($constants['SEARCH_DB_NAME'])
            ->toBe('ossearch')
            ->and($constants['OPENSIM_DB_NAME'])
            ->toBe('alpha_robust')
            ->and($constants['OPENSIM_MAIL_SENDER'])
            ->toBe('no-reply@example.org')
            ->and($constants['CURRENCY_PROVIDER'])
            ->toBe('gloebit')
            ->and(OpenSim_Helpers_GridConfig::script_path($settings, 'query.php'))
            ->toBe('/search')
            ->and(OpenSim_Helpers_GridConfig::script_path($settings, 'guide.php'))
            ->toBe('/helper/guide.php')
            ->and($constants['CURRENCY_HELPER_URL'])
            ->toBe('https://play.example.org/helper/currency.php');
    });

    test('gives the message of the day of helpers.ini', function () {
        [$conf] = grid_config_tree("[Helpers]\nmotd = \"Hello\\nthere\"\n");

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect(OpenSim_Helpers_GridConfig::constants($settings)['OPENSIM_MOTD'])->toBe('Hello\\nthere');
    });

    test('needs no Robust config when helpers.ini is full', function () {
        [$conf] = grid_config_tree(
            <<<'INI'
            [Helpers]
            grid_name = "Alpha"
            login_uri = "http://play.example.org:8002"
            web_url = "https://play.example.org"
            [robust_db]
            hostname = "localhost"
            prefix = "alpha_robust"
            user = "helpers"
            password = "pw"
            INI
            ,
            false,
        );

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect($settings['web_url'])
            ->toBe('https://play.example.org')
            ->and($settings['databases']['robust_db']['user'])
            ->toBe('helpers')
            ->and($settings['login_uri'])
            ->toBe('http://play.example.org:8002')
            ->and($settings['hypergrid'])
            ->toBeFalse();
    });

    test('works from the example helpers.ini', function () {
        [$conf] = grid_config_tree(
            (string) file_get_contents(dirname(__DIR__, 2) . '/includes/helpers.example.ini'),
            false,
        );

        $constants = OpenSim_Helpers_GridConfig::constants(OpenSim_Helpers_GridConfig::settings(null, $conf));

        expect($constants['OPENSIM_GRID_NAME'])
            ->toBe('Your Grid')
            ->and($constants['OPENSIM_DB_NAME'])
            ->toBe('robust')
            ->and($constants['CURRENCY_HELPER_URL'])
            ->toBe('https://yourgrid.org/helpers/currency.php');
    });

    test('gives what the grid says, the defaults are for the helpers', function () {
        [$conf] = grid_config_tree();

        $constants = OpenSim_Helpers_GridConfig::constants(OpenSim_Helpers_GridConfig::settings(null, $conf));

        expect($constants)
            ->toHaveKeys([
                'OPENSIM_GRID_NAME',
                'OPENSIM_LOGIN_URI',
                'OPENSIM_DB_HOST',
                'SEARCH_DB_NAME',
                'CURRENCY_DB_USER',
                'OFFLINE_DB_PASS',
                'ROBUST_DB_HOST',
                'CURRENCY_HELPER_URL',
            ])
            ->not->toHaveKeys(['OPENSIM_USE_UTC_TIME', 'CURRENCY_MONEY_TBL', 'CURRENCY_RATE', 'HYPEVENTS_URL'])
            ->and($constants['CURRENCY_HELPER_URL'])
            ->toBe('https://play.example.org/helpers/currency.php');
    });

    test('finds no grid, or none chosen', function () {
        [$conf, $grid] = grid_config_tree();
        expect(OpenSim_Helpers_GridConfig::settings('Nowhere', $conf))->toBeNull();

        mkdir(dirname($grid) . '/Beta');
        copy("$grid/Robust.HG.ini", dirname($grid) . '/Beta/Robust.ini');

        expect(OpenSim_Helpers_GridConfig::grid_nick(null, $conf))
            ->toBeNull()
            ->and(OpenSim_Helpers_GridConfig::grid_nick('Beta', $conf))
            ->toBe('Beta');
    });

    test('reads a connection string', function () {
        $string = 'Data Source=db;Database=x;User ID=u;Password=p;Old Guids=true;';

        expect(OpenSim_Helpers_GridConfig::connection($string))->toBe([
            'hostname' => 'db',
            'prefix' => 'x',
            'user' => 'u',
            'password' => 'p',
        ]);
    });
});
