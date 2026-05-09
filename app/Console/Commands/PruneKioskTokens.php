<?php

namespace App\Console\Commands;

use App\Models\KioskScanResult;
use App\Models\KioskToken;
use Illuminate\Console\Command;

/**
 * Prune expired kiosk tokens and old scan results.
 * Schedule: every 5 minutes in routes/console.php
 *
 *   Schedule::command('kiosk:prune')->everyFiveMinutes();
 */
class PruneKioskTokens extends Command
{
    protected $signature   = 'kiosk:prune';
    protected $description = 'Delete expired kiosk QR tokens and old scan results';

    public function handle(): int
    {
        // Delete tokens expired more than 5 minutes ago
        $deletedTokens = KioskToken::where('expires_at', '<', now()->subMinutes(5))
                                   ->delete();

        // Delete scan results older than 24 hours
        $deletedScans = KioskScanResult::where('scanned_at', '<', now()->subDay())
                                       ->delete();

        $this->info("Pruned {$deletedTokens} expired tokens and {$deletedScans} old scan results.");

        return Command::SUCCESS;
    }
}