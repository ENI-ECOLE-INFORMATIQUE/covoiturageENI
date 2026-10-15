<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class CampusEniEmail extends Constraint
{
    public const DOMAINE_AUTORISE = 'campus-eni.fr';

    public string $message = 'L\'adresse email doit se terminer par @campus-eni.fr. Seuls les membres de la communauté ENI peuvent s\'inscrire.';

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
