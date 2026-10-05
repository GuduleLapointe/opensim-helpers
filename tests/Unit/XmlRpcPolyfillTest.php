<?php
/**
 * The xmlrpc_* functions of the library standing for the extension, as query.php, currency.php and the others use them.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
// Where the extension is, its functions answer instead, which is what the polyfill imitates
if (!function_exists('xmlrpc_encode')) {
    require_once dirname(__DIR__, 2) . '/includes/xmlrpc-polyfill.php';
}

function xmlrpc_double($method, $params, $app_data)
{
    // What OpenSim sends: one struct of strings; what it expects back: a struct
    return ['method' => $method, 'text' => $params[0]['text'] . '!', 'data' => $app_data];
}

it('serves a method with the signature of the extension, and answers with what it returns', function () {
    $server = xmlrpc_server_create();
    expect(xmlrpc_server_register_method($server, 'dir_places_query', 'xmlrpc_double'))->toBeTrue();

    $request =
        '<?xml version="1.0"?><methodCall><methodName>dir_places_query</methodName><params><param><value><struct>' .
        '<member><name>text</name><value><string>Welcome</string></value></member></struct></value></param></params></methodCall>';
    $level = ob_get_level();
    $handler = set_error_handler(static fn() => false);
    restore_error_handler();
    ob_start();
    xmlrpc_server_call_method($server, $request, 'the data');
    $response = ob_get_clean();
    // The library leaves its own buffers, and stacks the previous error handler on top of its own instead of
    // removing it: the test leaves what it found
    while (ob_get_level() > $level) {
        ob_end_clean();
    }
    if ($handler !== null) {
        restore_error_handler();
        restore_error_handler();
    }

    $answer = xmlrpc_decode($response);
    expect($answer['method'])
        ->toBe('dir_places_query')
        ->and($answer['text'])
        ->toBe('Welcome!')
        ->and($answer['data'])
        ->toBe('the data');
});

it('answers a fault for a method that is not registered', function () {
    $server = xmlrpc_server_create();
    ob_start();
    xmlrpc_server_call_method(
        $server,
        '<?xml version="1.0"?><methodCall><methodName>nope</methodName><params></params></methodCall>',
        null,
    );
    $response = ob_get_clean();

    expect($response)->toContain('<fault>');
});

it('encodes a value as the XML parameters of a response, like the extension', function () {
    $xml = xmlrpc_encode(['success' => true]);

    expect($xml)->toBeString()->toContain('<params>')->toContain('<name>success</name>');
});
