<?php
/**
 * Router: the single entry of the helpers. It reads the URL asked and decides which script or page answers.
 *
 * A web server that sends every URL it has no file for to index.php can serve any URL with the helpers: the
 * services at the paths the operator wants (`/search`, `/guide`...), the pages (`/`, `/welcome`) and the
 * scripts under any prefix (`/helpers/query.php`).
 *
 * The routes are the constant OPENSIM_ROUTES of config.php: URL path => script (`query.php`) or page
 * (`@home`, `@splash`). The defaults are `/` for the home page and `/welcome` for the splash page.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Router
{
    /** The scripts that answer requests. */
    const SCRIPTS = [
        'query.php',
        'register.php',
        'offline.php',
        'currency.php',
        'guide.php',
        'motd.php',
        'landtool.php',
        'parser.php',
        'eventsparser.php',
        'textgen.php',
        'directory_info.php',
    ];

    /** The pages, rendered from the Twig templates of the same name. */
    const PAGES = ['home', 'splash'];

    const MIME = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'css' => 'text/css',
        'js' => 'text/javascript',
    ];

    /**
     * The routes: the defaults, then those of the operator.
     *
     * @param  array<string,string> $own
     * @return array<string,string>
     */
    public static function routes($own = [])
    {
        $routes = ['/' => '@home', '/welcome' => '@splash'];
        foreach ($own as $path => $target) {
            $routes[self::normalize($path)] = $target;
        }

        return $routes;
    }

    /** A path without trailing slash, the root being `/`. */
    public static function normalize($path)
    {
        return '/' . trim((string) $path, '/');
    }

    /**
     * What answers a path.
     *
     * @param  string               $path
     * @param  array<string,string> $own the routes of the operator
     * @return array{0:string,1:string}|null [kind, what]: script (file name), page (name), asset (path under assets/)
     */
    public static function match($path, $own = [])
    {
        $path = self::normalize(rawurldecode((string) $path));
        if (strpos($path, '..') !== false) {
            return null;
        }
        $routes = self::routes($own);
        if (isset($routes[$path])) {
            $target = $routes[$path];
            if ($target[0] === '@' && in_array(substr($target, 1), self::PAGES, true)) {
                return ['page', substr($target, 1)];
            }
            if (in_array($target, self::SCRIPTS, true)) {
                return ['script', $target];
            }

            return null;
        }
        if (preg_match('#^/assets/(.+)$#', $path, $m)) {
            return ['asset', $m[1]];
        }
        if (in_array(basename($path), self::SCRIPTS, true)) {
            return ['script', basename($path)];
        }

        return null;
    }

    /**
     * Render a page from its Twig template. The templates of OPENSIM_TEMPLATES_DIR (when defined) win over those of
     * the helpers, so a template can be customized, and a block shown or left out.
     *
     * @param  string               $page
     * @param  array<string,mixed>  $context
     * @param  string[]             $dirs where to look for templates, the first wins
     * @return string
     */
    public static function render($page, $context, $dirs)
    {
        $loader = new \Twig\Loader\FilesystemLoader(array_values(array_filter($dirs, 'is_dir')));
        $twig = new \Twig\Environment($loader, ['autoescape' => 'html']);

        return $twig->render("pages/$page.twig", $context);
    }

    /**
     * What the pages show, from the constants of config.php.
     *
     * @return array<string,mixed>
     */
    public static function context()
    {
        $logo = defined('OPENSIM_GRID_LOGO_URL') ? OPENSIM_GRID_LOGO_URL : null;
        if (empty($logo)) {
            foreach (['svg', 'png'] as $ext) {
                if (is_file(dirname(__DIR__) . "/assets/logos/logo.$ext")) {
                    $logo = "/assets/logos/logo.$ext";
                    break;
                }
            }
        }

        return [
            'grid_name' => defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : 'OpenSimulator grid',
            'login_uri' => defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : null,
            'login_host' => defined('OPENSIM_LOGIN_URI') ? preg_replace('#^https?://#', '', OPENSIM_LOGIN_URI) : null,
            'logo_url' => $logo,
        ];
    }

    /**
     * Answer the current request: a page or an asset is answered here, a script is given back for the entry to include. It
     * has to run in the global scope, as when the web server calls it: the scripts keep the database and the settings in
     * global variables ($SearchDB...) that their functions read.
     *
     * @return string|null The script to include, none when the request is answered.
     */
    public static function run()
    {
        $own = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $found = self::match(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), $own);
        if ($found === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not found\n";

            return null;
        }
        [$kind, $what] = $found;
        $root = dirname(__DIR__);
        switch ($kind) {
            case 'page':
                $dirs = [];
                if (defined('OPENSIM_TEMPLATES_DIR')) {
                    $dirs[] = OPENSIM_TEMPLATES_DIR;
                }
                $dirs[] = "$root/templates/twig";
                header('Content-Type: text/html; charset=utf-8');
                echo self::render($what, self::context(), $dirs);

                return null;
            case 'asset':
                $file = "$root/assets/$what";
                $type = self::MIME[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
                if ($type === null || !is_file($file)) {
                    http_response_code(404);

                    return null;
                }
                header("Content-Type: $type");
                header('Cache-Control: public, max-age=86400');
                readfile($file);

                return null;
            default:
                // The scripts expect their own folder as the working one
                chdir($root);
                $_SERVER['SCRIPT_NAME'] = '/' . $what;
                $_SERVER['SCRIPT_FILENAME'] = "$root/$what";
                $_SERVER['PHP_SELF'] = '/' . $what;

                return $root . '/' . $what;
        }
    }
}
