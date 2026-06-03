<?php

use App\Services\Media\BannerImageStorageService;

test('normalizeDestroyPublicId strips file extension', function (): void {
    $svc = new BannerImageStorageService;

    expect($svc->normalizeDestroyPublicId('book_ecommerce/banners/home/home-banner-abc'))->toBe('book_ecommerce/banners/home/home-banner-abc')
        ->and($svc->normalizeDestroyPublicId('book_ecommerce/banners/home/home-banner-abc.jpg'))->toBe('book_ecommerce/banners/home/home-banner-abc');
});

test('cloudinaryUploadOptionsForImageAtPath splits folder and public_id', function (): void {
    $svc = new BannerImageStorageService;

    $opts = $svc->cloudinaryUploadOptionsForImageAtPath('book_ecommerce/banners/home/home-banner-abc');

    expect($opts)->toMatchArray([
        'folder' => 'book_ecommerce/banners/home',
        'public_id' => 'home-banner-abc',
        'resource_type' => 'image',
    ]);
});

test('newBannerImageBasename has no path separators', function (): void {
    $svc = new BannerImageStorageService;

    expect($svc->newBannerImageBasename())->toStartWith('home-banner-')
        ->and($svc->newBannerImageBasename())->not->toContain('/');
});

test('newBannerImagePublicId uses book_ecommerce banners home folder', function (): void {
    $svc = new BannerImageStorageService;

    $id = $svc->newBannerImagePublicId();

    expect($id)->toStartWith('book_ecommerce/banners/home/home-banner-')
        ->and($id)->not->toContain('.jpg');
});

test('homeBannersFolder returns fixed home banner path', function (): void {
    $svc = new BannerImageStorageService;

    expect($svc->homeBannersFolder())->toBe('book_ecommerce/banners/home');
});
