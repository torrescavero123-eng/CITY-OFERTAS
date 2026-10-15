<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Inventory\Domain\EventBus;

final class RecordingEventBus implements EventBus
{
    /** @var list<object> */
    public array $events = [];

    public function publish(object $event): void
    {
        $this->events[] = $event;
    }
}
