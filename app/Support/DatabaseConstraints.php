<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DatabaseConstraints
{
    public static function dropCheck(string $table, string $constraint): void
    {
        foreach ([$table, $constraint] as $identifier) {
            if (preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier) !== 1) {
                throw new InvalidArgumentException('Unsafe database identifier.');
            }
        }

        $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');
        $kind = str_contains($version, 'MariaDB') ? 'CONSTRAINT' : 'CHECK';

        DB::statement("ALTER TABLE `{$table}` DROP {$kind} `{$constraint}`");
    }
}
