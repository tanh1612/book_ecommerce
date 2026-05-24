<?php

use App\Traits\GeneratesUniqueSlug;

final class GeneratesUniqueSlugTestStub
{
    use GeneratesUniqueSlug;
}

test('slug from name treats slash as word separator', function (): void {
    expect(GeneratesUniqueSlugTestStub::slugFromName('Khởi Nghiệp/Kỹ Năng Làm Việc'))
        ->toBe('khoi-nghiep-ky-nang-lam-viec');
});

test('slug from name collapses repeated separators', function (): void {
    expect(GeneratesUniqueSlugTestStub::slugFromName('A  /  B'))
        ->toBe('a-b');
});

test('slug from name returns empty string for blank input', function (): void {
    expect(GeneratesUniqueSlugTestStub::slugFromName(null))->toBe('')
        ->and(GeneratesUniqueSlugTestStub::slugFromName(''))->toBe('');
});
