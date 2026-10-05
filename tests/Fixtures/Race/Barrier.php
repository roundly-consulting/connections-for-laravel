<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests\Fixtures\Race;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Connections\Models\Connection;
use RuntimeException;
use Throwable;

/**
 * A two-process rendezvous on the connection write: each side stops right after its first
 * read of the `connections` table, until the other side has made the same read. Both
 * writers then hold whatever that read locked before either inserts — the interleaving a
 * real race only hits by luck, made deterministic.
 *
 * One side runs in the test process, the other in `connect.php`; they meet through marker
 * files in a shared directory.
 */
final class Barrier
{
    private bool $passed = false;

    public function __construct(
        private readonly string $directory,
        private readonly string $self,
        private readonly string $other,
        private readonly int $timeoutSeconds = 20,
    ) {}

    public function arm(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if ($this->passed || preg_match('/^select .* from [`"]?connections[`"]?/i', $query->sql) !== 1) {
                return;
            }

            $this->passed = true;

            touch($this->directory.'/'.$this->self);

            $deadline = microtime(true) + $this->timeoutSeconds;

            while (! file_exists($this->directory.'/'.$this->other)) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException("Barrier timed out waiting for [{$this->other}].");
                }

                usleep(10_000);
            }
        });
    }

    /**
     * Run the write and report its outcome as plain data, so both processes report alike.
     *
     * @param  Closure(): Connection  $write
     * @return array{ok: bool, id: int|string|null, error: string|null}
     */
    public static function attempt(Closure $write): array
    {
        try {
            $id = $write()->getKey();

            return ['ok' => true, 'id' => is_int($id) || is_string($id) ? $id : null, 'error' => null];
        } catch (Throwable $exception) {
            return ['ok' => false, 'id' => null, 'error' => $exception::class.': '.$exception->getMessage()];
        }
    }
}
