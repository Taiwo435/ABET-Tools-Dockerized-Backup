<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    $envPath = file_exists('/home/docker/.env') ? '/home/docker/.env' : dirname(__DIR__).'/.env';
    (new Dotenv())->bootEnv($envPath);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
