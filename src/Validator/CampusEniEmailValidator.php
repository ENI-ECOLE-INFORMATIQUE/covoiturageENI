<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class CampusEniEmailValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!$constraint instanceof CampusEniEmail) {
            throw new \InvalidArgumentException(\sprintf('La contrainte %s ne peut être validée que par %s.', CampusEniEmail::class, self::class));
        }

        $positionArobase = strrpos((string) $value, '@');
        if (false === $positionArobase) {
            return; // Le format email est contrôlé séparément par Assert\Email.
        }

        $domaine = strtolower(substr((string) $value, $positionArobase + 1));
        if ($domaine !== CampusEniEmail::DOMAINE_AUTORISE) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ domaine }}', CampusEniEmail::DOMAINE_AUTORISE)
                ->addViolation();
        }
    }
}
