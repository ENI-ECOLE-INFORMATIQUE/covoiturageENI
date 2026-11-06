<?php

namespace App\DataFixtures;

use App\Entity\Membre;
use App\Entity\Participation;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Enum\SensTrajet;
use App\Enum\StatutMembre;
use App\Enum\StatutParticipation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $campus = [];
        $campusData = [
            'ENI Quimper' => [
                'adresse' => '2 Rue Georges Perros', 'ville' => 'Quimper', 'codePostal' => '29000',
                'siteWeb' => 'https://www.eni-ecole.fr/campus/quimper',
                'description' => "À Quimper, le campus ENI accueille les formations dans un environnement à taille humaine, proche du centre-ville.\n\nLe covoiturage facilite les déplacements depuis toute la Cornouaille.",
            ],
            'ENI Rennes' => [
                'adresse' => '8 Rue Léo Lagrange', 'ville' => 'Chartres de Bretagne', 'codePostal' => '35131',
                'siteWeb' => 'https://www.eni-ecole.fr/campus/rennes',
                'description' => "Le campus de Rennes, à Chartres de Bretagne, est aisément accessible depuis la rocade.\n\nLe covoiturage est une bonne solution aux heures de pointe sur l'axe Rennes - Chartres de Bretagne.",
            ],
            'ENI Nantes' => [
                'adresse' => '2B rue Benjamin Franklin / 3 rue Mickaël Faraday', 'ville' => 'Saint-Herblain', 'codePostal' => '44800',
                'siteWeb' => 'https://www.eni-ecole.fr/campus/nantes',
                'description' => "À Nantes, nos campus sont situés à Saint-Herblain et bénéficient d'un accès direct aux grands axes routiers, mais les embouteillages aux heures de pointe peuvent compliquer l'accès. Les places de parking sont disponibles sur nos deux campus.\n\nLe covoiturage est une excellente solution, en complément des transports en commun.",
            ],
            'ENI Niort' => [
                'adresse' => '19 avenue Léo Lagrange, Bâtiment B et C', 'ville' => 'Niort', 'codePostal' => '79000',
                'siteWeb' => 'https://www.eni-ecole.fr/campus/niort',
                'description' => "Le campus de Niort, avenue Léo Lagrange, est proche de la gare et des axes routiers.\n\nLe covoiturage facilite les trajets depuis le centre et les communes alentours.",
            ],
            'ENI CEL' => [
                'adresse' => null, 'ville' => null, 'codePostal' => null, // campus en ligne
                'siteWeb' => null,
                'description' => "Le campus en ligne (CEL) propose les formations ENI 100 % à distance.\n\nLes rassemblements ponctuels sur les campus physiques sont l'occasion de rencontrer la communauté ENI.",
            ],
        ];
        foreach ($campusData as $nom => $data) {
            $site = new Site();
            $site->setNom($nom);
            if (null !== $data['adresse']) {
                $site->setAdresse($data['adresse']);
                $site->setVille($data['ville']);
                $site->setCodePostal($data['codePostal']);
            }
            $site->setSiteWeb($data['siteWeb']);
            $site->setDescription($data['description']);
            $manager->persist($site);
            $campus[$nom] = $site;
        }

        $admin = new Membre();
        $admin->setPrenom('Compte');
        $admin->setNom('Administrateur');
        $admin->setEmail('admin@campus-eni.fr');
        $admin->setStatut(StatutMembre::Personnel);
        $admin->setCampusRattachement($campus['ENI Rennes']);
        $admin->setEmailVerifieLe(new \DateTimeImmutable());
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setMotDePasse($this->passwordHasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        // Données de démonstration pour le jalon M2.
        $conducteur = new Membre();
        $conducteur->setPrenom('Camille');
        $conducteur->setNom('Le Goff');
        $conducteur->setEmail('camille.legoff@campus-eni.fr');
        $conducteur->setStatut(StatutMembre::Etudiant);
        $conducteur->setCampusRattachement($campus['ENI Rennes']);
        $conducteur->setEmailVerifieLe(new \DateTimeImmutable());
        $conducteur->setMotDePasse($this->passwordHasher->hashPassword($conducteur, 'MotDePasse123'));
        $manager->persist($conducteur);

        $passager = new Membre();
        $passager->setPrenom('Hugo');
        $passager->setNom('Martin');
        $passager->setEmail('hugo.martin@campus-eni.fr');
        $passager->setStatut(StatutMembre::Formateur);
        $passager->setCampusRattachement($campus['ENI Nantes']);
        $passager->setEmailVerifieLe(new \DateTimeImmutable());
        $passager->setMotDePasse($this->passwordHasher->hashPassword($passager, 'MotDePasse123'));
        $manager->persist($passager);

        $vehicule = new Vehicule();
        $vehicule->setMembre($conducteur);
        $vehicule->setMarque('Peugeot');
        $vehicule->setModele('208');
        $vehicule->setCouleur('Gris');
        $vehicule->setPlaque('AB-123-CD');
        $vehicule->setNbPlaces(3);
        $vehicule->setAnimaux(true);
        $manager->persist($vehicule);

        $demainMatin = (new \DateTimeImmutable('tomorrow'))->setTime(8, 0);
        $demainSoir = (new \DateTimeImmutable('tomorrow'))->setTime(17, 30);

        $trajetAller = new Trajet();
        $trajetAller->setConducteur($conducteur);
        $trajetAller->setVehicule($vehicule);
        $trajetAller->setSite($campus['ENI Rennes']);
        $trajetAller->setSens(SensTrajet::VersSite);
        $trajetAller->setVilleDepart('Vannes');
        $trajetAller->setVilleArrivee('Rennes');
        $trajetAller->setAdresseRendezVous('Gare de Vannes');
        $trajetAller->setDateDepart($demainMatin);
        $trajetAller->setDureeEstimee(75);
        $trajetAller->setPrix('6.00');
        $trajetAller->setNbPlacesOffertes($vehicule->getNbPlaces());
        $manager->persist($trajetAller);

        $trajetRetour = new Trajet();
        $trajetRetour->setConducteur($conducteur);
        $trajetRetour->setVehicule($vehicule);
        $trajetRetour->setSite($campus['ENI Rennes']);
        $trajetRetour->setSens(SensTrajet::DepuisSite);
        $trajetRetour->setVilleDepart('Rennes');
        $trajetRetour->setVilleArrivee('Vannes');
        $trajetRetour->setDateDepart($demainSoir);
        $trajetRetour->setPrix('6.00');
        $trajetRetour->setNbPlacesOffertes($vehicule->getNbPlaces());
        $manager->persist($trajetRetour);

        // Une demande en attente pour démontrer la modération par le conducteur.
        $participation = new Participation();
        $participation->setTrajet($trajetAller);
        $participation->setMembre($passager);
        $participation->setNbPlaces(1);
        $participation->setStatut(StatutParticipation::EnAttente);
        $manager->persist($participation);

        $manager->flush();
    }
}
