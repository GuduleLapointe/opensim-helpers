<?php
/**
 * includes/functions.php
 *
 * Provides functions required by opensimulator helpers
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link		https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

use PhpXmlRpc\Encoder;

// Only when the extension is missing: the functions of a file are declared when it is compiled, it cannot guard itself
if (!function_exists('xmlrpc_encode')) {
    require_once __DIR__ . '/xmlrpc-polyfill.php';
}

/**
 * Verify if given string is an UUID.
 * In theory, we would check want v4-compliant uuids
 * (xxxxxxxx-xxxx-4xxx-[89AB]xxx-xxxxxxxxxxxx) but OpenSimulator seems to have
 * lot of non v4-compliant uuids left, so stict defaults to false.
 *
 * @param  [type]  $uuid                 string to verify
 * @param  boolean $nullok               accept null value or null key as valid (default false)
 * @param  boolean $strict               apply strict UUID v4 implentation (default false)
 * @return boolean
 */
function opensim_isuuid($uuid, $nullok = false, $strict = false)
{
    if ($uuid == null) {
        return $nullok;
    }
    if (defined('NULL_KEY') && $uuid == NULL_KEY) {
        return $nullok;
    }

    if ($strict) {
        return preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $uuid);
    } else {
        return preg_match('/^[0-9A-F]{8,8}-[0-9A-F]{4,4}-[0-9A-F]{4,4}-[0-9A-F]{4,4}-[0-9A-F]{12,12}$/i', $uuid);
    }
}

/**
 * Sanitize a destination URI or URL
 *
 * @param  string  $url				url or uri or tp link (secondlife:// url, hop:// url, region name...)
 * @param  string  $default_gatekeeper	default login uri to add to urls sithout host:port
 * @param  boolean $outputArray		output as array
 * @return string       (default)                   $host:$port $region/$pos
 *           or array                                           array($host, $port, $region, $pos)
 */
function opensim_parse_url($url, $default_gatekeeper = null): array
{
    $e_scheme = '(.*://)';
    $e_host = '(([A-Za-z0-9_-]+)(\.[A-Za-z0-9\._-]+)+)';
    $e_short_host = '(([A-Za-z0-9_-]+)(\.[A-Za-z0-9\._-]+)*)?';
    $e_port = '[:\|]([0-9]+)';
    $e_float = '(\d+(\.\d+)?)';

    $normalized_url = preg_replace(
        ["#^{$e_scheme}?{$e_host}{$e_port}?[:\+_ ]#", "#^{$e_scheme}?{$e_short_host}{$e_port}[:\+_ ]#", '/(\+|%20|_)/'],
        ["\\1\\2:\\5/", "\\1\\2:\\5/", ' '],
        urldecode(trim($url)),
    );

    if (!preg_match("#^$e_scheme#", $url) && preg_match("#$e_host|$e_short_host{$e_port}#", $url)) {
        $parsed = parse_url('http://' . $normalized_url) ?: [];
        unset($parsed['scheme']);
    } else {
        $parsed = parse_url($normalized_url) ?: [];
    }

    if (!empty($parsed['host']) || !empty($default_gatekeeper)) {
        $parsed['host'] ??= empty($default_gatekeeper) ? null : parse_url($default_gatekeeper, PHP_URL_HOST) ?? null;
        $parsed['host'] = strtolower(trim($parsed['host']));
        $parsed['port'] ??= parse_url($default_gatekeeper ?? '', PHP_URL_PORT) ?? 80;
        $parsed['gatekeeper'] = 'http://' . $parsed['host'] . ':' . $parsed['port'];
    }

    $split_path = array_values(array_filter(explode('/', $parsed['path'] ?? '')));

    if (count($split_path) > 0) {
        if (!preg_match("/^$e_float$/", $split_path[0])) {
            $parsed['region'] = array_shift($split_path);
        }

        // Sanitize remaining items for position
        $split_path = array_map(
            'floatval',
            array_slice(array_filter($split_path, fn($item) => is_numeric($item)), 0, 3),
        );
        if (count($split_path) >= 2) {
            $parsed['pos'] = implode('/', $split_path);
        }
    }

    if (!empty($parsed['host'])) {
        $parsed['region_uri'] =
            $parsed['host'] . (empty($parsed['host']) || empty($parsed['port']) ? '' : ':' . $parsed['port']);
    }
    $parsed['region_uri'] = trim(
        ($parsed['region_uri'] ?? '') . (empty($parsed['region']) ? '' : '/') . ($parsed['region'] ?? ''),
        ":/ \n\r\t\v\x00",
    );
    $parsed['dest_uri'] = $parsed['region_uri'] . (empty($parsed['pos']) ? '' : "/$parsed[pos]");

    return $parsed;
}

