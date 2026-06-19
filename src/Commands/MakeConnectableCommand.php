<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:connectable')]
final class MakeConnectableCommand extends GeneratorCommand
{
    protected $name = 'make:connectable';

    protected $description = 'Create a new Eloquent model that can form connections';

    protected $type = 'Model';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/connectable.model.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return is_dir(app_path('Models'))
            ? $rootNamespace.'\Models'
            : $rootNamespace;
    }

    public function handle(): ?bool
    {
        if ($this->option('existing')) {
            $this->printExistingGuidance();

            return null;
        }

        return parent::handle();
    }

    private function printExistingGuidance(): void
    {
        $name = $this->argument('name');
        $model = is_string($name) ? $name : '';

        $this->components->info("To make {$model} connectable, add the trait and interface to it:");
        $this->line('');
        $this->line('  use RoundlyConsulting\\Connections\\Concerns\\HasConnections;');
        $this->line('  use RoundlyConsulting\\Connections\\Contracts\\Connectable;');
        $this->line('');
        $this->line("  class {$model} extends Model implements Connectable");
        $this->line('  {');
        $this->line('      use HasConnections;');
        $this->line('  }');
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['existing', null, InputOption::VALUE_NONE, 'Print guidance for adding connection support to an existing model'],
        ];
    }
}
