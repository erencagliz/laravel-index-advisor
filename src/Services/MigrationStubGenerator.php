<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Services;

use Erencagliz\LaravelIndexAdvisor\DTO\IndexSuggestion;

final class MigrationStubGenerator
{
    public function buildMigrationClass(IndexSuggestion $suggestion, ?string $customName = null): string
    {
        $table = $suggestion->table;
        $columnsArray = $suggestion->columns;
        $indexName = $suggestion->fingerprint . '_idx';

        $columnsExport = var_export($columnsArray, true);

        $className = $customName ?? 'AddIndexTo' . $this->studly($table) . 'Table';

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('$table', function (Blueprint \$table): void {
            \$table->index($columnsExport, '$indexName');
        });
    }

    public function down(): void
    {
        Schema::table('$table', function (Blueprint \$table): void {
            \$table->dropIndex('$indexName');
        });
    }
};

PHP;
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);

        return str_replace(' ', '', $value);
    }
}

