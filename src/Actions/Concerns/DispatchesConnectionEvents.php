<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Support\Config;

trait DispatchesConnectionEvents
{
    protected function dispatch(object $event): void
    {
        if (Config::boolean('connections.events.enabled', true)) {
            Event::dispatch($event);
        }
    }
}
