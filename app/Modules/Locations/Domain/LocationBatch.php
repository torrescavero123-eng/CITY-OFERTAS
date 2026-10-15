<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

/** Pedido de generación en lote: zona fija x pasillos x anaqueles x niveles. */
final readonly class LocationBatch
{
    public const MAX_PER_BATCH = 2000;

    public function __construct(
        public int $warehouseId,
        public int $zone,
        public int $aisleFrom,
        public int $aisleTo,
        public int $rackFrom,
        public int $rackTo,
        public int $levelFrom,
        public int $levelTo,
        public ?int $actorId = null,
        public ?string $maxCapacity = null,
    ) {
        foreach (['pasillos' => [$aisleFrom, $aisleTo], 'anaqueles' => [$rackFrom, $rackTo], 'niveles' => [$levelFrom, $levelTo]] as $label => [$from, $to]) {
            if ($from > $to) {
                throw LocationException::invalid("El rango de {$label} es inválido: el inicial es mayor que el final.");
            }
        }

        if ($this->count() > self::MAX_PER_BATCH) {
            throw LocationException::invalid('Máximo '.self::MAX_PER_BATCH.' ubicaciones por lote (pidió '.$this->count().').');
        }
        if ($maxCapacity !== null && (preg_match('/^\d{1,11}(\.\d{1,3})?$/', $maxCapacity) !== 1 || bccomp($maxCapacity, '0', 3) <= 0)) {
            throw LocationException::invalid('La capacidad máxima debe ser mayor que cero.');
        }
    }

    public function count(): int
    {
        return ($this->aisleTo - $this->aisleFrom + 1) * ($this->rackTo - $this->rackFrom + 1) * ($this->levelTo - $this->levelFrom + 1);
    }

    /** @return list<NewLocation> */
    public function expand(string $warehouseCode): array
    {
        $out = [];
        for ($aisle = $this->aisleFrom; $aisle <= $this->aisleTo; $aisle++) {
            for ($rack = $this->rackFrom; $rack <= $this->rackTo; $rack++) {
                for ($level = $this->levelFrom; $level <= $this->levelTo; $level++) {
                    $code = LocationCode::build($warehouseCode, $this->zone, $aisle, $rack, $level);
                    $out[] = new NewLocation(
                        $code->value, 'Z'.$this->zone, sprintf('P%02d', $aisle), sprintf('A%02d', $rack), $level,
                        $this->maxCapacity === null ? null : bcadd($this->maxCapacity, '0', 3),
                    );
                }
            }
        }

        return $out;
    }
}
