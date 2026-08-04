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

function abetEnv(string $name, ?string $default = null): ?string {
    $processValue = getenv($name);
    if ($processValue !== false) {
        return $processValue;
    }

    $dotenvValue = $_ENV[$name] ?? $_SERVER[$name] ?? null;

    return is_string($dotenvValue) ? $dotenvValue : $default;
}


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;

// config file (maybe make a config dir?)
class Services {
    private static ?EntityManager $instance = null;

    public static function getConnection() : Connection {
        $connectionParams = [
            'dbname' => abetEnv('MYSQL_DATABASE'),
            'user' => abetEnv('MYSQL_USER'),
            'password' => abetEnv('MYSQL_PASS'),
            'host' => abetEnv('MYSQL_HOSTNAME', '127.0.0.1'),
            'driver' => 'pdo_mysql',
        ];

        return DriverManager::getConnection($connectionParams);
    }


    public static function getEntityManager() {
        if (static::$instance === null) {
            $paths = [__DIR__.'/src/Entity'];

            $isDevMode = true;

            $ORMConfig = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

            $connectionParams = [
                'dbname' => abetEnv('MYSQL_DATABASE'),
                'user' => abetEnv('MYSQL_USER'),
                'password' => abetEnv('MYSQL_PASS'),
                'host' => abetEnv('MYSQL_HOSTNAME', '127.0.0.1'),
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

    public static function doesColumnExist(string $tableName, string $columnName) : bool {
        $conn = self::getConnection();
        $schemaManager = $conn->createSchemaManager(); // DBAL 3.x
        $columnName = '"'.$columnName.'"';


        if ($schemaManager->tablesExist([$tableName])) {
            $columns = $schemaManager->introspectTableColumnsByUnquotedName($tableName);

            foreach ($columns as $column) {
                if ($column->getObjectName()->toString() === $columnName) {
                    return true;
                }
            }

            // echo "Column $columnName does not exist. Safe to add.";
            return false;
        } else {
            echo "Table $tableName does not exist!";
            throw new InvalidArgumentException("Table ".$tableName."Does not exist!!");
        }
    }
}



if (abetEnv("APP_ENV") != 'test')
$entityManager = Services::getEntityManager();
