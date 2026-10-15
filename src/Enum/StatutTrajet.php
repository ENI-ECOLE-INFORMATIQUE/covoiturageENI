<?php

namespace App\Enum;

enum StatutTrajet: string
{
    case Ouvert = 'ouvert';
    case Complet = 'complet';
    case Annule = 'annule';
    case Termine = 'termine';
}
