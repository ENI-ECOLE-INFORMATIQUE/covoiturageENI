<?php

namespace App\Enum;

enum SensTrajet: string
{
    case VersSite = 'vers_site';
    case DepuisSite = 'depuis_site';

    public function libelle(): string
    {
        return match ($this) {
            self::VersSite => 'Vers le campus',
            self::DepuisSite => 'Depuis le campus',
        };
    }
}
