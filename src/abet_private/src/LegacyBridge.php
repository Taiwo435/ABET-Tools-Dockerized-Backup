<?php
namespace App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
// all different codes found https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/HttpKernel/Exception/AccessDeniedHttpException.php
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception;

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
        
        // var_dump(get_loaded_extensions());
        // ----------------------------
        // .htaccess pretty paths but here
        // ----------------------------

        if ($requestPathInfo == '/') {
            return "{$legacyRoot}/auth/login.php";
        }

        // this is a template...
        if ($requestPathInfo == '/URI') {
        }

        // skipped RewriteCond %{THE_REQUEST} \s/+index\.php(?:[?\s]|$) [NC]

        // /home, /login, /logout are now handled natively by Symfony
        // (HomeController, SecurityController) — the front controller
        // matches those routes before ever falling back here, so these
        // legacy mappings would be unreachable dead code.

        if ($requestPathInfo == '/register') {
            return "{$legacyRoot}/auth/register.php";
        }

        if ($requestPathInfo == '/verify-email') {
            return "{$legacyRoot}/auth/verify_email.php";
        }

        if ($requestPathInfo == '/resend-verification') {
            return "{$legacyRoot}/auth/resend_verification.php";
        }

        // account profile stuff
        // NOTE: I had to change paths because some links ended with /
        if ($requestPathInfo == '/account/profile/') {
            return "{$legacyRoot}/account/profile/index.php";
        }

        if ($requestPathInfo == '/account/me/') {
            return "{$legacyRoot}/account/me/index.php";
        }

        if ($requestPathInfo == '/account/settings/') {
            return "{$legacyRoot}/account/settings/index.php";
        }

        if ($requestPathInfo == '/account/privacy/') {
            return "{$legacyRoot}/account/privacy/index.php";
        }

        if ($requestPathInfo == '/account/help/') {
            return "{$legacyRoot}/account/help/index.php";
        }

        // account actions
        if ($requestPathInfo == '/account/profile/update/') {
            return "{$legacyRoot}/account/profile/update.php";
        }

        if ($requestPathInfo == '/account/settings/email') {
            return "{$legacyRoot}/account/settings/email.php";
        }

        if ($requestPathInfo == '/account/settings/password/') {
            return "{$legacyRoot}/account/settings/password.php";
        }

        if ($requestPathInfo == '/account/privacy/consent/') {
            return "{$legacyRoot}/account/privacy/consent.php";
        }
        
        if ($requestPathInfo == '/account/privacy/export-data/') {
            return "{$legacyRoot}/account/privacy/export-data.php";
        }
        
        if ($requestPathInfo == '/account/privacy/delete-request/') {
            return "{$legacyRoot}/account/privacy/delete-request.php";
        }
        
        // faq
        if ($requestPathInfo == '/account/help/faq/') {
            return "{$legacyRoot}/account/help/faq.php";
        }
        
        // contact
        if ($requestPathInfo == '/account/help/contact/') {
            return "{$legacyRoot}/account/help/contact.php";
        }

        // TOOL 2: Faculty form
        if ($requestPathInfo == '/faculty-form/') {
            return "{$legacyRoot}/faculty-form/index.php";
        }
        if ($requestPathInfo == '/faculty-form/review/') {
            return "{$legacyRoot}/faculty-form/review/index.php";
        }
        if ($requestPathInfo == '/faculty-form/edit/') {
            return "{$legacyRoot}/faculty-form/edit/index.php";
        }
        // TOOL 3: Faculty form
        if ($requestPathInfo == '/coordinator-form/') {
            return "{$legacyRoot}/coordinator-form/index.php";
        }
        if ($requestPathInfo == '/coordinator-form/edit/') {
            return "{$legacyRoot}/coordinator-form/edit/index.php";
        }
        if ($requestPathInfo == '/coordinator-form/review/') {
            return "{$legacyRoot}/coordinator-form/review/index.php";
        }
        

        
        LegacyBridge::doSecurityChecks($legacyRoot, $requestPathInfo);
        // Resolve to absolute canonical path
        $resolvedPath = realpath("{$legacyRoot}{$requestPathInfo}");

        if (is_file($resolvedPath)) {
            return "{$resolvedPath}";
        }

        // Need to only show in DEVELOPMENT
        // var_dump(phpinfo());
        // var_dump("
        // <br>
        // request path: {$requestPathInfo}
        // <br>
        // fullPath: {$legacyRoot}{$requestPathInfo}
        // <br>
        // resolved path: {$resolvedPath}
        // ");
        // var_dump($requestPathInfo);
        // var_dump($legacyRoot);

        // ... etc.

        throw new NotFoundHttpException("Unhandled legacy mapping for $requestPathInfo");
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
            throw new NotFoundHttpException('Invalid path');
        }

        // absolute canonical path
        $filepath = "{$legacyRoot}{$requestPathInfo}";
        $resolvedPath = realpath($filepath);

        // NOTE: security auditors can easily log attack attempts if $resolvedPath is valid but is outside of $legacyRoot
        if ($resolvedPath === false || strncmp($resolvedPath, $legacyRoot, strlen($legacyRoot)) !== 0) {
            #throw new \Exception('Invalid path');
            throw new NotFoundHttpException("Invalid path: {$legacyRoot} + {$requestPathInfo} == {$filepath} or {$resolvedPath}");
        }

        $allowedExtensions = ['php', ''];

        $ext = pathinfo($resolvedPath, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new NotFoundHttpException('Forbidden file type');
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