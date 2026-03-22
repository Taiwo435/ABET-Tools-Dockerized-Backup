<?php

/**
 * REQUIRED TO BE IN THE ROOT FOLDER (with composer and friends)
 * 
 * the doctrine config. Period.
 * REQUIRE this file if you want to use the EntityManager!
 * 
 * run "composer doctrine" to get more info
 */

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__."/../../docker");
$dotenv->load();


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Doctrine\DBAL\DriverManager;

// config file (maybe make a config dir?)
class Services {
    private static ?EntityManager $instance = null;


//     }

    public static function getEntityManager() {
        if (static::$instance === null) {
            $paths = [__DIR__.'/database/entities'];

            $isDevMode = true;

            $ORMConfig = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

            $connectionParams = [
                'dbname' => $_ENV['MYSQL_DATABASE'],
                'user' => $_ENV['MYSQL_USER'],
                'password' => $_ENV['MYSQL_PASS'],
                'host' => "127.0.0.1",
                'driver' => 'pdo_mysql',
            ];

            echo ''. json_encode($connectionParams) .'';

            $connection = DriverManager::getConnection($connectionParams);

            static::$instance = new EntityManager(
                $connection, 
                $ORMConfig);
        }

       return static::$instance;
    }
}



$entityManager = Services::getEntityManager();