<?php

namespace App\Enum;

enum StatutMembre: string
{
    case Etudiant = 'etudiant';
    case Formateur = 'formateur';
    case Personnel = 'personnel';

    public function libelle(): string
    {
        return match ($this) {
            self::Etudiant => 'Étudiant',
            self::Formateur => 'Formateur',
            self::Personnel => 'Personnel',
        };
    }
}
