<?php

use App\Enums\Account\AccountRole;
use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Models\Account;
use App\Models\Banner;
use App\Services\Media\BannerImageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $storage = Mockery::mock(BannerImageStorageService::class);
    $storage->shouldReceive('deliveryUrlFromPublicId')
        ->andReturnUsing(fn (string $publicId): string => 'https://res.cloudinary.com/test/image/upload/'.$publicId.'.jpg');
    $storage->shouldReceive('deleteByPublicId')->andReturnNull();

    app()->instance(BannerImageStorageService::class, $storage);
});

function filamentBannerAdmin(): Account
{
    return Account::factory()->create(['role' => AccountRole::Admin]);
}

test('admin can open banner list page', function (): void {
    Livewire::actingAs(filamentBannerAdmin())
        ->test(ListBanners::class)
        ->assertSuccessful()
        ->assertActionExists('create');
});

test('banner list shows created banners in table', function (): void {
    $admin = filamentBannerAdmin();
    $banner = Banner::factory()->create(['title' => 'Banner hiển thị']);

    Livewire::actingAs($admin)
        ->test(ListBanners::class)
        ->assertCanSeeTableRecords([$banner]);
});

test('banner list filters active and inactive banners', function (): void {
    $admin = filamentBannerAdmin();
    $active = Banner::factory()->create(['title' => 'Active banner']);
    $inactive = Banner::factory()->inactive()->create(['title' => 'Inactive banner']);

    Livewire::actingAs($admin)
        ->test(ListBanners::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive])
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$active]);
});

test('admin can edit banner title and visibility without changing sort order manually', function (): void {
    $admin = filamentBannerAdmin();
    $banner = Banner::factory()->create([
        'title' => 'Before edit',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditBanner::class, ['record' => $banner->getKey()])
        ->fillForm([
            'title' => 'After edit',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $banner->refresh();

    expect($banner->title)->toBe('After edit')
        ->and($banner->sort_order)->toBe(3)
        ->and($banner->is_active)->toBeFalse();
});

test('banner table delete action removes record', function (): void {
    $admin = filamentBannerAdmin();
    $banner = Banner::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListBanners::class)
        ->callTableAction('delete', $banner)
        ->assertNotified();

    expect(Banner::query()->whereKey($banner->id)->exists())->toBeFalse();
});

test('banner table bulk delete removes selected records', function (): void {
    $admin = filamentBannerAdmin();
    $first = Banner::factory()->create();
    $second = Banner::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListBanners::class)
        ->callTableBulkAction('delete', [$first, $second])
        ->assertNotified();

    expect(Banner::query()->whereKey([$first->id, $second->id])->exists())->toBeFalse();
});

test('create banner form requires title', function (): void {
    Livewire::actingAs(filamentBannerAdmin())
        ->test(CreateBanner::class)
        ->fillForm([
            'title' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['title']);

    expect(Banner::query()->count())->toBe(0);
});

test('duplicate public id is rejected before persisting second banner', function (): void {
    $publicId = 'book_ecommerce/banners/home/home-banner-duplicate';

    Banner::factory()->create(['public_id' => $publicId]);

    expect(fn (): Banner => Banner::factory()->create(['public_id' => $publicId]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(Banner::query()->count())->toBe(1);
});

test('create banner form requires public id image', function (): void {
    Livewire::actingAs(filamentBannerAdmin())
        ->test(CreateBanner::class)
        ->fillForm([
            'title' => 'Missing image',
            'public_id' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['public_id']);

    expect(Banner::query()->count())->toBe(0);
});
