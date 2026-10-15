<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

interface EventBus
{
    /** La implementación de producción publica solo después del commit. */
    public function publish(object $event): void;
}
