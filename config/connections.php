<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Connection;

return [

    /*
    |--------------------------------------------------------------------------
    | Connection Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to represent a connection. Override this if you
    | need to extend the default model with your own behaviour. The replacement
    | must extend RoundlyConsulting\Connections\Connection.
    |
    */

    'model' => Connection::class,

];