/**
 * Sanitize a destination URI or URL
 *
 * @param  string  $url				url or uri or tp link (secondlife:// url, hop:// url, region name...)
 * @param  string  $default_gatekeeper	default login uri to add to urls sithout host:port
 * @return string					$host:$port $region/$pos
 */
function opensim_uri($url, $default_gatekeeper = null): string
{
    $parsed = opensim_parse_url($url, $default_gatekeeper);
    return $parsed['dest_uri'] ?? '';
}

/**
 * Deprecated: use opensim_uri() instead
 *
 * @deprecated since #c9ee461 2025-05-05
 */
function opensim_sanitize_uri($url, $default_gatekeeper = null, $outputArray = false): string|array
{
    if ($outputArray) {
        return opensim_parse_url($url, $default_gatekeeper);
    }
    return opensim_uri($url, $default_gatekeeper);
}

/**
 * Format destination uri as a valid local or hypergrid link url
 *
 * @param  string  $destination      Destination uri, as "host:port:Region Name" or already formatted URL
 * @param  integer $format  The desired format as binary flags. Several values can be specified with an addition
 *                          e.g. TPLINK_V3HG + TPLINK_APPTP
 *                          TPLINK_LOCAL or 1:   secondlife://Region Name/x/y/z
 *                          TPLINK_HG or 2:      original HG format (obsolete?)
 *                          TPLINK_V3HG or 4:    v3 HG format (Singularity)
 *                          TPLINK_HOP or 8:     hop:// format (FireStorm)
 *                          TPLINK_TXT or 16:    host:port Region Name
 *                          TPLINK_APPTP or 32:  secondlife:///app/teleport link
 *                          TPLINK_MAP or 64:    (map, not implemented)
 * 							128:                 (for future use, not implemented)
 *                          TPLINK or 255:       output all formats
 *                          TPLINK_ARRAY or 256: output all formats as array
 *
 * @param  string  $sep      Separator for multiple formats, default new line
 * @return string
 */
function opensim_format_tp($destination, $format = null, $sep = "\n"): string|array
{
    if (empty($destination)) {
        return $format & TPLINK_ARRAY ? [] : '';
    }
    // TODO: allow Region, Destination or Event classes
    // if (is_object($destination) && !empty($destination->uri)) {
    // 	$destination = $destination->uri;
    // }
    $format ??= TPLINK_DEFAULT;

    $parsed = opensim_parse_url($destination);
    extract($parsed);

    $post_split = explode('/', $pos ?? '');
    if (count($post_split) >= 2) {
        $pos_x = $post_split[0];
        $pos_y = $post_split[1];

        // set $pos_sl to $pos only if $pos_x and $pos_y < 256
        $pos_sl = $pos_x < 256 && $pos_y < 256 ? $pos : null;
    }

    $region_urlencode = urlencode($region ?? '');
    $region_percentencode = str_replace('+', '%20', $region_urlencode);

    $links = [];
    if (empty($host) && empty($region)) {
        return $format & TPLINK_ARRAY ? [] : '';
    }

    if ($format & TPLINK_TXT) {
        $links[TPLINK_TXT] = trim(
            (empty($host) ? '' : join(':', [$host, $port])) . ' ' . ($region ?? '') . (empty($pos) ? '' : "/$pos"),
        );
    }
    if ($format & TPLINK_LOCAL) {
        // Web only, do not use for in-world messages
        $links[TPLINK_LOCAL] = "secondlife://$region_percentencode" . (empty($pos_sl) ? '' : "/$pos_sl");
    }
    if ($format & TPLINK_HG) {
        // if(empty())
        // Web only, do not use for in-world messages
        $links[TPLINK_HG] = empty($host)
            ? $links[TPLINK_LOCAL]
            : "secondlife://$host:$port%20$region_percentencode" . (empty($pos_sl) ? '' : "/$pos_sl");
    }
    if ($format & TPLINK_V3HG) {
        // Web only, do not use for in-world messages
        $links[TPLINK_V3HG] =
            (empty($host) ? $links[TPLINK_LOCAL] : "secondlife://http|!!$host|$port%20$region_percentencode") .
            (empty($pos_sl) ? '' : "/$pos_sl");
    }
    if ($format & TPLINK_HOP) {
        // Web and in-world. Position (/x/y/z) is
        // - optional for web browser links (viewer applies default)
        // - required for in-world messages
        $links[TPLINK_HOP] = empty($gatekeeper)
            ? ''
            : trim("hop://$host:$port/$region_urlencode", '/') . (empty($pos) ? '' : "/$pos");
    }
    if ($format & TPLINK_APPTP) {
        // In-world messages, do not use for web links
        // secondlife:///app/teleport/speculoos:8002+Grand+Place/" .
        $links[TPLINK_APPTP] =
            (empty($host)
                ? "secondlife:///app/teleport/$region_urlencode/"
                : "secondlife:///app/teleport/$host:$port+$region_urlencode/") . (!empty($pos_sl) ? "$pos_sl/" : '');
    }
    // TODO: Alternative web url when maps implemented in API
    // (No direct map slurl support in the viewer)
    // Example map URLs (TBD in API)
    //  - API_HOST/api/v3/map/$host:$port/$region/128/64/32/
    //  - API_HOST/maps/$host:$port/$region/128/64/32/
    //  - WEB_HOST/maps/$host:$port/$region/128/64/32/
    // if ($format & TPLINK_MAP) {
    // }

    // clean up trailing/leading non-alphanumeric characters
    $links = preg_replace('#^[^[:alnum:]]*|[^[:alnum:]]+$#', '', $links);

    if ($format & TPLINK_ARRAY) {
        return $links;
    }
    return join($sep, $links);
}

