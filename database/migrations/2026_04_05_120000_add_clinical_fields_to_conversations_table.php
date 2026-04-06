<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('clinical_stage')->default('anamnesis')->after('last_message_at');
            $table->json('clinical_snapshot')->nullable()->after('clinical_stage');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['clinical_stage', 'clinical_snapshot']);
        });
    }
};