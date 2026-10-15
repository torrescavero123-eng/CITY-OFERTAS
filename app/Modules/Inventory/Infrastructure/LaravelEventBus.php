<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Inventory\Domain\EventBus;
use Illuminate\Support\Facades\DB;

final class LaravelEventBus implements EventBus
{
    public function publish(object $event): void
    {
        // Solo si la transacción se confirma: nunca se notifica un movimiento que se revirtió.
        DB::afterCommit(static function () use ($event): void {
            event($event);
        });
    }
}
