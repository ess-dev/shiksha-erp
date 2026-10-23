<?php

namespace App\Domain\Students;

use Illuminate\Support\Facades\DB;

/**
 * Hands out the next number of a named series (admission numbers, TC numbers).
 * Must be called inside an Action's transaction: the row is locked until it
 * commits, so two requests can never get the same number.
 */
class NumberSequences
{
    public function next(string $name): int
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $connection->table('number_sequences')->insertOrIgnore([
            'name' => $name, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $current = (int) $connection->table('number_sequences')->where('name', $name)->lockForUpdate()->value('last_value');

        $connection->table('number_sequences')->where('name', $name)->update(['last_value' => $current + 1, 'updated_at' => now()]);

        return $current + 1;
    }

    /**
     * "ADM/2026/0042": prefix, the academic year's starting year, a padded count.
     */
    public function formatted(string $prefix, string $yearStartsOn, string $series): string
    {
        $year = substr($yearStartsOn, 0, 4);

        return sprintf('%s/%s/%04d', $prefix, $year, $this->next("{$series}:{$prefix}:{$year}"));
    }
}
