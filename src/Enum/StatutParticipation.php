<?php

namespace App\Enum;

enum StatutParticipation: string
{
    case EnAttente = 'en_attente';
    case Accepte = 'accepte';
    case Refuse = 'refuse';
    case Annule = 'annule';
}
