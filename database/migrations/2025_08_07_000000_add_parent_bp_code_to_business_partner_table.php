<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql2')->table('business_partner', function (Blueprint $table) {
            if (!Schema::connection('mysql2')->hasColumn('business_partner', 'parent_bp_code')) {
                $table->string('parent_bp_code')->nullable()->after('bp_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql2')->table('business_partner', function (Blueprint $table) {
            if (Schema::connection('mysql2')->hasColumn('business_partner', 'parent_bp_code')) {
                $table->dropColumn('parent_bp_code');
            }
        });
    }
};
