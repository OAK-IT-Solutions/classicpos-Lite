<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('landlord')->statement('
            ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS support_tickets_category_check
        ');
    }

    public function down(): void
    {
        DB::connection('landlord')->statement('
            ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_category_check
            CHECK (category::text = ANY (ARRAY[
                \'billing\'::character varying,
                \'technical\'::character varying,
                \'feature_request\'::character varying,
                \'general\'::character varying,
                \'bug_report\'::character varying,
                \'account\'::character varying
            ]::text[]))
        ');
    }
};
