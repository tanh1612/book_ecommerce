<?php

use App\Enums\Account\AccountRole;
use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\RelationManagers\BooksRelationManager;
use App\Models\Account;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function filamentAdmin(): Account
{
    return Account::factory()->create(['role' => AccountRole::Admin]);
}

test('edit book form can replace the only category with another', function (): void {
    $admin = filamentAdmin();
    $oldCategory = Category::factory()->create(['name' => 'Old Filament Cat']);
    $newCategory = Category::factory()->create(['name' => 'New Filament Cat']);
    $author = Author::factory()->create();

    $book = Book::factory()->create();
    $book->authors()->attach($author);
    $book->categories()->attach($oldCategory);

    Livewire::actingAs($admin)
        ->test(EditBook::class, ['record' => $book->getKey()])
        ->fillForm([
            'categories' => [$newCategory->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($book->fresh()->categories()->pluck('categories.id')->all())->toBe([$newCategory->id]);
});

test('edit book form rejects saving without categories', function (): void {
    $admin = filamentAdmin();
    $category = Category::factory()->create();
    $author = Author::factory()->create();

    $book = Book::factory()->create();
    $book->authors()->attach($author);
    $book->categories()->attach($category);

    Livewire::actingAs($admin)
        ->test(EditBook::class, ['record' => $book->getKey()])
        ->fillForm([
            'categories' => [],
        ])
        ->call('save')
        ->assertHasFormErrors(['categories']);

    expect($book->fresh()->categories()->whereKey($category->id)->exists())->toBeTrue();
});

test('edit category delete action deletes an empty category through deletion service', function (): void {
    $admin = filamentAdmin();
    $category = Category::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditCategory::class, ['record' => $category->getKey()])
        ->callAction('delete')
        ->assertNotified()
        ->assertRedirect(CategoryResource::getUrl('index'));

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

test('category books relation manager cannot detach the last category from a book', function (): void {
    $admin = filamentAdmin();
    $owner = Category::factory()->create(['name' => 'Owner Only Cat']);
    $book = Book::factory()->create(['name' => 'Single Cat Book']);
    $book->categories()->attach($owner);

    Livewire::actingAs($admin)
        ->test(BooksRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditCategory::class,
        ])
        ->callTableAction('detach', $book)
        ->assertNotified();

    expect($book->fresh()->categories()->whereKey($owner->id)->exists())->toBeTrue();
});

test('category books relation manager can detach when book has another category', function (): void {
    $admin = filamentAdmin();
    $owner = Category::factory()->create(['name' => 'Owner Detach Cat']);
    $spare = Category::factory()->create(['name' => 'Spare Detach Cat']);
    $book = Book::factory()->create(['name' => 'Multi Cat Book']);
    $book->categories()->attach([$owner->id, $spare->id]);

    Livewire::actingAs($admin)
        ->test(BooksRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditCategory::class,
        ])
        ->callTableAction('detach', $book)
        ->assertHasNoActionErrors();

    $ids = $book->fresh()->categories()->pluck('categories.id')->all();

    expect($ids)->toContain($spare->id)->not->toContain($owner->id);
});

test('category books relation manager can attach multiple books through assignment service', function (): void {
    $admin = filamentAdmin();
    $owner = Category::factory()->create(['name' => 'Attach Owner Cat']);
    $existing = Category::factory()->create(['name' => 'Existing Attach Cat']);
    $books = Book::factory()->count(2)->create();

    foreach ($books as $book) {
        $book->categories()->attach($existing);
    }

    Livewire::actingAs($admin)
        ->test(BooksRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditCategory::class,
        ])
        ->callTableAction('attach', data: [
            'recordId' => $books->pluck('id')->all(),
        ])
        ->assertHasNoActionErrors();

    foreach ($books as $book) {
        expect($book->fresh()->categories()->whereKey($owner->id)->exists())->toBeTrue();
    }
});

test('category books bulk detach does not partially detach when selection is invalid', function (): void {
    $admin = filamentAdmin();
    $owner = Category::factory()->create(['name' => 'Bulk Owner Cat']);
    $spare = Category::factory()->create(['name' => 'Bulk Spare Cat']);

    $onlyOwner = Book::factory()->create(['name' => 'Bulk Only Owner']);
    $onlyOwner->categories()->attach($owner);

    $withSpare = Book::factory()->create(['name' => 'Bulk With Spare']);
    $withSpare->categories()->attach([$owner->id, $spare->id]);

    Livewire::actingAs($admin)
        ->test(BooksRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditCategory::class,
        ])
        ->callTableBulkAction('detach', [$onlyOwner, $withSpare])
        ->assertNotified();

    expect($onlyOwner->fresh()->categories()->whereKey($owner->id)->exists())->toBeTrue()
        ->and($withSpare->fresh()->categories()->whereKey($owner->id)->exists())->toBeTrue();
});
