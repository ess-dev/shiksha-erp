<?php

namespace App\Domain\Fees;

use App\Domain\School\Queries\SchoolSettingsQuery;
use Illuminate\Support\Facades\DB;

/**
 * Hands out receipt numbers ("RCPT/2026/0042": the school's prefix, the
 * academic year's starting year, a padded count). Must be called inside an
 * Action's transaction: the counter row is locked until it commits, so two
 * payments can never get the same number.
 */
class ReceiptNumbers
{
    public function __construct(private readonly SchoolSettingsQuery $settings) {}

    public function next(string $yearStartsOn): string
    {
        $prefix = $this->settings->receiptPrefix();
        $year = substr($yearStartsOn, 0, 4);
        $name = "receipt:{$prefix}:{$year}";
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $connection->table('number_sequences')->insertOrIgnore([
            'name' => $name, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $current = (int) $connection->table('number_sequences')->where('name', $name)->lockForUpdate()->value('last_value');

        $connection->table('number_sequences')->where('name', $name)->update(['last_value' => $current + 1, 'updated_at' => now()]);

        return sprintf('%s/%s/%04d', $prefix, $year, $current + 1);
    }
}
