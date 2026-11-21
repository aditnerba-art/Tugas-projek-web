<?php

namespace App\Console\Commands;

use App\Services\LegacyPaymentImporter;
use Illuminate\Console\Command;

class MigrateLegacyPayments extends Command
{
    protected $signature = 'payments:migrate-legacy';

    protected $description = 'Import legacy service payment summaries into the payments ledger';

    public function handle(LegacyPaymentImporter $importer): int
    {
        $count = $importer->import();
        $this->info("Migrasi data lama selesai: {$count} pembayaran diimpor.");

        return self::SUCCESS;
    }
}
