<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('index_advisor_queries', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint')->unique();
            $table->string('connection_name');
            $table->string('table_name')->nullable();
            $table->text('normalized_sql');
            $table->text('sample_raw_sql')->nullable();
            $table->unsignedBigInteger('executions')->default(0);
            $table->unsignedBigInteger('total_time_ms')->default(0);
            $table->decimal('avg_time_ms', 10, 2)->nullable();
            $table->unsignedInteger('max_time_ms')->nullable();
            $table->unsignedInteger('p95_time_ms')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->string('parse_status')->default('pending');
            $table->json('parse_warnings')->nullable();
            $table->json('shape')->nullable();
            $table->timestamps();
        });

        Schema::create('index_advisor_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint');
            $table->string('connection_name');
            $table->string('table_name');
            $table->json('suggested_columns');
            $table->string('suggested_index_type')->default('index');
            $table->string('suggested_index_name')->nullable();
            $table->text('reason');
            $table->unsignedTinyInteger('confidence_score');
            $table->json('supporting_stats')->nullable();
            $table->json('similar_existing_indexes')->nullable();
            $table->json('warnings')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['fingerprint', 'status'], 'idx_index_advisor_suggestions_fingerprint_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_advisor_suggestions');
        Schema::dropIfExists('index_advisor_queries');
    }
};

