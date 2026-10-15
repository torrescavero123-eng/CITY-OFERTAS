<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum CountScopeType: string
{
    case Full = 'FULL';
    case Zone = 'ZONE';
    case Location = 'LOCATION';
    case Category = 'CATEGORY';
    case AbcA = 'ABC_A';

    public function needsValue(): bool
    {
        return match ($this) {
            self::Zone, self::Location, self::Category => true,
            default => false,
        };
    }
}
