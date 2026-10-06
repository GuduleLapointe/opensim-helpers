<?php
/**
 * classes/class-router.php: which script or page answers a URL.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/classes/class-router.php';

describe('Router', function () {
    test('serves the home and the splash page by default', function () {
        expect(OpenSim_Helpers_Router::match('/'))
            ->toBe(['page', 'home'])
            ->and(OpenSim_Helpers_Router::match('/welcome/'))
            ->toBe(['page', 'splash']);
    });

    test('serves a script under any prefix', function () {
        expect(OpenSim_Helpers_Router::match('/helpers/query.php'))
            ->toBe(['script', 'query.php'])
            ->and(OpenSim_Helpers_Router::match('/a/b/currency.php'))
            ->toBe(['script', 'currency.php']);
    });

    test('serves the routes of the operator', function () {
        $own = ['/search/' => 'query.php', '/hello' => '@splash'];

        expect(OpenSim_Helpers_Router::match('/search', $own))
            ->toBe(['script', 'query.php'])
            ->and(OpenSim_Helpers_Router::match('/hello', $own))
            ->toBe(['page', 'splash'])
            ->and(OpenSim_Helpers_Router::match('/welcome', $own))
            ->toBe(['page', 'splash']);
    });

    test('serves the assets, nothing outside them', function () {
        expect(OpenSim_Helpers_Router::match('/assets/logos/logo.svg'))
            ->toBe(['asset', 'logos/logo.svg'])
            ->and(OpenSim_Helpers_Router::match('/assets/../includes/config.php'))
            ->toBeNull();
    });

    test('knows no other URL', function () {
        expect(OpenSim_Helpers_Router::match('/nothing'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/includes/config.php'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/x', ['/x' => 'config.php']))
            ->toBeNull();
    });

    test('gives a script back to the entry, which includes it in the global scope', function () {
        $_SERVER['REQUEST_URI'] = '/helpers/query.php';
        $cwd = getcwd();
        $script = OpenSim_Helpers_Router::run();
        chdir($cwd);

        expect($script)->toBe(dirname(__DIR__, 2) . '/query.php');
    });

    test('answers a page itself and gives nothing back', function () {
        $_SERVER['REQUEST_URI'] = '/nothing';
        ob_start();
        $script = OpenSim_Helpers_Router::run();
        ob_end_clean();

        expect($script)->toBeNull();
    });

    test('renders a page, a customized template wins', function () {
        $context = [
            'grid_name' => 'Alpha <b>',
            'login_uri' => 'http://alpha:8002',
            'logo_url' => '/assets/logos/logo.svg',
        ];
        $html = OpenSim_Helpers_Router::render('home', $context, [dirname(__DIR__, 2) . '/templates/twig']);

        expect($html)
            ->toContain('Alpha &lt;b&gt;')
            ->and($html)
            ->toContain('http://alpha:8002')
            ->and($html)
            ->toContain('logo.svg');

        $own = sys_get_temp_dir() . '/tpl-' . bin2hex(random_bytes(4));
        mkdir("$own/pages", 0o755, true);
        file_put_contents("$own/pages/home.twig", 'Mine {{ grid_name }}');

        expect(
            OpenSim_Helpers_Router::render('home', $context, [$own, dirname(__DIR__, 2) . '/templates/twig']),
        )->toContain('Mine Alpha');
    });
});