/**
 * Use xmlrpc link_region method to request link_region data from robust
 *
 * @param  mixed  $args   region uri or sanitized region array
 * @param  string $var      output a single variable value
 * @return array (or string if var specified)
 * Array format:
 *  - uuid: UUID of the region
 *
 */
function opensim_link_region($args, $var = null)
{
    if (empty($args)) {
        error_log('DEBUG ' . __FUNCTION__ . ' empty args');
        return [];
    }
    global $OSSEARCH_CACHE;

    if (is_string($args)) {
        $region_array = opensim_parse_url($args);
    } elseif (is_array($args)) {
        if (!empty($args['region_uri'])) {
            $region_array = $args;
        } elseif ($args['host'] || $args['region']) {
            $tryurl = join('/', [join(':', [$args['host'] ?? '', $args['port'] ?? '']), $args['region'] ?? '']);

            $region_array = opensim_parse_url($tryurl);
        }
    }
    if (!$region_array) {
        return [
            'success' => false,
            'errorMessage' => 'could not parse region url from args: ' . print_r($args, true),
            'data' => $region_array,
            'args' => $args,
        ];
    }
    extract($region_array); // $host, $port, $region, $pos, $gatekeeper, $region_uri, $dest_uri
    if (empty($gatekeeper)) {
        // TODO: implemeent default gatekeeper and apply if region name is provided alone
        return [
            'success' => false,
            'errorMessage' => 'no gatekeeper',
            'data' => $region_array,
            'args' => $args,
        ];
    }
    if (empty($region_uri)) {
        return [
            'success' => false,
            'errorMessage' => 'could not parse region uri from region_array: ' . print_r($region_array, true),
            'data' => $region_array,
            'args' => $args,
        ];
    }
    $gatekeeper = preg_match('#://#', $gatekeeper) ? $gatekeeper : "http://$gatekeeper";

    if (isset($OSSEARCH_CACHE['link_region'][$region_uri])) {
        $link_region = $OSSEARCH_CACHE['link_region'][$region_uri];
    } else {
        $link_region = oxXmlRequest($gatekeeper, 'link_region', [
            'region_name' => $region ?? '',
        ]);
        $OSSEARCH_CACHE['link_region'][$region_uri] = $link_region;
    }

    if ($link_region) {
        if ($var) {
            return $link_region[$var];
        } else {
            return $link_region;
        }
    }

    return [];
}

/**
 * Build region URL from array
 *
 * @param  array $region sanitized region array
 * @return string
 */
