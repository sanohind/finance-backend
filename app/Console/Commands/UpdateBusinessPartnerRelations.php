<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;

class UpdateBusinessPartnerRelations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business-partner:update-relations {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update parent-child relationships in business partner table';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->info('Starting business partner relationship update...');
        
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $partners = Partner::all();
        $this->info("Found {$partners->count()} business partners to process");

        $updatedCount = 0;
        $errors = [];

        foreach ($partners as $partner) {
            try {
                if (preg_match('/-\d+$/', $partner->bp_code)) {
                    $base = preg_replace('/-\d+$/', '', $partner->bp_code);
                    
                    // Check if parent exists
                    $parent = Partner::where('bp_code', $base)->first();
                    
                    if ($parent) {
                        if ($partner->parent_bp_code !== $base) {
                            if (!$this->option('dry-run')) {
                                $partner->parent_bp_code = $base;
                                $partner->save();
                            }
                            $updatedCount++;
                            $this->line("✓ Updated {$partner->bp_code} -> parent: {$base}");
                        } else {
                            $this->line("- Skipped {$partner->bp_code} (already has correct parent)");
                        }
                    } else {
                        $this->warn("⚠ Parent not found for {$partner->bp_code} (base: {$base})");
                        $errors[] = "Parent not found for {$partner->bp_code} (base: {$base})";
                    }
                } else {
                    $this->line("- Skipped {$partner->bp_code} (no suffix)");
                }
            } catch (\Exception $e) {
                $this->error("✗ Error processing {$partner->bp_code}: " . $e->getMessage());
                $errors[] = "Error processing {$partner->bp_code}: " . $e->getMessage();
            }
        }

        $this->newLine();
        $this->info("=== SUMMARY ===");
        $this->info("Total partners processed: {$partners->count()}");
        $this->info("Partners updated: {$updatedCount}");
        $this->info("Errors: " . count($errors));

        if (!empty($errors)) {
            $this->newLine();
            $this->error("=== ERRORS ===");
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->info('Business partner relationships updated successfully!');
        }

        return 0;
    }
}
