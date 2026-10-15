<?php

namespace App\Entity;

use App\Repository\AvisRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
#[UniqueEntity(fields: ['participation'], message: 'Un avis a déjà été laissé pour ce trajet.')]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'auteur_id', nullable: false, onDelete: 'CASCADE')]
    private ?Membre $auteur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cible_id', nullable: false, onDelete: 'CASCADE')]
    private ?Membre $cible = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'participation_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Participation $participation = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.')]
    private ?int $note = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

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

    public function getAuteur(): ?Membre
    {
        return $this->auteur;
    }

    public function setAuteur(?Membre $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getCible(): ?Membre
    {
        return $this->cible;
    }

    public function setCible(?Membre $cible): static
    {
        $this->cible = $cible;

        return $this;
    }

    public function getParticipation(): ?Participation
    {
        return $this->participation;
    }

    public function setParticipation(?Participation $participation): static
    {
        $this->participation = $participation;

        return $this;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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
