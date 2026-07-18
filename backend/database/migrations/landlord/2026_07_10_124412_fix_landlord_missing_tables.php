<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('landlord')->hasTable('notifications')) {
            Schema::connection('landlord')->create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->uuidMorphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        $schema = Schema::connection('landlord');
        if ($schema->hasTable('personal_access_tokens')) {
            $type = $schema->getConnection()->select(
                "SELECT data_type FROM information_schema.columns WHERE table_name='personal_access_tokens' AND column_name='tokenable_id'"
            );
            if (!empty($type) && $type[0]->data_type !== 'uuid') {
                $schema->getConnection()->statement(
                    'ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE uuid USING tokenable_id::text::uuid'
                );
                $schema->getConnection()->statement(
                    'ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id SET NOT NULL'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('notifications');
    }
};
