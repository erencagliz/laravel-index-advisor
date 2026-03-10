<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('index_advisor_ignores', function (Blueprint $table): void {
            $table->id();
            $table->string('type'); // fingerprint|table|columns
            $table->string('fingerprint')->nullable();
            $table->string('table_name')->nullable();
            $table->json('columns')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['type', 'table_name'], 'idx_index_advisor_ignores_type_table');
            $table->index(['type', 'fingerprint'], 'idx_index_advisor_ignores_type_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_advisor_ignores');
    }
};

