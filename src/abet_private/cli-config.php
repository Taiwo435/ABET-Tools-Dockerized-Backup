<?php

/**
 * REQUIRED TO BE IN THE ROOT FOLDER (with composer and friends)
 * the doctrine cli config
 * 
 * run "composer doctrine" to get more info
 */

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\MigrationConfiguration;
use Doctrine\Migrations\Configuration\Migration\JsonFile;

require_once 'vendor/autoload.php';
require_once 'bootstrap.php';

if (!isset($entityManager)) {
    $entityManager = Services::getEntityManager();
}

$paths = [__DIR__.'/database/entities'];
$config = new JsonFile('database/migrations.json');

return DependencyFactory::fromEntityManager($config, new ExistingEntityManager($entityManager));  