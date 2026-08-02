<?php
namespace App;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
// all different codes found https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/HttpKernel/Exception/AccessDeniedHttpException.php
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception;
use Symfony\Bundle\SecurityBundle\Security;

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
    public static function getLegacyScript(Request $request, Security $security): string
    {
        $requestPathInfo = $request->getPathInfo();
        LegacyBridge::doAuthorizationChecks($security, $requestPathInfo);
        // note that public files will now NOT be copied into the apache folder. only index and .htaccess will be synced now.
        $legacyRoot = getenv("ABET_PUBLIC_DIR");

        // $legacyRoot = __DIR__.'/../../public';

        // check if they are in legacyRoot. Else reject.

        //TODO: add pretty rewrites in .htaccess

        // var_dump(get_loaded_extensions());
        // ----------------------------
        // .htaccess pretty paths but here
        // ----------------------------


	// Removed: / is now a native Symfony route (redirects to /login) (#132)
        //if ($requestPathInfo == '/') {
        //    return "{$legacyRoot}/auth/login.php";
        //}

        // this is a template...
        if ($requestPathInfo == '/URI') {
        }

        // skipped RewriteCond %{THE_REQUEST} \s/+index\.php(?:[?\s]|$) [NC]

        // #RewriteRule ^index\.php$ /home [R=302,L]
        if ($requestPathInfo == '/home') {
            return "{$legacyRoot}/home.php";
        }

	// Removed: /login is a native Symfony route
        // you probably can tell the pattern. Next line in .htaccess
        //if ($requestPathInfo == '/login') {
        //    return "{$legacyRoot}/auth/login.php";
        //}


        if ($requestPathInfo == '/register') {
            return "{$legacyRoot}/auth/register.php";
        }

	// Removed: /logout is a native Symfony route
        //if ($requestPathInfo == '/logout') {
        //    return "{$legacyRoot}/auth/logout.php";
        //}

        // account profile stuff
        // Removed: /account/profile/ now handled by AccountProfileController (#51)
        //if ($requestPathInfo == '/account/profile/') {
        //    return "{$legacyRoot}/account/profile/index.php";
        //}

	// Removed: /account/me/ now handled by AccountProfileController::directory() (#132)
        // if ($requestPathInfo == '/account/me/') {
        //    return "{$legacyRoot}/account/me/index.php";
        //}

	// Removed: /account/settings/ now handled by AccountSettingsController (#132)
        // if ($requestPathInfo == '/account/settings/') {
        //    return "{$legacyRoot}/account/settings/index.php";
        //}

        // Removed: /account/privacy/ now handled by AccountPrivacyController (#132)
        //if ($requestPathInfo == '/account/privacy/') {
        //    return "{$legacyRoot}/account/privacy/index.php";
        //}

	// Removed: /account/help/ now handled by AccountHelpController (#132)
        //if ($requestPathInfo == '/account/help/') {
        //    return "{$legacyRoot}/account/help/index.php";
        //}

        // Removed: /account/profile/update/ now handled by AccountProfileController (#132)
        //if ($requestPathInfo == '/account/profile/update/') {
        //    return "{$legacyRoot}/account/profile/update.php";
        // }

	// Removed: /account/settings/email/ now handled by AccountSettingsController (#132)
        //if ($requestPathInfo == '/account/settings/email') {
        //    return "{$legacyRoot}/account/settings/email.php";
        //}

	// Removed: /account/settings/password/ now handled by AccountSettingsController (#132)
        //if ($requestPathInfo == '/account/settings/password/') {
        //    return "{$legacyRoot}/account/settings/password.php";
        //}

        // Removed: /account/privacy/consent/ now handled by AccountPrivacyController (#132)
        //if ($requestPathInfo == '/account/privacy/consent/') {
        //    return "{$legacyRoot}/account/privacy/consent.php";
        //}

        // Removed: /account/privacy/export-data/ now handled by AccountPrivacyController (#132)
        //if ($requestPathInfo == '/account/privacy/export-data/') {
        //    return "{$legacyRoot}/account/privacy/export-data.php";
        //}

        // Removed: /account/privacy/delete-request/ now handled by AccountPrivacyController (#132)
        //if ($requestPathInfo == '/account/privacy/delete-request/') {
        //    return "{$legacyRoot}/account/privacy/delete-request.php";
        //}

	// Removed: /account/help/faq/ now handled by AccountHelpController (#132)
        // faq
        //if ($requestPathInfo == '/account/help/faq/') {
        //    return "{$legacyRoot}/account/help/faq.php";
        //}

	// Removed: /account/help/contact/ now handled by AccountHelpController (#132)
        // contact
        //if ($requestPathInfo == '/account/help/contact/') {
        //    return "{$legacyRoot}/account/help/contact.php";
        //}

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
     * #132: Symfony's native access_control cannot protect these paths —
     * they have no matching #[Route], so the router throws
     * NotFoundHttpException and the security firewall never runs. This is
     * the equivalent enforcement point for legacy-bridged requests.
     *
     * Runs at the very top of getLegacyScript(), before any of the
     * hardcoded path mappings below, so it applies uniformly regardless
     * of whether the request resolves via a hardcoded mapping or the
     * generic realpath() fallback.
     *
     * NOTE: This map does NOT yet cover every path under src/public — see
     * #132 for remaining work (/tools/tool1, /tools/tool2,
     * /tools/AdminPanel are intentionally not yet mapped here; they keep
     * relying on their existing in-file require_login()/require_role()
     * checks until their required roles are confirmed).
     */
    private static function doAuthorizationChecks(Security $security, string $requestPathInfo): void
    {
        $publicPrefixes = [
            '/',
            '/home', // /home is a native Symfony route already; harmless if ever hit here
            '/login',
            '/register',
            '/logout',
            '/auth/login.php',
            '/auth/register.php',
            '/auth/forgot_password.php',
            '/auth/forgot_password_sent.php',
            '/auth/reset_password.php',
            '/auth/reset_password_success.php',
        ];
        foreach ($publicPrefixes as $prefix) {
            if ($requestPathInfo === $prefix) {
                return;
            }
            if ($prefix !== '/' && str_starts_with($requestPathInfo, $prefix)) {
                return;
            }
        }

        $roleMap = [
            '/account/'            => null,
            '/faculty-form/'       => 'ROLE_FACULTY_FORM',
            '/coordinator-form/'   => 'ROLE_COORDINATOR_FORM',
            '/AssignmentsGrades/'  => 'ROLE_ASSIGNMENTS_GRADES',
            '/report-generator/'   => 'ROLE_REPORTGEN',
        ];

        foreach ($roleMap as $prefix => $role) {
            if (!str_starts_with($requestPathInfo, $prefix)) {
                continue;
            }

            if (!$security->getUser()) {
                throw new AccessDeniedHttpException('Authentication required.');
            }

            if ($role !== null && !$security->isGranted($role)) {
                throw new AccessDeniedHttpException("Missing required role: {$role}");
            }

            return;
        }
    }

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

    public static function handleRequest(Request $request, Response $response, string $publicDirectory, Security $security): void
    {
        $legacyScriptFilename = LegacyBridge::getLegacyScript($request, $security);

        // Possibly (re-)set some env vars (e.g. to handle forms
        // posting to PHP_SELF):
        $p = $request->getPathInfo();
        $_SERVER['PHP_SELF'] = $p;
        $_SERVER['SCRIPT_NAME'] = $p;
        $_SERVER['SCRIPT_FILENAME'] = $legacyScriptFilename;

        require $legacyScriptFilename;
    }
}
