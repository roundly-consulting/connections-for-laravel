<?php

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('keeps actions final')
    ->expect('RoundlyConsulting\Connections\Actions')
    ->classes()
    ->toBeFinal();

it('keeps events final and readonly')
    ->expect('RoundlyConsulting\Connections\Events')
    ->classes()
    ->toBeFinal();

it('keeps data transfer objects final and readonly')
    ->expect('RoundlyConsulting\Connections\DataTransferObjects')
    ->classes()
    ->toBeReadonly();

it('uses enums for connection status')
    ->expect('RoundlyConsulting\Connections\Enums\ConnectionStatus')
    ->toBeEnum();
