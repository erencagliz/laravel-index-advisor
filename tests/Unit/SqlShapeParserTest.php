<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\Parsers\SqlShapeParser;
use Erencagliz\LaravelIndexAdvisor\Parsers\SqlAstParser;
use Erencagliz\LaravelIndexAdvisor\Parsers\AstQueryShapeBuilder;

it('parses basic select query shape', function (): void {
    $parser = new SqlShapeParser(
        new SqlAstParser(),
        new AstQueryShapeBuilder()
    );

    $sql = 'select * from orders where tenant_id = ? and status = ? order by created_at desc limit ?';

    $shape = $parser->parse($sql);

    expect($shape->operationType)->toBe('select')
        ->and($shape->primaryTable)->toBe('orders')
        ->and($shape->whereColumns)->toEqualCanonicalizing(['tenant_id', 'status'])
        ->and($shape->orderByColumns)->toEqualCanonicalizing(['created_at']);
});

