<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Modules\Operations\Domain\ReasonInfo;
use App\Shared\Domain\Transaction;
use Tests\Support\FixedClock;
use Tests\Support\RecordingAuditTrail;

/** Arma el escenario en memoria para las pruebas de Operations. */
final class OperationsKit
{
    public InMemoryOperationsStore $store;

    public FakeStockMovements $movements;

    public RecordingAuditTrail $audit;

    public FixedClock $clock;

    public Transaction $tx;

    public function __construct()
    {
        $this->store = new InMemoryOperationsStore;
        $this->movements = new FakeStockMovements;
        $this->audit = new RecordingAuditTrail;
        $this->clock = new FixedClock;
        $this->tx = new OperationsTransaction($this->store, $this->movements);

        $this->store->reasons = [
            1 => new ReasonInfo(1, 'MERMA', true, true),
            2 => new ReasonInfo(2, 'AJUSTE_NEGATIVO', true, true),
            3 => new ReasonInfo(3, 'AJUSTE_POSITIVO', true, true),
            4 => new ReasonInfo(4, 'MERMA', true, false),
        ];
    }
}
