<?php

namespace App\Tests;

use App\Entity\Membre;
use App\Entity\Participation;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Enum\SensTrajet;
use App\Enum\StatutMembre;
use App\Enum\StatutTrajet;
use App\Repository\ParticipationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Helpers de parcours pour les tests fonctionnels : création directe
 * d'un membre/trajet (avant toute requête HTTP) et parcours UI de la
 * réservation à l'avis.
 */
trait TrajetHelpersTrait
{
    private static function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get('doctrine')->getManager();
    }

    private static function creerMembre(KernelBrowser $client, string $email): Membre
    {
        $membre = new Membre();
        $membre->setEmail($email);
        $membre->setPrenom('Test');
        $membre->setNom(ucfirst(substr($email, 0, strpos($email, '@'))));
        $membre->setStatut(StatutMembre::Etudiant);
        $membre->setCampusRattachement(self::em($client)->getRepository(Site::class)->findOneBy(['actif' => true]));
        $membre->setEmailVerifieLe(new \DateTimeImmutable());
        $membre->setMotDePasse(
            $client->getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($membre, 'MotDePasse123')
        );

        self::em($client)->persist($membre);
        self::em($client)->flush();

        return $membre;
    }

    private static function creerTrajet(KernelBrowser $client, Membre $conducteur, \DateTimeImmutable $depart, int $places = 2): Trajet
    {
        $entityManager = self::em($client);

        $vehicule = new Vehicule();
        $vehicule->setMembre($conducteur);
        $vehicule->setMarque('Renault');
        $vehicule->setModele('Clio');
        $vehicule->setPlaque('ZZ-987-WV');
        $vehicule->setNbPlaces($places);
        $entityManager->persist($vehicule);

        $trajet = new Trajet();
        $trajet->setConducteur($conducteur);
        $trajet->setVehicule($vehicule);
        $trajet->setSite($entityManager->getRepository(Site::class)->findOneBy(['actif' => true]));
        $trajet->setSens(SensTrajet::VersSite);
        $trajet->setVilleDepart('Vannes');
        $trajet->setVilleArrivee('Rennes');
        $trajet->setDateDepart($depart);
        $trajet->setPrix('5.00');
        $trajet->setNbPlacesOffertes($places);
        $trajet->setStatut(StatutTrajet::Ouvert);
        $entityManager->persist($trajet);

        $entityManager->flush();

        return $trajet;
    }

    private static function reserverViaUi(KernelBrowser $client, Trajet $trajet, Membre $passager): void
    {
        $client->loginUser($passager);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Demander à participer')->form([
            'reservation[nbPlaces]' => '1',
        ]);
        $client->submit($form);
    }

    private static function accepterViaUi(KernelBrowser $client, Trajet $trajet, Membre $conducteur): void
    {
        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Accepter')->form();
        $client->submit($form);
    }

    private static function refuserViaUi(KernelBrowser $client, Trajet $trajet, Membre $conducteur): void
    {
        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Refuser')->form();
        $client->submit($form);
    }

    /**
     * Décale le départ d'un trajet dans le passé, en SQL direct :
     * après des requêtes HTTP, le conteneur du client est celui du dernier
     * kernel rebooté et son EntityManager ne gère plus les entités créées
     * avant les requêtes ; un UPDATE SQL garantit le commit.
     */
    private static function fairePartirTrajet(KernelBrowser $client, Trajet $trajet): void
    {
        /** @var Connection $connection */
        $connection = $client->getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement(
            'UPDATE trajet SET date_depart = ? WHERE id = ?',
            [(new \DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s'), $trajet->getId()]
        );
    }

    /**
     * Réserve et fait accepter la participation, puis fait partir le trajet
     * (décalé à il y a 2 heures) pour rendre l'avis possible (règle 6).
     */
    private static function participerEtTerminer(KernelBrowser $client, Trajet $trajet, Membre $conducteur, Membre $passager): Participation
    {
        self::reserverViaUi($client, $trajet, $passager);
        self::accepterViaUi($client, $trajet, $conducteur);

        $participation = $client->getContainer()->get(ParticipationRepository::class)
            ->findOneBy(['trajet' => $trajet, 'membre' => $passager]);
        \assert(null !== $participation);

        self::fairePartirTrajet($client, $trajet);

        return $participation;
    }
}
