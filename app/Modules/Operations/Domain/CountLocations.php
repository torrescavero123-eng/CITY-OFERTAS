<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

/** Puerto hacia el módulo Locations: congelar durante el conteo. */
interface CountLocations
{
    /**
     * @param  list<int>  $locationIds
     *
     * @throws OperationsException si alguna ya está congelada o desactivada
     */
    public function freeze(array $locationIds, int $actorId, string $reference): void;

    /** @param list<int> $locationIds */
    public function unfreeze(array $locationIds, int $actorId, string $reference): void;
}
