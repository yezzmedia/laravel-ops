<?php

declare(strict_types=1);

use YezzMedia\Ops\Pages\AuditEntryDetailsPage;

it('highlights removed and added segments in audit diff output', function (): void {
    $method = new ReflectionMethod(AuditEntryDetailsPage::class, 'decorateChangeRow');
    $method->setAccessible(true);

    $row = $method->invoke(null, [
        'field' => 'support_email',
        'oldPreview' => 'support@old.example.com',
        'oldRaw' => 'support@old.example.com',
        'newPreview' => 'support@new.example.com',
        'newRaw' => 'support@new.example.com',
    ]);

    expect($row['removedSegment'])->toBe('old')
        ->and($row['addedSegment'])->toBe('new')
        ->and($row['oldRaw'])->toBe('support@old.example.com')
        ->and($row['newRaw'])->toBe('support@new.example.com');
});

it('returns plain escaped output when no diff exists', function (): void {
    $method = new ReflectionMethod(AuditEntryDetailsPage::class, 'decorateChangeRow');
    $method->setAccessible(true);

    $row = $method->invoke(null, [
        'field' => 'name',
        'oldPreview' => 'unchanged',
        'oldRaw' => 'unchanged',
        'newPreview' => 'unchanged',
        'newRaw' => 'unchanged',
    ]);

    expect($row['removedSegment'])->toBeNull()
        ->and($row['addedSegment'])->toBeNull();
});
