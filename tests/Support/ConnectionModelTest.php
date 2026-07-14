<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Support\ConnectionModel;
use RoundlyConsulting\Connections\Tests\CustomConnection;

it('resolves the packaged model by default', function (): void {
    expect(ConnectionModel::class())->toBe(Connection::class);
});

it('resolves a configured subclass of the packaged model', function (): void {
    config()->set('connections.model', CustomConnection::class);

    expect(ConnectionModel::class())->toBe(CustomConnection::class);
});

it('falls back to the packaged model when the configured value is not a class', function (): void {
    config()->set('connections.model', 'not-a-class');

    expect(ConnectionModel::class())->toBe(Connection::class);
});

it('falls back to the packaged model when the configured value is not a model', function (): void {
    config()->set('connections.model', stdClass::class);

    expect(ConnectionModel::class())->toBe(Connection::class);
});

it('falls back to the packaged model when the model cannot answer connection queries', function (): void {
    config()->set('connections.model', new class extends Model {}::class);

    expect(ConnectionModel::class())->toBe(Connection::class);
});

it('resolves the table the configured model reads from', function (): void {
    expect(ConnectionModel::table())->toBe('connections');

    config()->set('connections.table', 'links');

    expect(ConnectionModel::table())->toBe('links');
});
