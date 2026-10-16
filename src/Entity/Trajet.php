<?php

namespace App\Entity;

use App\Enum\SensTrajet;
use App\Enum\StatutTrajet;
use App\Repository\TrajetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TrajetRepository::class)]
#[ORM\Table(name: 'trajet')]
#[ORM\Index(name: 'idx_trajet_recherche', columns: ['site_id', 'sens', 'date_depart'])]
class Trajet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'conducteur_id', nullable: false, onDelete: 'CASCADE')]
    private ?Membre $conducteur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'vehicule_id', nullable: true, onDelete: 'SET NULL')]
    private ?Vehicule $vehicule = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'site_id', nullable: false)]
    private ?Site $site = null;

    #[ORM\Column(enumType: SensTrajet::class, length: 20)]
    private ?SensTrajet $sens = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'La ville de départ est obligatoire.')]
    private ?string $villeDepart = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'La ville d\'arrivée est obligatoire.')]
    private ?string $villeArrivee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseRendezVous = null;

    #[ORM\Column(name: 'date_depart', type: 'datetime_immutable')]
    #[Assert\NotBlank(message: 'La date de départ est obligatoire.')]
    private ?\DateTimeImmutable $dateDepart = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $dureeEstimee = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    private ?string $prix = '0.00';

    #[ORM\Column]
    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à 0.')]
    private ?int $nbPlacesOffertes = null;

    #[ORM\Column(enumType: StatutTrajet::class, length: 10)]
    private StatutTrajet $statut = StatutTrajet::Ouvert;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(mappedBy: 'trajet', targetEntity: Participation::class, orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConducteur(): ?Membre
    {
        return $this->conducteur;
    }

    public function setConducteur(?Membre $conducteur): static
    {
        $this->conducteur = $conducteur;

        return $this;
    }

    public function getVehicule(): ?Vehicule
    {
        return $this->vehicule;
    }

    public function setVehicule(?Vehicule $vehicule): static
    {
        $this->vehicule = $vehicule;

        return $this;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getSens(): ?SensTrajet
    {
        return $this->sens;
    }

    public function setSens(SensTrajet $sens): static
    {
        $this->sens = $sens;

        return $this;
    }

    public function getVilleDepart(): ?string
    {
        return $this->villeDepart;
    }

    public function setVilleDepart(string $villeDepart): static
    {
        $this->villeDepart = $villeDepart;

        return $this;
    }

    public function getVilleArrivee(): ?string
    {
        return $this->villeArrivee;
    }

    public function setVilleArrivee(string $villeArrivee): static
    {
        $this->villeArrivee = $villeArrivee;

        return $this;
    }

    public function getAdresseRendezVous(): ?string
    {
        return $this->adresseRendezVous;
    }

    public function setAdresseRendezVous(?string $adresseRendezVous): static
    {
        $this->adresseRendezVous = $adresseRendezVous;

        return $this;
    }

    public function getDateDepart(): ?\DateTimeImmutable
    {
        return $this->dateDepart;
    }

    public function setDateDepart(\DateTimeImmutable $dateDepart): static
    {
        $this->dateDepart = $dateDepart;

        return $this;
    }

    public function getDureeEstimee(): ?int
    {
        return $this->dureeEstimee;
    }

    public function setDureeEstimee(?int $dureeEstimee): static
    {
        $this->dureeEstimee = $dureeEstimee;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getNbPlacesOffertes(): ?int
    {
        return $this->nbPlacesOffertes;
    }

    public function setNbPlacesOffertes(int $nbPlacesOffertes): static
    {
        $this->nbPlacesOffertes = $nbPlacesOffertes;

        return $this;
    }

    public function getStatut(): StatutTrajet
    {
        return $this->statut;
    }

    public function setStatut(StatutTrajet $statut): static
    {
        $this->statut = $statut;

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

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setTrajet($this);
        }

        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getTrajet() === $this) {
                $participation->setTrajet(null);
            }
        }

        return $this;
    }

    /**
     * Places encore disponibles (participations acceptées uniquement).
     */
    public function getNbPlacesRestantes(): int
    {
        $reservees = 0;
        foreach ($this->participations as $participation) {
            if ($participation->getStatut() === \App\Enum\StatutParticipation::Accepte) {
                $reservees += $participation->getNbPlaces();
            }
        }

        return max(0, ($this->nbPlacesOffertes ?? 0) - $reservees);
    }
}
