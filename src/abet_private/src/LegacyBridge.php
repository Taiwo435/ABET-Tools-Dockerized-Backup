<?php
namespace App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyBridge
{

    /**
     * Map the incoming request to the right file. This is the
     * key function of the LegacyBridge.
     *
     * Sample code only. Your implementation will vary, depending on the
     * architecture of the legacy code and how it's executed.
     *
     * If your mapping is complicated, you may want to write unit tests
     * to verify your logic, so this method is public static.
     */
    public static function getLegacyScript(Request $request): string
    {
        $requestPathInfo = $request->getPathInfo();
        $legacyRoot = __DIR__ . '/../public/';

        // Map a route to a legacy script:
        if ($requestPathInfo == '/customer/') {
            return "{$legacyRoot}src/customers/list.php";
        }

        // Map a direct file call, e.g. an ajax call:
        if ($requestPathInfo == 'inc/ajax_cust_details.php') {
            return "{$legacyRoot}inc/ajax_cust_details.php";
        }

        // default please
        if ($requestPathInfo == '/') {
            return "{$legacyRoot}homepage.php";
        }

        // ... etc.

        throw new \Exception("Unhandled legacy mapping for $requestPathInfo");
    }

    public static function handleRequest(Request $request, Response $response, string $publicDirectory): void
    {
        $legacyScriptFilename = LegacyBridge::getLegacyScript($request);

        // Possibly (re-)set some env vars (e.g. to handle forms
        // posting to PHP_SELF):
        $p = $request->getPathInfo();
        $_SERVER['PHP_SELF'] = $p;
        $_SERVER['SCRIPT_NAME'] = $p;
        $_SERVER['SCRIPT_FILENAME'] = $legacyScriptFilename;

        require $legacyScriptFilename;
    }
}