<?php

namespace App\Entity;

use App\Enum\StatutParticipation;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[ORM\UniqueConstraint(name: 'uq_participation_trajet_membre', columns: ['trajet_id', 'membre_id'])]
#[UniqueEntity(fields: ['trajet', 'membre'], message: 'Vous participez déjà à ce trajet.')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'trajet_id', nullable: false, onDelete: 'CASCADE')]
    private ?Trajet $trajet = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'membre_id', nullable: false, onDelete: 'CASCADE')]
    private ?Membre $membre = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à 0.')]
    private int $nbPlaces = 1;

    #[ORM\Column(enumType: StatutParticipation::class, length: 15)]
    private StatutParticipation $statut = StatutParticipation::EnAttente;

    #[ORM\Column(name: 'cree_le', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $creeLe = null;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrajet(): ?Trajet
    {
        return $this->trajet;
    }

    public function setTrajet(?Trajet $trajet): static
    {
        $this->trajet = $trajet;

        return $this;
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

    public function getNbPlaces(): int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(int $nbPlaces): static
    {
        $this->nbPlaces = $nbPlaces;

        return $this;
    }

    public function getStatut(): StatutParticipation
    {
        return $this->statut;
    }

    public function setStatut(StatutParticipation $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTimeImmutable $creeLe): static
    {
        $this->creeLe = $creeLe;

        return $this;
    }
}
