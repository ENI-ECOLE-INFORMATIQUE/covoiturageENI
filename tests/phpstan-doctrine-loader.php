<?php

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

require __DIR__.'/../vendor/autoload.php';

// Loader PHPStan-Doctrine : fournit un EntityManager avec les mappings
// du projet pour permettre l'analyse statique des entités (API ORM 3).
$config = ORMSetup::createAttributeMetadataConfiguration(
    [__DIR__.'/../src/Entity'],
    true,
    null,
    new ArrayAdapter()
);

$connection = DriverManager::getConnection([
    'driver' => 'pdo_sqlite',
    'memory' => true,
], $config);

return new EntityManager($connection, $config);
