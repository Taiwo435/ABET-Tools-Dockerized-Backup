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

require_once getenv("ABET_PRIVATE_DIR").'/vendor/autoload.php';
$path = (getenv('ABET_PRIVATE_DIR').'/../../docker/.env');
(new Dotenv())->bootEnv($path);

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

if (false === $response->isNotFound()) {
    // Symfony successfully handled the route.
    $response->send();
} else {
    // var_dump(phpinfo());
    try {
        LegacyBridge::handleRequest($request, $response, __DIR__);
    }
    catch (NotFoundHttpException $e) {
        $response->send();
    }
}

$kernel->terminate($request, $response);
