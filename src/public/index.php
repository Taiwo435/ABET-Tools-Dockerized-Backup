<?php
/**
 * ############### README ################
 * Looking for the old index.php? 
 * 
 * Go to home.php instead. Don't worry, the urls are still the same.
 * 
 * Front controller with legacy bridge:
 *      @see https://symfony.com/doc/7.4/migration.html#front-controller-with-legacy-bridge
 * Front Controllers in general
 *      @see https://symfony.com/doc/7.4/page_creation.html
 */


// not the same for prod?

use App\Kernel;
use App\LegacyBridge;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require_once rtrim(getenv('ABET_PRIVATE_DIR') ?: '/home/abet_private', '/') . '/vendor/autoload.php';

$path = '/home/docker/.env';
if (file_exists($path)) {
    (new Dotenv())->bootEnv($path);
}

$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1';

global $kernel;

if ($_SERVER['APP_DEBUG']) {
    umask(0000); // DON"T set file permissions :)

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(
      explode(',', $trustedProxies),
      Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
    );
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);

if ($_SERVER['APP_ENV'] === 'prod') {
    $security = $kernel->getContainer()->get(
        \Symfony\Bundle\SecurityBundle\Security::class
    );

    $user = $security->getUser();

    $log = sprintf(
        <<<'LOG'
==============================
Request Debug
==============================
Datetime: %s
Method:   %s
URI:      %s
IP:       %s
User:     %s
Session:  %s
==============================

LOG,
        date('Y-m-d H:i:s T'),
        $request->getMethod(),
        $request->getRequestUri(),
        $request->getClientIp() ?? 'unknown',
        $user ? $user->getUserIdentifier() : 'NULL',
        var_export($_SESSION ?? [], true)
    );

    file_put_contents(
        '/tmp/debug.log',
        $log,
        FILE_APPEND | LOCK_EX
    );
}

if (false === $response->isNotFound()) {
    // Symfony successfully handled the route.
    $response->send();
} else {
    try {
        LegacyBridge::handleRequest($request, $response, $kernel);

    }
    catch (NotFoundHttpException $e) {
        // Symfony's kernel already rendered $response as a proper 404 page
        // before falling through to LegacyBridge, so just send it.

        $response->send();
    }
    catch (HttpExceptionInterface $e) {
        // #132: Exceptions thrown from LegacyBridge (e.g. AccessDeniedHttpException
        // from doAuthorizationChecks()) happen outside Symfony's normal request
        // lifecycle, so the kernel never gets a chance to render them. Render
        // manually using the same branded error template used everywhere else.

        $twig = $kernel->getContainer()->get(\Twig\Environment::class);
        $statusCode = $e->getStatusCode();
        $content = $twig->render('bundles/TwigBundle/Exception/error.html.twig', [
            'status_code' => $statusCode,
            'status_text' => \Symfony\Component\HttpFoundation\Response::$statusTexts[$statusCode] ?? 'Error',
        ]);
        $errorResponse = new Response($content, $statusCode, $e->getHeaders());
        $errorResponse->send();
    }
}
$kernel->terminate($request, $response);
