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
        // note that public files will now NOT be copied into the apache folder. only index and .htaccess will be synced now.
        $legacyRoot = getenv("ABET_PUBLIC_DIR");
        // $legacyRoot = __DIR__.'/../../public';

        $filepath = "{$legacyRoot}{$requestPathInfo}";
        /**
         * SECURITY: Prevent domain traversal
         * WARNING: DO NOT use $filepath! This is dangerous!
         * use $resolved path AFTER here!!
         */
        // obvious attacks begone
        if (str_contains($requestPathInfo, "\0") || str_contains($requestPathInfo, '..')) {
            throw new \Exception('Invalid path');
        }
        // Resolve to absolute canonical path
        $resolvedPath = realpath($filepath);
        // check if they are in legacyRoot. Else reject.
        if ($resolvedPath === false || strncmp($resolvedPath, $legacyRoot, strlen($legacyRoot)) !== 0) {
            #throw new \Exception('Invalid path');
            throw new \Exception("Invalid path: {$legacyRoot} + {$requestPathInfo} == {$filepath} or {$resolvedPath}");
        }

        // Example 
        if ($requestPathInfo == '/') {
            return "{$legacyRoot}/home.php";
        }

        //TODO: add pretty rewrites in .htaccess
        // Example 
        if (is_file($filepath)) {
            return "{$filepath}";
        }

        // Need to only show in DEVELOPMENT
        var_dump(phpinfo());
        var_dump($requestPathInfo);
        var_dump($legacyRoot);

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