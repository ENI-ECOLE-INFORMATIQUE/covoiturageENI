<?php

namespace App\Service;

use App\Entity\Site;

/**
 * Choisit l'image (dans assets/img) associée à un campus selon son nom.
 */
final class ImageCampus
{
    private const IMAGES = [
        'Nantes' => 'img/poster_thumb_facade-nantes-franklin.jpg',
        'Rennes' => 'img/poster_thumb_batiment-rennes.jpg',
        'Quimper' => 'img/poster_thumb_batiment-quimper.jpg',
        'Niort' => 'img/poster_thumb_batiment-niort.jpg',
    ];

    private const PAR_DEFAUT = 'img/banner-ecologique_conviviale.jpg';

    public function pour(Site $site): string
    {
        foreach (self::IMAGES as $motCle => $image) {
            if (str_contains((string) $site->getNom(), $motCle)) {
                return $image;
            }
        }

        return self::PAR_DEFAUT;
    }
}
