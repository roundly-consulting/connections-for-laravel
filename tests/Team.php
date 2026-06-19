<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;

class Team extends Model implements Connectable
{
    use HasConnections;

    /** @var list<string> */
    protected $guarded = [];

    /** @var bool */
    public $timestamps = false;
}
