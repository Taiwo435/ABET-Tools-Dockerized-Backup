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

        // check if they are in legacyRoot. Else reject.

        //TODO: add pretty rewrites in .htaccess
        // Example 
        if ($requestPathInfo == '/') {
            return "{$legacyRoot}/home.php";
        }

        LegacyBridge::doSecurityChecks($legacyRoot, $requestPathInfo);
        // Resolve to absolute canonical path
        $resolvedPath = realpath("{$legacyRoot}{$requestPathInfo}");

        if (is_file($resolvedPath)) {
            return "{$resolvedPath}";
        }

        // Need to only show in DEVELOPMENT
        var_dump(phpinfo());
        var_dump($requestPathInfo);
        var_dump($legacyRoot);

        // ... etc.

        throw new \Exception("Unhandled legacy mapping for $requestPathInfo");
    }

        /**
         * SECURITY: Prevent domain traversal
         * WARNING: DO NOT use $filepath! This is dangerous!
         * use $resolved path AFTER here!!
         */

    /**
     * SECURITY: Prevent domain traversal
     * Helper function to prevent attackers from accessing any file they want.
     * @param mixed $legacyRoot
     * @param mixed $requestPathInfo
     * @throws \Exception
     * @return void
     */
    private static function doSecurityChecks($legacyRoot, $requestPathInfo) {

        // obvious attacks begone
        if (str_contains($requestPathInfo, "\0") || str_contains($requestPathInfo, '..')) {
            throw new \Exception('Invalid path');
        }

        // absolute canonical path
        $filepath = "{$legacyRoot}{$requestPathInfo}";
        $resolvedPath = realpath($filepath);

        // NOTE: security auditors can easily log attack attempts if $resolvedPath is valid but is outside of $legacyRoot
        if ($resolvedPath === false || strncmp($resolvedPath, $legacyRoot, strlen($legacyRoot)) !== 0) {
            #throw new \Exception('Invalid path');
            throw new \Exception("Invalid path: {$legacyRoot} + {$requestPathInfo} == {$filepath} or {$resolvedPath}");
        }

        $allowedExtensions = ['php'];

        $ext = pathinfo($resolvedPath, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new \Exception('Forbidden file type');
        }
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