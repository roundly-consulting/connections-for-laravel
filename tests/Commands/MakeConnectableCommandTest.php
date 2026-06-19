<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    File::ensureDirectoryExists(app_path('Models'));
});

afterEach(function (): void {
    File::delete(app_path('Models/Organisation.php'));
    File::delete(app_path('Models/User.php'));
});

test('it generates a model with the trait and interface', function (): void {
    $this->artisan('make:connectable', ['name' => 'Organisation'])
        ->assertSuccessful();

    $path = app_path('Models/Organisation.php');

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)
        ->toContain('use RoundlyConsulting\Connections\Concerns\HasConnections;')
        ->toContain('use RoundlyConsulting\Connections\Contracts\Connectable;')
        ->toContain('class Organisation extends Model implements Connectable')
        ->toContain('use HasConnections;');
});

test('the existing flag prints guidance without writing a file', function (): void {
    $this->artisan('make:connectable', ['name' => 'User', '--existing' => true])
        ->expectsOutputToContain('use RoundlyConsulting\Connections\Concerns\HasConnections;')
        ->assertSuccessful();

    expect(File::exists(app_path('Models/User.php')))->toBeFalse();
});
