<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\Parsers\QueryNormalizer;

it('normalizes literals and whitespace', function (): void {
    $normalizer = new QueryNormalizer();

    $sql = "SELECT *  FROM `posts` WHERE user_id = 123 AND status = 'published'  LIMIT 10";

    $normalized = $normalizer->normalize($sql);

    expect($normalized)
        ->toBe('select * from posts where user_id = ? and status = ? limit ?');
});