function opensim_region_url($region)
{
    if (!is_array($region)) {
        return false;
    }
    return $region['gatekeeper'] .
        (empty($region['region']) ? '' : ':' . $region['region']) .
        (empty($region['pos']) ? '' : '/' . $region['pos']);
}

/**
 * Get region data from region name or UUID
 *
 * @param  string|array $region region name or UUID or sanitized region array
 * @param  string $var   optional variable to return
 * @return array
 */
function opensim_get_region($region, $var = null)
{
    if (empty($region)) {
        return [
            'errorCode' => 400,
            'error' => 'Empty region',
        ];
    }
    if (opensim_isuuid($region)) {
        // Not implemented, UUID lookup would require a default gatekeeper
        return [
            'errorCode' => 501,
            'error' => 'UUID lookup not implemented',
        ];
    }
    global $OSSEARCH_CACHE;

    $region = opensim_parse_url($region);
    $link_region = opensim_link_region($region);
    if (!opensim_isuuid($link_region['uuid'])) {
        return [
            'errorCode' => 400,
            'error' => 'Invalid region UUID',
        ];
    }

    if (empty($region['gatekeeper'])) {
        return [
            'errorCode' => 400,
            'error' => 'Empty gatekeeper',
        ];
    }
    extract($region);
    $gatekeeper = preg_match('#://#', $gatekeeper) ? $gatekeeper : "http://$gatekeeper";

    $uuid = $link_region['uuid'] ?? false;
    if (isset($OSSEARCH_CACHE['get_region'][$uuid])) {
        $get_region = $OSSEARCH_CACHE['get_region'][$uuid];
    } else {
        $get_region = oxXmlRequest($gatekeeper, 'get_region', [
            'region_uuid' => "$uuid",
        ]);
        $OSSEARCH_CACHE['get_region'][$uuid] = $get_region;
    }

    if ($get_region) {
        if ($var) {
            return $get_region[$var];
        } else {
            return $get_region;
        }
    }
    return [];
}

/**
 * Check if region is online
 *
 * @param  mixed $region   region uri or sanitized region array
 * @return boolean                  true if online
 */
function opensim_region_is_online($region)
{
    $link_region = opensim_link_region($region);

    // About `result = "True"` vs `uuid != NULL_KEY`
    //
    // In some circumstances, maybe one of them will be matched
    // and the other will not, while region is found but offline.
    // In doubt, rely on semantics: "result" = "True" means a result
    // was found, not specifically that it's a good result.
    //
    // handle or uuid are better indicators of a good result.

    return opensim_isuuid($link_region['uuid'] ?? false);
}

function opensim_user_alert($agentID, $message, $secureID = null)
{
    $agentServer = opensim_get_server_info($agentID);
    if (!$agentServer) {
        return false;
    }
    $serverip = $agentServer['serverIP'];
    $httpport = $agentServer['serverHttpPort'];
    $serveruri = $agentServer['serverURI'];

    $avatarSession = opensim_get_avatar_session($agentID);
    if (!$avatarSession) {
        return false;
    }
    $sessionID = $avatarSession['sessionID'];
    if ($secureID == null) {
        $secureID = $avatarSession['secureID'];
    }

    $request = xmlrpc_encode_request('UserAlert', [
        [
            'clientUUID' => $agentID,
            'clientSessionID' => $sessionID,
            'clientSecureSessionID' => $secureID,
            'Description' => $message,
        ],
    ]);
    $response = currency_xmlrpc_call($serverip, $httpport, $serveruti, $request);

    return $response;
}

/**
 * [oxXmlRequest description]
 *
 * @param  string $gatekeeper               [description]
 * @param  string $method                   [description]
 * @param  array  $request                  [description]
 * @return array             received xml response
 */
