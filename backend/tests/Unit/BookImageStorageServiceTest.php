<?php

use App\Services\Media\BookImageStorageService;

test('normalizeDestroyPublicId strips file extension', function (): void {
    $svc = new BookImageStorageService;

    expect($svc->normalizeDestroyPublicId('book_ecommerce/books/foo/bar'))->toBe('book_ecommerce/books/foo/bar')
        ->and($svc->normalizeDestroyPublicId('book_ecommerce/books/foo/bar.jpg'))->toBe('book_ecommerce/books/foo/bar');
});

test('thumbnailDeliveryUrlFromDeliveryUrl injects transformation once', function (): void {
    $svc = new BookImageStorageService;

    $in = 'https://res.cloudinary.com/demo/image/upload/books/x/y.jpg';
    $out = $svc->thumbnailDeliveryUrlFromDeliveryUrl($in);

    expect($out)->toContain('/upload/c_fill,g_auto,w_300,h_400,q_auto,f_auto/')
        ->and($out)->toContain('books/x/y.jpg');
});

test('extractPublicIdFromUrl returns path without extension', function (): void {
    $svc = new BookImageStorageService;

    $url = 'https://res.cloudinary.com/demo/image/upload/v123/books/a/b.jpg';
    expect($svc->extractPublicIdFromUrl($url))->toBe('books/a/b');
});

test('cloudinaryUploadOptionsForImageAtPath splits folder and public_id', function (): void {
    $svc = new BookImageStorageService;

    $opts = $svc->cloudinaryUploadOptionsForImageAtPath('book_ecommerce/books/dante-dyer/dante-dyer-abc');

    expect($opts)->toMatchArray([
        'folder' => 'book_ecommerce/books/dante-dyer',
        'public_id' => 'dante-dyer-abc',
        'resource_type' => 'image',
    ]);
});

test('newBookImageBasename has no path separators', function (): void {
    $svc = new BookImageStorageService;

    expect($svc->newBookImageBasename('Dante Dyer'))->not->toContain('/');
});

test('newBookImagePublicId uses book_ecommerce books slug folder', function (): void {
    $svc = new BookImageStorageService;

    $id = $svc->newBookImagePublicId('Dante Dyer');

    expect($id)->toStartWith('book_ecommerce/books/dante-dyer/dante-dyer-')
        ->and($id)->not->toContain('.jpg');
});

test('bookImagesFolderForSlug normalizes slug', function (): void {
    $svc = new BookImageStorageService;

    expect($svc->bookImagesFolderForSlug('My Book!'))->toBe('book_ecommerce/books/my-book');
});
