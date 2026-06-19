<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use Illuminate\Support\Facades\Event;

trait DispatchesConnectionEvents
{
    protected function dispatch(object $event): void
    {
        if ((bool) config('connections.events.enabled', true)) {
            Event::dispatch($event);
        }
    }
}