function oxXmlRequest($gatekeeper, $method, $request)
{
    $xml_request = xmlrpc_encode_request($method, [$request]); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: text/xml' . "\r\n",
            'timeout' => 3, // most of the time below 1 sec, but leave some time for slow ones
            'content' => $xml_request,
        ],
    ]);

    $response = @file_get_contents($gatekeeper, false, $context);
    if ($response === false) {
        return false;
    } elseif (empty($response)) {
        return false;
    }

    // xmlrpc_decode() from library-xmlrpc.php does not handle raw XML strings
    // (new Response($xml) does not parse XML — it is a phpxmlrpc limitation).
    // Use Encoder::decodeXml() directly, which correctly parses any XML-RPC envelope.
    // decodeXml() returns a PhpXmlRpc\Response object; extract the PHP array from it.
    try {
        $encoder = new \PhpXmlRpc\Encoder();
        $decoded = $encoder->decodeXml($response);
    } catch (\Throwable $e) {
        error_log("oxXmlRequest decode error ($gatekeeper $method): " . $e->getMessage());
        return false;
    }
    if (empty($decoded)) {
        return false;
    }
    if ($decoded instanceof \PhpXmlRpc\Response) {
        if ($decoded->faultCode()) {
            error_log(
                "oxXmlRequest fault ($gatekeeper $method): " . $decoded->faultCode() . ' ' . $decoded->faultString(),
            );
            return false;
        }
        $xml_array = $encoder->decode($decoded->value());
    } else {
        $xml_array = $decoded;
    }
    if (empty($xml_array) || !is_array($xml_array)) {
        return false;
    }
    if (xmlrpc_is_fault($xml_array)) {
        // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions
        return false;
    }
    return $xml_array;
}

function osXmlResponse($success = true, $errorMessage = false, $data = false)
{
    if (is_array($data)) {
        $array = [
            'success' => $success,
            'errorMessage' => $errorMessage,
        ];
        // A success always has its list, empty when there is no result: the OpenSimSearch module reads it
        if ($success || !empty($data)) {
            $array['data'] = $data;
        }
        $response_xml = xmlrpc_encode($array); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions
        echo $response_xml;
        return;
    }
    if ($success) {
        $answer = new SimpleXMLElement('<boolean>true</boolean>');
    } else {
        $answer = new SimpleXMLElement("<error>$errorMessage</error>");
    }
    echo $answer->asXML();
}

function osXmlDie($message = '')
{
    osXmlResponse(false, $message, []);
    die();
}

function osNotice($message)
{
    echo $message . "\n";
}

function osAdminNotice($message, $error_code = 0, $die = false)
{
    // get calling function and file
    $trace = debug_backtrace();

    if (isset($trace[1])) {
        $caller = $trace[1];
    } else {
        $caller = $trace[0];
    }
    $file = empty($caller['file']) ? '' : $caller['file'];
    $function = $caller['function'] . '()' ?? 'main';
    $line = $caller['line'] ?? 0;
    $class = $caller['class'] ?? 'main';
    $type = $caller['type'] ?? '::';
    if ($class != 'main') {
        $function = $class . $type . $function;
    }
    $file = $file . ':' . $line;
    $message = sprintf('%s%s: %s in %s', $function, empty($error_code) ? '' : " Error $error_code", $message, $file);
    error_log($message);
    if ($die == true) {
        die($error_code);
    }
}

/**
 * Flush output and free client so following commands are executed in background
 *
 * @return void
 */
function dontWait()
{
    $size = ob_get_length();

    header("Content-Length:$size");
    header('Connection:close');
    header('Content-Encoding: none');
    header('Content-Type: text/html; charset=utf-8');

    ob_flush();
    ob_end_flush();
    flush();
}

if (!function_exists('osdebug')) {
    function osdebug($message = '')
    {
        if (empty($message)) {
            return;
        }
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        error_log('[DEBUG] ' . $message);
        echo $message . "\n";
    }
}

function set_helpers_locale($locale = null, $domain = 'messages')
{
    mb_internal_encoding('UTF-8');
    $encoding = mb_internal_encoding();

    if (isset($_GET['l'])) {
        $locale = $_GET['l'];
    }
    $languages = array_filter(array_merge([$locale], explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])));

    // $results = putenv("LC_ALL=$locale");
    // if (!$results) {
    // exit ('putenv failed');
    // }

    // $currentLocale = setlocale(LC_ALL, 0);
    $user_locales = array_unique([$locale, $locale . ".$encoding", $locale . '.UTF-8', $locale . '.utf8', $locale, 0]);

    $user_locales = array_map(function ($code) {
        return preg_replace(['/;.*/', '/-/'], ['', '_'], $code);
    }, $languages);

    // Generate variants with different encodings appended
    $variants = [];
    foreach ($user_locales as $lang) {
        $variants[] = $lang;
        $variants[] = "$lang.$encoding";
        // $variants[] = "$lang.UTF-8";
    }

    $variants = array_unique($variants);
    if (!setlocale(LC_ALL, $variants)) {
        // error_log( "setlocale() failed: none of  '" . join( ', ', $variants ) . "' does exist in this environment or setlocale() is not available on this platform" );
        setlocale(LC_ALL, 0);
        return 0;
    }

    bindtextdomain($domain, './locales');
    textdomain($domain);
}

