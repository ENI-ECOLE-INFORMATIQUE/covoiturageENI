<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Container\ContainerInterface;

/**
 * Prépare une base SQLite de test (fichier var/cache/test.db) pour un test
 * fonctionnel : schéma recréé puis fixtures chargées.
 *
 * À appeler APRÈS static::createClient() :
 *   $client = static::createClient();
 *   self::preparerBase($client->getContainer());
 *
 * Le KernelBrowser redémarre le kernel à chaque requête : on ne peut pas
 * utiliser sqlite:///:memory: (la base serait vide à la requête suivante),
 * d'où une base fichier partagée entre les requêtes d'un même test.
 */
trait DatabasePrimerTrait
{
    private static function preparerBase(ContainerInterface $container): void
    {
        $entityManager = $container->get('doctrine')->getManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
            // La base est peut-être déjà vide au premier lancement.
        }
        $schemaTool->createSchema($metadata);

        $purger = new ORMPurger($entityManager);
        $purger->purge();

        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute([$container->get(AppFixtures::class)]);
    }
}
