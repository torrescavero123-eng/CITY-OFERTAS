<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\LocationState;

/**
 * Escenario en memoria: producto 1 (costo 0), ubicaciones 10 (BOD-A) y 11 (PIS-A) activas,
 * 12 congelada y 13 desactivada.
 */
final class Scenario
{
    public const PRODUCT = 1;

    public const BODEGA = 10;

    public const PISO = 11;

    public const FROZEN = 12;

    public const INACTIVE = 13;

    public InMemoryStockLedger $ledger;

    public RecordingEventBus $events;

    public RegisterMovement $register;

    public function __construct()
    {
        $this->ledger = new InMemoryStockLedger;
        $this->events = new RecordingEventBus;
        $this->register = new RegisterMovement($this->ledger, new RollbackTransaction($this->ledger), $this->events);

        $this->ledger->productCosts[self::PRODUCT] = '0.0000';
        $this->ledger->locations = [
            self::BODEGA => new LocationState(self::BODEGA, 'BOD-A', true, false),
            self::PISO => new LocationState(self::PISO, 'PIS-A', true, false),
            self::FROZEN => new LocationState(self::FROZEN, 'BOD-F', true, true),
            self::INACTIVE => new LocationState(self::INACTIVE, 'BOD-X', false, false),
        ];
    }

    public function balance(int $location, int $product = self::PRODUCT): string
    {
        return $this->ledger->lockBalance($product, $location)->value;
    }
}
