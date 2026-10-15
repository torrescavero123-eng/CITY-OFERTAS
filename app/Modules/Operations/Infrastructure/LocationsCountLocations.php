<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Locations\Application\ChangeLocationState;
use App\Modules\Locations\Domain\LocationException;
use App\Modules\Operations\Domain\CountLocations;
use App\Modules\Operations\Domain\OperationsException;

/** Adaptador hacia el módulo Locations (congelar / descongelar en masa). */
final class LocationsCountLocations implements CountLocations
{
    public function __construct(private readonly ChangeLocationState $locations) {}

    public function freeze(array $locationIds, int $actorId, string $reference): void
    {
        try {
            $this->locations->freezeMany($locationIds, $actorId, $reference);
        } catch (LocationException $e) {
            throw OperationsException::invalid($e->getMessage());
        }
    }

    public function unfreeze(array $locationIds, int $actorId, string $reference): void
    {
        try {
            $this->locations->unfreezeMany($locationIds, $actorId, $reference);
        } catch (LocationException $e) {
            throw OperationsException::invalid($e->getMessage());
        }
    }
}
