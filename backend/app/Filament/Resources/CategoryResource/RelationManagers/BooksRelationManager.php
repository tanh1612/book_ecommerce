<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use App\Models\Category;
use App\Services\Catalog\BookCategoryAssignmentService;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Danh sách sách';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Field\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Category $owner */
        $owner = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Ảnh bìa')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên sách')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Giá bán')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect()
                    ->action(function (array $data, Actions\AttachAction $action) use ($owner): void {
                        $books = Book::query()
                            ->whereKey(Arr::wrap($data['recordId'] ?? []))
                            ->get();

                        try {
                            app(BookCategoryAssignmentService::class)
                                ->bulkAttachCategoryToBooks($books, (int) $owner->getKey());
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Không thể gán danh mục')
                                ->body(collect($exception->errors())->flatten()->first()
                                    ?? 'Danh mục không còn tồn tại.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->actions([
                Actions\Action::make('edit_book')
                    ->label('Chỉnh sửa')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record): string => BookResource::getUrl('edit', ['record' => $record])),
                Actions\DetachAction::make()
                    ->action(function (Book $record, Actions\DetachAction $action) use ($owner): void {
                        try {
                            app(BookCategoryAssignmentService::class)->detachCategories($record, [$owner->id]);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Không thể tháo danh mục')
                                ->body(collect($exception->errors())->flatten()->first()
                                    ?? BookCategoryAssignmentService::MIN_CATEGORIES_MESSAGE)
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make()
                        ->action(function (EloquentCollection $records, Actions\DetachBulkAction $action) use ($owner): void {
                            try {
                                app(BookCategoryAssignmentService::class)->bulkDetachCategoryFromBooks($records, $owner->id);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Không thể tháo danh mục')
                                    ->body(collect($exception->errors())->flatten()->first()
                                        ?? BookCategoryAssignmentService::MIN_CATEGORIES_MESSAGE)
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}
