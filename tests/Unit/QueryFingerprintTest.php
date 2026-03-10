<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\Services\QueryFingerprint;
use Erencagliz\LaravelIndexAdvisor\Parsers\QueryNormalizer;

it('produces deterministic fingerprints', function (): void {
    $fp = new QueryFingerprint();

    $a = $fp->make('select * from posts where id = ?', 'mysql', 'posts');
    $b = $fp->make('select * from posts where id = ?', 'mysql', 'posts');

    expect($a)->toBe($b);
});

it('keeps fingerprint stable across binding variations', function (): void {
    $normalizer = new QueryNormalizer();
    $fp = new QueryFingerprint();

    $sqlA = "select * from orders where tenant_id = 1 and status = 'active' limit 10";
    $sqlB = "select * from orders where tenant_id = 42 and status = 'pending' limit 25";

    $normA = $normalizer->normalize($sqlA);
    $normB = $normalizer->normalize($sqlB);

    $fingerA = $fp->make($normA, 'mysql', 'orders');
    $fingerB = $fp->make($normB, 'mysql', 'orders');

    expect($fingerA)->toBe($fingerB);
});

it('changes fingerprint when connection or table changes', function (): void {
    $fp = new QueryFingerprint();

    $base = $fp->make('select * from posts where id = ?', 'mysql', 'posts');
    $otherConnection = $fp->make('select * from posts where id = ?', 'pgsql', 'posts');
    $otherTable = $fp->make('select * from posts where id = ?', 'mysql', 'articles');

    expect($otherConnection)->not->toBe($base);
    expect($otherTable)->not->toBe($base);
});

