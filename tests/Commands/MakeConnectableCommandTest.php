<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * The generator writes into app/Models: a throwaway app/ per test, never the shared testbench
 * skeleton every parallel process boots from. The app path is read when the command runs, so
 * pointing it here is enough. The namespace is resolved first — Laravel derives it by
 * matching app/ against the skeleton's composer.json, which a sandbox would not match.
 */
beforeEach(function (): void {
    $this->app->getNamespace();
    $this->app->useAppPath($this->sandbox = sys_get_temp_dir().'/connections-make-'.bin2hex(random_bytes(6)));

    File::ensureDirectoryExists(app_path('Models'));
});

afterEach(fn () => File::deleteDirectory($this->sandbox));

test('it generates into the sandbox, never the shared skeleton', function (): void {
    expect(app_path('Models'))->toContain('connections-make-');
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
