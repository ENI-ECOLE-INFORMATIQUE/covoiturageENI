<?php

namespace App\Entity;

use App\Enum\StatutMembre;
use App\Repository\MembreRepository;
use App\Validator\CampusEniEmail;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MembreRepository::class)]
#[ORM\Table(name: 'membre')]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
class Membre implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'adresse email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
    #[CampusEniEmail]
    private ?string $email = null;

    #[ORM\Column(name: 'mot_de_passe')]
    private ?string $motDePasse = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    private ?string $nom = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    private ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(enumType: StatutMembre::class, length: 20)]
    private ?StatutMembre $statut = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'email_verifie_le', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifieLe = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'campus_rattachement_id', nullable: false)]
    private ?Site $campusRattachement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getStatut(): ?StatutMembre
    {
        return $this->statut;
    }

    public function setStatut(StatutMembre $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getEmailVerifieLe(): ?\DateTimeImmutable
    {
        return $this->emailVerifieLe;
    }

    public function setEmailVerifieLe(?\DateTimeImmutable $emailVerifieLe): static
    {
        $this->emailVerifieLe = $emailVerifieLe;

        return $this;
    }

    public function getCampusRattachement(): ?Site
    {
        return $this->campusRattachement;
    }

    public function setCampusRattachement(?Site $campusRattachement): static
    {
        $this->campusRattachement = $campusRattachement;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Si des données sensibles temporaires existaient, elles seraient effacées ici.
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function getNomComplet(): string
    {
        return \sprintf('%s %s', $this->prenom, $this->nom);
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }
}
