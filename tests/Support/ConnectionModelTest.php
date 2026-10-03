<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Support\ConnectionModel;
use RoundlyConsulting\Connections\Tests\CustomConnection;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(ConnectionModel::class())->toBe(Connection::class);
});

it('resolves a configured subclass of the packaged model', function (): void {
    config()->set('connections.model', CustomConnection::class);

    expect(ConnectionModel::class())->toBe(CustomConnection::class);
});

it('refuses a configured value that is not a Connection instead of falling back', function (Closure $configured): void {
    $class = $configured();
    config()->set('connections.model', $class);

    expect(fn (): string => ConnectionModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [connections.model] must be a class-string of ['.Connection::class."], [{$class}] given.",
    );
})->with([
    'not a class' => [fn (): string => 'not-a-class'],
    'not a model' => [fn (): string => stdClass::class],
    'a model that cannot answer connection queries' => [fn (): string => (new class extends Model {})::class],
]);

it('resolves the table the configured model reads from', function (): void {
    expect(ConnectionModel::table())->toBe('connections');

    config()->set('connections.table', 'links');

    expect(ConnectionModel::table())->toBe('links');
});
