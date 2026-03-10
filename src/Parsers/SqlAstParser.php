<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Parsers;

use PHPSQLParser\PHPSQLParser;

final class SqlAstParser
{
    private PHPSQLParser $parser;

    public function __construct(?PHPSQLParser $parser = null)
    {
        $this->parser = $parser ?? new PHPSQLParser();
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $sql): array
    {
        // php-sql-parser is lenient and will try its best to parse
        // We keep any exception handling at the call site if needed.
        return $this->parser->parse($sql, true);
    }
}