function get_writable_tmp_dir()
{
    if (isset($_GLOBALS['tmp_dir'])) {
        return $_GLOBALS['tmp_dir'];
    }
    $dirs = [sys_get_temp_dir(), ini_get('upload_tmp_dir'), '/tmp', '/var/tmp', '/usr/tmp', '.'];
    foreach ($dirs as $dir) {
        if (@is_writable($dir)) {
            $_GLOBALS['tmp_dir'] = $dir;
            return $dir;
        }
    }
    error_log(
        __FILE__ .
            ':' .
            __LINE__ .
            ' ERROR - could not find a writable temporary directory, check web server and PHP config',
    );
    return false;
    // return '/tmp';
}

function os_cache_get($key, $default = null)
{
    global $oshelpers_cache;
    return isset($oshelpers_cache[$key]) ? $oshelpers_cache[$key] : $default;
}

function os_cache_set($key, $value, $expire = 0)
{
    global $oshelpers_cache;
    $oshelpers_cache[$key] = $value;
}

/**
 * Defines the constants used by the OpenSim helpers library.
 *
 * Hard constants (cannot be overridden by environment variables)
 */
$hard_constants = [
    'NULL_KEY' => '00000000-0000-0000-0000-000000000000',
    'HELPERS_LOCALE_DIR' => dirname(__DIR__) . '/languages',
    'TPLINK_LOCAL' => 1, // secondlife://Region Name/x/y/z
    'TPLINK_HG' => 2, // original HG format (obsolete?)
    'TPLINK_V3HG' => 4, // the overcomplicated stuff! Should be deprecated
    'TPLINK_HOP' => 8, // hop://yourgrid.org:8002:Region/x/y/z (FireStorm)
    'TPLINK_TXT' => 16, // host:port Region Name
    'TPLINK_APPTP' => 32, // secondlife:///app/teleport/host:port+Region%20Name/x/y/z
    'TPLINK_MAP' => 64, // (map, not implemented)
    'TPLINK' => 255, // all formats
    'TPLINK_ARRAY' => 256, // output as array
];
foreach ($hard_constants as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

/**
 * Soft constants (can be overridden by environment variables)
 */
$soft_constants = [
    'TPLINK_DEFAULT' => TPLINK_HOP,
];
foreach ($soft_constants as $name => $value) {
    if (!defined($name)) {
        define($name, getenv($name) ?: $value);
    }
}

/**
 * OpenSim source to help further attempts to allow Hypergrid search results.
 * Infouuid is a fake parcelid resolving to region handle and (region-level?)
 * pos which might (or not) give enough information to allow hg results.
 * 1. Link region locally with link-region (or directly in db?)
 * 2. Use local link region handle (instead of remote one) to generate infouuid
 * 3. Use local link Global pos instead of remote one
 */
//
// public static UUID BuildFakeParcelID(ulong regionHandle, uint x, uint y)
// {
// byte[] bytes =
// {
// (byte)regionHandle, (byte)(regionHandle >> 8), (byte)(regionHandle >> 16), (byte)(regionHandle >> 24),
// (byte)(regionHandle >> 32), (byte)(regionHandle >> 40), (byte)(regionHandle >> 48), (byte)(regionHandle >> 56),
// (byte)x, (byte)(x >> 8), 0, 0,
// (byte)y, (byte)(y >> 8), 0, 0 };
// return new UUID(bytes, 0);
// }
//
// public static UUID BuildFakeParcelID(ulong regionHandle, uint x, uint y, uint z)
// {
// byte[] bytes =
// {
// (byte)regionHandle, (byte)(regionHandle >> 8), (byte)(regionHandle >> 16), (byte)(regionHandle >> 24),
// (byte)(regionHandle >> 32), (byte)(regionHandle >> 40), (byte)(regionHandle >> 48), (byte)(regionHandle >> 56),
// (byte)x, (byte)(x >> 8), (byte)z, (byte)(z >> 8),
// (byte)y, (byte)(y >> 8), 0, 0 };
// return new UUID(bytes, 0);
// }
