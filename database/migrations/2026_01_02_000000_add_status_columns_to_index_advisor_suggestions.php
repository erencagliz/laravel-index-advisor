<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('index_advisor_suggestions', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('dismissed_at')->nullable()->after('accepted_at');
            $table->string('dismissed_reason')->nullable()->after('dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('index_advisor_suggestions', function (Blueprint $table): void {
            $table->dropColumn(['accepted_at', 'dismissed_at', 'dismissed_reason']);
        });
    }
};

