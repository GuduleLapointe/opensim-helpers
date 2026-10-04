<?php
/**
 * The settings of the helpers from the config of a grid, when there is no includes/config.php.
 *
 * The grids of a machine are in /etc/opensim/grids/<grid>/: the Robust config of the grid and its helpers.ini, which
 * says what Robust does not (see includes/helpers.example.ini). The helpers need no Robust config at all when
 * helpers.ini gives them the grid and its database. Read only, nothing is created.
 *
 * Which grid: the one named by OPENSIM_GRID (constant, environment or web server variable, the virtual host of a grid
 * sets it), else the only grid there is. The files are the only source: what they do not say, bootstrap.php has a
 * default for.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

class OpenSim_Helpers_GridConfig
{
    const DEFAULT_CONF = '/etc/opensim/opensim.conf';
    const DEFAULT_ETC = '/etc/opensim';

    /**
     * The services of the helpers and the script that answers each, the way helpers.ini names them
     * ([Urls] search = "/search" serves query.php there).
     */
    const SERVICES = [
        'search' => 'query.php',
        'register' => 'register.php',
        'offline' => 'offline.php',
        'currency' => 'currency.php',
        'guide' => 'guide.php',
        'motd' => 'motd.php',
        'home' => '@home',
        'welcome' => '@splash',
        'landtool' => 'landtool.php',
        'parser' => 'parser.php',
        'eventsparser' => 'eventsparser.php',
        'textgen' => 'textgen.php',
        'directory_info' => 'directory_info.php',
    ];

    /**
     * Define the constants of the grid.
     *
     * @return string|null why it cannot, null when the constants are defined
     */
    public static function load()
    {
        $profile = self::profile();
        $grids = self::grids($profile);
        $etc = self::etc_root($profile);
        $nick = self::requested_nick();
        if (!$grids) {
            return "no includes/config.php, and no grid in $etc/grids (see includes/helpers.example.ini)";
        }
        if ($nick !== '' && !isset($grids[$nick])) {
            return "grid $nick not found in $etc/grids (" . implode(', ', array_keys($grids)) . ')';
        }
        if ($nick === '' && count($grids) > 1) {
            return "several grids in $etc/grids (" .
                implode(', ', array_keys($grids)) .
                '), give the nick of the one to serve in OPENSIM_GRID';
        }

        $settings = self::settings($nick);
        // helpers.ini holds the password of the database: without it the helpers would try the placeholder of the Robust config
        $ini = $settings['dir'] . '/helpers.ini';
        if (is_file($ini) && !is_readable($ini)) {
            return "$ini is not readable by the web server user (it belongs to the group of the web server, mode 640: chgrp www-data)";
        }
        self::define_constants($settings);

        return null;
    }

    /**
     * The opensim.conf to read: OPENSIM_CONF (constant or environment), else the one of the system.
     *
     * @return string|null
     */
    public static function conf_path()
    {
        $path = defined('OPENSIM_CONF') ? OPENSIM_CONF : getenv('OPENSIM_CONF');
        if (empty($path)) {
            $path = $_SERVER['OPENSIM_CONF'] ?? self::DEFAULT_CONF;
        }

        return is_file($path) ? $path : null;
    }

    /**
     * Read an ini file the way OpenSimulator writes them: sections, `key = value`, quotes around a
     * value optional, `;` and `#` comments. `${Const|Name}` is replaced by the value of [Const].
     *
     * @param  string $path
     * @return array<string,array<string,string>> section => key => value, empty when unreadable
     */
    public static function read_ini($path)
    {
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;
        if ($lines === false) {
            return [];
        }

        $ini = [];
        $section = '';
        foreach ($lines as $line) {
            $line = trim(str_replace("\r", '', $line));
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                $section = trim($m[1]);
                $ini[$section] ??= [];
                continue;
            }
            if (!preg_match('/^([^=]+?)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $value = trim($m[2]);
            if (preg_match('/^"(.*)"\s*(?:[;#].*)?$/', $value, $quoted)) {
                $value = $quoted[1];
            }
            $ini[$section][trim($m[1])] = $value;
        }

        return self::expand($ini);
    }

    /**
     * Replace `${Const|Name}` by the value of Name in [Const], itself expanded (a few levels).
     *
     * @param  array<string,array<string,string>> $ini
     * @return array<string,array<string,string>>
     */
    private static function expand($ini)
    {
        $const = $ini['Const'] ?? [];
        $replace = function ($value) use (&$const) {
            for ($i = 0; $i < 5 && strpos($value, '${Const|') !== false; $i++) {
                $value = preg_replace_callback('/\$\{Const\|([^}]+)\}/', fn($m) => $const[$m[1]] ?? $m[0], $value);
            }

            return $value;
        };
        foreach ($const as $key => $value) {
            $const[$key] = $replace($value);
        }
        foreach ($ini as $section => $values) {
            foreach ($values as $key => $value) {
                $ini[$section][$key] = $replace($value);
            }
        }

        return $ini;
    }

    /**
     * The profile of opensim.conf: [Defaults] completed by the section of the default profile.
     *
     * @param  string|null $conf
     * @return array<string,string> empty when there is no opensim.conf
     */
    public static function profile($conf = null)
    {
        $conf ??= self::conf_path();
        $ini = $conf === null ? [] : self::read_ini($conf);
        $defaults = $ini['Defaults'] ?? [];
        $profile = $defaults;
        if (!empty($defaults['DefaultProfile']) && isset($ini[$defaults['DefaultProfile']])) {
            $profile = array_merge($defaults, $ini[$defaults['DefaultProfile']]);
        }

        return $profile;
    }

    /** Where the config of the machine is: EtcRoot of the profile, else /etc/opensim. */
    private static function etc_root($profile)
    {
        return rtrim(!empty($profile['EtcRoot']) ? $profile['EtcRoot'] : self::DEFAULT_ETC, '/');
    }

    /**
     * The grids of the profile: the folders of EtcRoot/grids/ (/etc/opensim/grids/ without opensim.conf) with a
     * Robust config or a helpers.ini.
     *
     * @param  array<string,string> $profile
     * @return array<string,string> nick => folder
     */
    public static function grids($profile)
    {
        $etc = self::etc_root($profile);
        $grids = [];
        foreach (glob("$etc/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (['Robust.HG.ini', 'Robust.ini', 'helpers.ini'] as $file) {
                if (is_file("$dir/$file")) {
                    $grids[basename($dir)] = $dir;
                    break;
                }
            }
        }

        return $grids;
    }

    /**
     * The nick of the grid the environment names, empty when it does not.
     *
     * @return string
     */
    private static function requested_nick()
    {
        return (string) (defined('OPENSIM_GRID')
            ? OPENSIM_GRID
            : (getenv('OPENSIM_GRID') ?:
            $_SERVER['OPENSIM_GRID'] ?? ''));
    }

    /**
     * The grid asked, or the one the environment names, or the only one.
     *
     * @param  string|null $nick
     * @param  string|null $conf
     * @return string|null its nick, null when it cannot be told
     */
    public static function grid_nick($nick = null, $conf = null)
    {
        $grids = self::grids(self::profile($conf));
        $nick = $nick ?: self::requested_nick();
        if ($nick !== '') {
            return isset($grids[$nick]) ? $nick : null;
        }

        return count($grids) === 1 ? (string) array_key_first($grids) : null;
    }

    /**
     * What the helpers of a grid need, from its Robust config and its helpers.ini.
     *
     * helpers.ini can carry everything the helpers need (grid_name, login_uri, web_url, [robust_db]), so the
     * web server user does not have to read the Robust config, which holds more than the helpers need.
     *
     * The databases are the one of Robust unless helpers.ini gives another ([search_db], [currency_db],
     * [offline_db], [opensim_db] with hostname, prefix (the database), user, password).
     *
     * @param  string|null $nick
     * @param  string|null $conf
     * @return array|null null when the grid is not found
     */
    public static function settings($nick = null, $conf = null)
    {
        $profile = self::profile($conf);
        $nick = self::grid_nick($nick, $conf);
        $grids = self::grids($profile);
        if ($nick === null || !isset($grids[$nick])) {
            return null;
        }

        $dir = $grids[$nick];
        $robust_file = null;
        foreach (['Robust.HG.ini', 'Robust.ini'] as $file) {
            if (is_file("$dir/$file")) {
                $robust_file = "$dir/$file";
                break;
            }
        }
        $robust = $robust_file === null ? [] : self::read_ini($robust_file);
        $helpers = self::read_ini("$dir/helpers.ini");
        $options = $helpers['Helpers'] ?? [];
        $weburl = rtrim($options['web_url'] ?? ($robust['Const']['WebURL'] ?? ''), '/');

        $database = self::connection($robust['DatabaseService']['ConnectionString'] ?? '');
        foreach (['hostname', 'prefix', 'user', 'password'] as $key) {
            $database[$key] = $helpers['robust_db'][$key] ?? $database[$key];
        }
        $databases = ['robust_db' => $database];
        foreach (['opensim_db', 'search_db', 'currency_db', 'offline_db'] as $name) {
            $given = $helpers[$name] ?? [];
            $databases[$name] = [
                'hostname' => $given['hostname'] ?? $database['hostname'],
                'prefix' => $given['prefix'] ?? $database['prefix'],
                'user' => $given['user'] ?? $database['user'],
                'password' => $given['password'] ?? $database['password'],
            ];
        }

        $base = '/' . trim($options['path'] ?? 'helpers', '/');

        return [
            'nick' => $nick,
            'dir' => $dir,
            'profile' => $profile,
            'hypergrid' => $robust_file !== null && str_contains(basename($robust_file), '.HG.'),
            'grid_name' => $options['grid_name'] ?? ($robust['GridInfoService']['gridname'] ?? ucfirst($nick)),
            'login_uri' => $options['login_uri'] ?? self::login_uri($robust),
            'web_url' => $weburl,
            'mail_sender' => $options['mail_sender'] ?? null,
            'options' => $options,
            'databases' => $databases,
            'base_path' => $base,
            'urls' => $helpers['Urls'] ?? [],
        ];
    }

    /**
     * The public path of a service of the helpers: the one helpers.ini gives in [Urls], else the
     * script under the base path (`/helpers/query.php`).
     *
     * @param  array  $settings what settings() gave
     * @param  string $service  the name of the service (search) or its script (query.php)
     * @return string
     */
    public static function script_path($settings, $service)
    {
        $script = self::SERVICES[$service] ?? $service;
        $name = array_search($script, self::SERVICES, true);

        return ($name !== false ? $settings['urls'][$name] ?? null : null) ?? $settings['base_path'] . '/' . $script;
    }

    /**
     * The routes of the services the grid gives a path of their own to ([Urls] of helpers.ini): path => script or page.
     *
     * @param  array $settings what settings() gave
     * @return array<string,string>
     */
    public static function routes($settings)
    {
        $routes = [];
        foreach ($settings['urls'] as $service => $path) {
            if (isset(self::SERVICES[$service]) && trim($path) !== '') {
                $routes['/' . trim($path, '/')] = self::SERVICES[$service];
            }
        }

        return $routes;
    }

    /**
     * The constants the helper scripts expect: what the grid says, the helpers have a default for the rest.
     *
     * @param  array $settings what settings() gave
     * @return array<string,mixed> name => value
     */
    public static function constants($settings)
    {
        $o = $settings['options'];
        $db = $settings['databases'];
        $constants = [
            'OPENSIM_GRID_NAME' => $settings['grid_name'],
            'OPENSIM_LOGIN_URI' => $settings['login_uri'],
            'ROBUST_DB' => true,
            'OPENSIM_DB' => true,
            'OPENSIM_ROUTES' => self::routes($settings),
        ];
        $given = [
            'OPENSIM_USE_UTC_TIME' => isset($o['use_utc_time']) ? self::flag($o['use_utc_time']) : null,
            'OPENSIM_MAIL_SENDER' => $o['mail_sender'] ?? null,
            'OPENSIM_MOTD' => $o['motd'] ?? null,
            'OPENSIM_GRID_LOGO_URL' => $o['grid_logo_url'] ?? null,
            'HYPEVENTS_URL' => isset($o['events_url']) ? rtrim($o['events_url'], '/') : null,
            'CURRENCY_USE_MONEYSERVER' => isset($o['currency_use_moneyserver'])
                ? self::flag($o['currency_use_moneyserver'])
                : null,
            'CURRENCY_SCRIPT_KEY' => $o['currency_script_key'] ?? null,
            'CURRENCY_RATE' => $o['currency_rate'] ?? null,
            'CURRENCY_RATE_PER' => $o['currency_rate_per'] ?? null,
            'CURRENCY_PROVIDER' => $o['currency_provider'] ?? null,
            'CURRENCY_HELPER_URL' =>
                $o['currency_helper_url'] ??
                ($settings['web_url'] === ''
                    ? null
                    : $settings['web_url'] . self::script_path($settings, 'currency.php')),
        ];
        foreach ($given as $name => $value) {
            if ($value !== null) {
                $constants[$name] = $value;
            }
        }
        foreach (
            [
                'ROBUST' => 'robust_db',
                'OPENSIM' => 'opensim_db',
                'SEARCH' => 'search_db',
                'CURRENCY' => 'currency_db',
                'OFFLINE' => 'offline_db',
            ]
            as $prefix => $name
        ) {
            $constants["{$prefix}_DB_HOST"] = $db[$name]['hostname'];
            $constants["{$prefix}_DB_NAME"] = $db[$name]['prefix'];
            $constants["{$prefix}_DB_USER"] = $db[$name]['user'];
            $constants["{$prefix}_DB_PASS"] = $db[$name]['password'];
        }

        return $constants;
    }

    /**
     * Define the constants of constants(), those that are not defined already.
     *
     * @param  array $settings
     * @return void
     */
    public static function define_constants($settings)
    {
        foreach (self::constants($settings) as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    /**
     * The settings of a database from a connection string of OpenSimulator
     * ("Data Source=host;Database=name;User ID=user;Password=pass;Old Guids=true;").
     *
     * @param  string $string
     * @return array{hostname:?string,prefix:?string,user:?string,password:?string}
     */
    public static function connection($string)
    {
        $values = [];
        foreach (explode(';', $string) as $pair) {
            if (strpos($pair, '=') !== false) {
                [$key, $value] = explode('=', $pair, 2);
                $values[strtolower(trim($key))] = trim($value);
            }
        }

        return [
            'hostname' => $values['data source'] ?? ($values['server'] ?? null),
            'prefix' => $values['database'] ?? null,
            'user' => $values['user id'] ?? ($values['uid'] ?? null),
            'password' => $values['password'] ?? ($values['pwd'] ?? null),
        ];
    }

    /** The address viewers log in to: the gatekeeper of a Hypergrid grid, else its public address. */
    private static function login_uri($robust)
    {
        $uri = $robust['Hypergrid']['GatekeeperURI'] ?? '';
        if ($uri === '' && isset($robust['Const']['BaseURL'], $robust['Const']['PublicPort'])) {
            $uri = $robust['Const']['BaseURL'] . ':' . $robust['Const']['PublicPort'];
        }

        return $uri === '' ? null : $uri;
    }

    private static function flag($value)
    {
        return !in_array(strtolower((string) $value), ['', '0', 'false', 'no', 'off'], true);
    }
}
