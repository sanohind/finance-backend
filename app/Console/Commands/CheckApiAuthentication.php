<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Local\Partner;
use App\Services\BusinessPartnerUnifiedService;
use Illuminate\Support\Facades\DB;

class CheckApiAuthentication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:check-auth {bp_code? : Specific bp_code to check} {--user-id= : Specific user ID to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check API authentication and user access issues';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $userId = $this->option('user-id');

        $this->info('=== API AUTHENTICATION CHECK ===');
        $this->newLine();

        if ($userId) {
            $this->checkSpecificUser($userId, $unifiedService);
        } elseif ($bpCode) {
            $this->checkSpecificBpCode($bpCode, $unifiedService);
        } else {
            $this->checkAllUsers($unifiedService);
        }

        return 0;
    }

    private function checkSpecificUser($userId, $unifiedService)
    {
        $this->info("=== CHECKING USER ID: {$userId} ===");
        $this->newLine();

        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ User with ID {$userId} not found!");
            return;
        }

        $this->info("✓ User found: {$user->name}");
        $this->info("  - Email: {$user->email}");
        $this->info("  - Role: {$user->role}");
        $this->info("  - BP_CODE: {$user->bp_code}");
        $this->info("  - Status: " . ($user->status ? 'Active' : 'Inactive'));

        if ($user->bp_code) {
            $this->newLine();
            $this->checkBpCodeAccess($user->bp_code, $unifiedService);
        }
    }

    private function checkSpecificBpCode($bpCode, $unifiedService)
    {
        $this->info("=== CHECKING BP_CODE: {$bpCode} ===");
        $this->newLine();

        // Check if bp_code exists in business_partner table
        $partner = Partner::where('bp_code', $bpCode)->first();
        if (!$partner) {
            $this->error("❌ BP_CODE '{$bpCode}' not found in business_partner table!");
            return;
        }

        $this->info("✓ BP_CODE found in business_partner table");
        $this->info("  - Name: {$partner->bp_name}");
        $this->info("  - Parent BP_CODE: " . ($partner->parent_bp_code ?? 'None'));

        // Check if any user has this bp_code
        $users = User::where('bp_code', $bpCode)->get();
        if ($users->isEmpty()) {
            $this->warn("⚠ No users found with bp_code: {$bpCode}");
        } else {
            $this->info("✓ Found " . $users->count() . " user(s) with this bp_code:");
            foreach ($users as $user) {
                $this->line("  - User ID: {$user->id}, Name: {$user->name}, Role: {$user->role}, Status: " . ($user->status ? 'Active' : 'Inactive'));
            }
        }

        $this->newLine();
        $this->checkBpCodeAccess($bpCode, $unifiedService);
    }

    private function checkAllUsers($unifiedService)
    {
        $this->info("=== CHECKING ALL USERS ===");
        $this->newLine();

        $users = User::all();
        $this->info("Total users: {$users->count()}");

        $usersWithBpCode = $users->whereNotNull('bp_code');
        $usersWithoutBpCode = $users->whereNull('bp_code');

        $this->info("Users with BP_CODE: " . $usersWithBpCode->count());
        $this->info("Users without BP_CODE: " . $usersWithoutBpCode->count());

        // Check users with bp_code
        if ($usersWithBpCode->count() > 0) {
            $this->newLine();
            $this->info("=== USERS WITH BP_CODE ===");
            
            foreach ($usersWithBpCode as $user) {
                $this->line("User ID: {$user->id}, Name: {$user->name}, BP_CODE: {$user->bp_code}, Role: {$user->role}");
                
                // Check if bp_code exists in business_partner table
                $partner = Partner::where('bp_code', $user->bp_code)->first();
                if (!$partner) {
                    $this->warn("  ⚠ BP_CODE '{$user->bp_code}' not found in business_partner table!");
                } else {
                    $this->line("  ✓ BP_CODE found in business_partner table");
                }
            }
        }

        // Check for potential issues
        $this->newLine();
        $this->info("=== POTENTIAL AUTHENTICATION ISSUES ===");
        
        $issues = [];
        
        // Check for inactive users
        $inactiveUsers = $users->where('status', false);
        if ($inactiveUsers->count() > 0) {
            $issues[] = "Found " . $inactiveUsers->count() . " inactive users";
        }

        // Check for users with invalid bp_code
        foreach ($usersWithBpCode as $user) {
            $partner = Partner::where('bp_code', $user->bp_code)->first();
            if (!$partner) {
                $issues[] = "User {$user->name} has invalid bp_code: {$user->bp_code}";
            }
        }

        // Check for duplicate bp_codes
        $bpCodeCounts = $usersWithBpCode->groupBy('bp_code')->map->count();
        $duplicateBpCodes = $bpCodeCounts->filter(function($count) {
            return $count > 1;
        });
        
        if ($duplicateBpCodes->count() > 0) {
            $issues[] = "Found duplicate bp_codes: " . $duplicateBpCodes->keys()->implode(', ');
        }

        if (empty($issues)) {
            $this->info("✓ No authentication issues found");
        } else {
            $this->warn("Found " . count($issues) . " potential issues:");
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
        }
    }

    private function checkBpCodeAccess($bpCode, $unifiedService)
    {
        $this->info("=== BP_CODE ACCESS CHECK ===");
        
        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        $this->info("Normalized BP_CODE: {$normalizedBpCode}");

        // Check unified service access
        try {
            $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);
            $this->info("✓ Unified service access successful");
            $this->info("  - Unified BP_CODES: " . $unifiedBpCodes->count());
            
            if ($unifiedBpCodes->count() > 0) {
                $this->line("  - Codes: " . $unifiedBpCodes->implode(', '));
            }
        } catch (\Exception $e) {
            $this->error("✗ Unified service access failed: " . $e->getMessage());
        }

        // Check database access
        try {
            $partner = Partner::where('bp_code', $normalizedBpCode)->first();
            if ($partner) {
                $this->info("✓ Database access successful");
            } else {
                $this->warn("⚠ BP_CODE not found in database");
            }
        } catch (\Exception $e) {
            $this->error("✗ Database access failed: " . $e->getMessage());
        }

        // Check if it's old or new system
        if ($unifiedService->isOldSystemBpCode($normalizedBpCode)) {
            $this->info("  - System: OLD (has suffix)");
            $baseBpCode = $unifiedService->getBaseBpCode($normalizedBpCode);
            $this->info("  - Base BP_CODE: {$baseBpCode}");
            
            // Check parent relationship
            $partner = Partner::where('bp_code', $normalizedBpCode)->first();
            if ($partner && $partner->parent_bp_code !== $baseBpCode) {
                $this->warn("  ⚠ Parent relationship incorrect");
                $this->line("    Current: " . ($partner->parent_bp_code ?? 'NULL'));
                $this->line("    Expected: {$baseBpCode}");
            } else {
                $this->info("  ✓ Parent relationship correct");
            }
        } else {
            $this->info("  - System: NEW (no suffix)");
        }

        // Check data availability
        try {
            $invLines = DB::table('inv_line')->where('bp_id', $normalizedBpCode)->count();
            $invHeaders = DB::table('inv_header')->where('bp_code', $normalizedBpCode)->count();
            
            $this->info("  - InvLine records: {$invLines}");
            $this->info("  - InvHeader records: {$invHeaders}");
            
            if ($invLines === 0 && $invHeaders === 0) {
                $this->warn("  ⚠ No data found for this bp_code");
            }
        } catch (\Exception $e) {
            $this->error("  ✗ Data access failed: " . $e->getMessage());
        }
    }
}
