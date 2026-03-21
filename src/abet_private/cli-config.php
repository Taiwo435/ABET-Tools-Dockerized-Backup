<?php

/**
 * REQUIRED TO BE IN THE ROOT FOLDER (with composer and friends)
 * the doctrine cli config
 * 
 * run "composer doctrine" to get more info
 */

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\DependencyFactory;

require_once 'vendor/autoload.php';
require_once 'bootstrap.php';

return DependencyFactory::fromEntityManager($config, new ExistingEntityManager($entityManager));  