<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('record_index');
            $table->json('original')->nullable();
            $table->text('semantic_description');
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['dataset_id', 'record_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_records');
    }
};
