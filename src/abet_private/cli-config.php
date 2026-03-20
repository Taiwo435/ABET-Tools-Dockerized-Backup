<?php

/**
 * REQUIRED TO BE IN THE ROOT FOLDER (with composer and friends)
 * the doctrine cli config
 * 
 * run "composer doctrine" to get more info
 */

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__."/../../docker");
$dotenv->load();


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Doctrine\DBAL\DriverManager;

// config file (maybe make a config dir?)
$config = new JsonFile('database/migrations.json'); // Or use one of the Doctrine\Migrations\Configuration\Configuration\* loaders

$paths = [__DIR__.'/database/Entities'];
$isDevMode = true;

$ORMConfig = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

$connectionParams = [
    'dbname' => getenv('MYSQL_DATABASE'),
    'user' => getenv('MYSQL_USER'),
    'password' => getenv('MYSQL_PASS'),
    'host' => getenv('MYSQL_HOSTNAME'),
    'driver' => 'pdo_mysql',
];

var_dump($connectionParams);

$connection = DriverManager::getConnection($connectionParams);


// $connection = DriverManager::getConnection(
//     ['driver' => 'pdo_mysql', 'memory' => true]);

$entityManager = new EntityManager(
    $connection, 
    $ORMConfig);

return DependencyFactory::fromEntityManager($config, new ExistingEntityManager($entityManager));  