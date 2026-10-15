<?php

namespace App\Entity;

use App\Repository\VehiculeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VehiculeRepository::class)]
#[ORM\Table(name: 'vehicule')]
class Vehicule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'membre_id', nullable: false, onDelete: 'CASCADE')]
    private ?Membre $membre = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'La marque est obligatoire.')]
    private ?string $marque = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Le modèle est obligatoire.')]
    private ?string $modele = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $nbPlaces = null;

    #[ORM\Column(length: 15)]
    #[Assert\NotBlank(message: 'La plaque d\'immatriculation est obligatoire.')]
    private ?string $plaque = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $fumeur = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $animaux = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMembre(): ?Membre
    {
        return $this->membre;
    }

    public function setMembre(?Membre $membre): static
    {
        $this->membre = $membre;

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(int $nbPlaces): static
    {
        $this->nbPlaces = $nbPlaces;

        return $this;
    }

    public function getPlaque(): ?string
    {
        return $this->plaque;
    }

    public function setPlaque(string $plaque): static
    {
        $this->plaque = $plaque;

        return $this;
    }

    public function isFumeur(): bool
    {
        return $this->fumeur;
    }

    public function setFumeur(bool $fumeur): static
    {
        $this->fumeur = $fumeur;

        return $this;
    }

    public function isAnimaux(): bool
    {
        return $this->animaux;
    }

    public function setAnimaux(bool $animaux): static
    {
        $this->animaux = $animaux;

        return $this;
    }

    public function __toString(): string
    {
        return \sprintf('%s %s', $this->marque, $this->modele);
    }
}
