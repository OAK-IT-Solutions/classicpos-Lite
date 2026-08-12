<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::connection('landlord')->getSchemaBuilder()->hasIndex('agent_referrals', 'agent_referrals_referral_code_unique')) {
            DB::connection('landlord')->getSchemaBuilder()->table('agent_referrals', function ($table) {
                $table->unique('referral_code');
            });
        }
    }

    public function down(): void
    {
        DB::connection('landlord')->getSchemaBuilder()->table('agent_referrals', function ($table) {
            $table->dropUnique('agent_referrals_referral_code_unique');
        });
    }
};
