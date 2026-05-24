<?php

use App\Support\Catalog\CategoryBreadcrumbNormalizer;

test('category breadcrumb normalizer handles case dash and spacing', function (): void {
    $input = '  Sách   tiếng Việt  >  Tiểu sử  –  Hồi ký  ';
    $expected = 'sách tiếng việt > tiểu sử - hồi ký';

    expect(CategoryBreadcrumbNormalizer::normalize($input))->toBe($expected);
});

test('category breadcrumb normalizer preserves vietnamese diacritics', function (): void {
    $input = 'Văn học > Tiểu thuyết';

    expect(CategoryBreadcrumbNormalizer::normalize($input))->toBe('văn học > tiểu thuyết');
});
