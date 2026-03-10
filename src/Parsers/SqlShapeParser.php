<?php

declare(strict_types=1);

namespace Erencagliz\LaravelIndexAdvisor\Parsers;

use Erencagliz\LaravelIndexAdvisor\DTO\ParsedQueryShape;

final class SqlShapeParser
{
    public function __construct(
        private readonly SqlAstParser $astParser,
        private readonly AstQueryShapeBuilder $builder,
    ) {
    }

    public function parse(string $normalizedSql): ParsedQueryShape
    {
        try {
            $ast = $this->astParser->parse($normalizedSql);

            return $this->builder->build($ast);
        } catch (\Throwable) {
            // In case AST parsing fails for any reason, fall back to a very conservative shape.
            return new ParsedQueryShape(
                operationType: 'unknown',
                primaryTable: null,
                involvedTables: [],
                whereColumns: [],
                joinColumns: [],
                orderByColumns: [],
                groupByColumns: [],
                limit: null,
                hasSubquery: false,
                parseWarnings: ['AST parsing failed; suggestion engine will be conservative.'],
            );
        }
    }
}

