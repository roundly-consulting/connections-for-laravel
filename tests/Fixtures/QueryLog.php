<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests\Fixtures;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * Every statement against the `connections` table, in order, with whether it took a row
 * lock and the transaction level of the connection it ran on — plus a `commit` entry for
 * every commit (or savepoint release), carrying the level it left behind.
 *
 * SQLite compiles `lockForUpdate()` to nothing, so on SQLite the connection gets the
 * testing package's {@see LockRecordingGrammar}, which leaves a `lock-for-update` marker
 * instead. Postgres and MySQL emit a real `for update`. Either way the lock is visible.
 */
final class QueryLog
{
    /** @var list<array{sql: string, locked: bool, level: int, connection: string}> */
    public array $entries = [];

    public static function start(?string $connection = null): self
    {
        $log = new self;
        $database = DB::connection($connection);

        if ($database->getDriverName() === 'sqlite') {
            $database->setQueryGrammar(new LockRecordingGrammar($database));
        }

        DB::listen(static function (QueryExecuted $query) use ($log): void {
            if (preg_match('/\b(from|into|update) [`"]?connections[`"]?/i', $query->sql) !== 1) {
                return;
            }

            $sql = strtolower($query->sql);

            $log->entries[] = [
                'sql' => $sql,
                'locked' => str_contains($sql, 'lock-for-update') || str_contains($sql, 'for update'),
                'level' => $query->connection->transactionLevel(),
                'connection' => $query->connectionName,
            ];
        });

        Event::listen(TransactionCommitted::class, static function (TransactionCommitted $event) use ($log): void {
            $log->entries[] = [
                'sql' => 'commit',
                'locked' => false,
                'level' => $event->connection->transactionLevel(),
                'connection' => $event->connectionName,
            ];
        });

        return $log;
    }

    public function flush(): void
    {
        $this->entries = [];
    }

    /**
     * The position of the first statement matching the predicate, or null.
     *
     * @param  callable(array{sql: string, locked: bool, level: int, connection: string}): bool  $predicate
     */
    public function firstIndex(callable $predicate): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($predicate($entry)) {
                return $index;
            }
        }

        return null;
    }
}
