<?php

namespace App\DataFixtures;

use App\Entity\Membre;
use App\Entity\Site;
use App\Enum\StatutMembre;
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
        foreach (['ENI Quimper', 'ENI Rennes', 'ENI Nantes', 'ENI CEL'] as $nom) {
            $site = new Site();
            $site->setNom($nom);
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

        $manager->flush();
    }
}
