<?php

declare(strict_types=1);

use Erencagliz\LaravelIndexAdvisor\Services\QueryFingerprint;

it('produces deterministic fingerprints', function (): void {
    $fp = new QueryFingerprint();

    $a = $fp->make('select * from posts where id = ?', 'mysql', 'posts');
    $b = $fp->make('select * from posts where id = ?', 'mysql', 'posts');

    expect($a)->toBe($b);
});

it('changes fingerprint when connection or table changes', function (): void {
    $fp = new QueryFingerprint();

    $base = $fp->make('select * from posts where id = ?', 'mysql', 'posts');
    $otherConnection = $fp->make('select * from posts where id = ?', 'pgsql', 'posts');
    $otherTable = $fp->make('select * from posts where id = ?', 'mysql', 'articles');

    expect($otherConnection)->not->toBe($base);
    expect($otherTable)->not->toBe($base);
});

