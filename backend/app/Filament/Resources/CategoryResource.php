<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use App\Services\Catalog\CategoryDeletionService;
use App\Traits\GeneratesUniqueSlug;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class CategoryResource extends Resource
{
    use GeneratesUniqueSlug;

    protected static ?string $model = Category::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static \UnitEnum|string|null $navigationGroup = 'Danh mục';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Danh mục';

    protected static ?string $modelLabel = 'Danh mục';

    protected static ?string $pluralModelLabel = 'Danh mục';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make()->components([
                Field\TextInput::make('name')
                    ->label('Tên danh mục')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->columnSpanFull()
                    ->afterStateUpdated(function (string $operation, ?string $state, \Filament\Schemas\Components\Utilities\Set $set): void {
                        if ($operation !== 'create' || blank($state)) {
                            return;
                        }

                        $set('slug', static::slugFromName($state));
                    }),
                Field\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->unique(Category::class, 'slug', ignoreRecord: true),
                Field\Select::make('parent_id')
                    ->label('Danh mục cha')
                    ->relationship('parent', 'name', modifyQueryUsing: function ($query, ?Category $record) {
                        $q = $query->with('parent.parent');

                        return $record ? $q->whereNotIn('id', [...$record->getDescendantIds(), $record->id]) : $q;
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getBreadcrumb())
                    ->rules([
                        fn (?Category $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                            if (! $value) {
                                return;
                            }

                            $parent = Category::find($value);
                            if (! $parent) {
                                return;
                            }

                            if ($record) {
                                $descendantIds = $record->getDescendantIds();
                                if (in_array($value, $descendantIds)) {
                                    $fail('Không thể chọn danh mục con cháu làm danh mục cha (tránh vòng lặp vô hạn).');

                                    return;
                                }
                            }

                            $parentDepth = $parent->getDepth();
                            $maxDepthBranch = 1;

                            if ($record) {
                                $maxDepthBranch = $record->getMaxDescendantDepth() + 1;
                            }

                            if (($parentDepth + $maxDepthBranch) > 2) {
                                $fail('Thao tác này làm vượt quá giới hạn 3 cấp danh mục. Vui lòng kiểm tra lại cấu trúc nhánh.');
                            }
                        },
                    ])
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->placeholder('— Không có (Danh mục gốc) —'),
            ])->columnSpanFull(),
        ]);
    }

    public static function deleteBlockReason(Category $category): ?string
    {
        if ($category->children()->exists()) {
            return "Danh mục \"{$category->name}\" đang có ".$category->children()->count().' danh mục con. Hãy xóa hoặc chuyển các danh mục con trước.';
        }

        if ($category->books()->exists()) {
            return "Danh mục \"{$category->name}\" đang liên kết với ".$category->books()->count().' sách. Hãy gán sách sang danh mục khác trước khi xóa.';
        }

        return null;
    }

    public static function haltDeleteIfBlocked(Category $record, Actions\DeleteAction|Actions\DeleteBulkAction $action): void
    {
        $reason = static::deleteBlockReason($record);

        if ($reason === null) {
            return;
        }

        \Filament\Notifications\Notification::make()
            ->title('Không thể xóa')
            ->body($reason)
            ->danger()
            ->send();

        $action->halt();
    }

    public static function haltBulkDeleteIfBlocked(EloquentCollection $records, Actions\DeleteBulkAction $action): void
    {
        foreach ($records as $record) {
            static::haltDeleteIfBlocked($record, $action);
        }
    }

    public static function configureDeleteAction(Actions\DeleteAction $action): Actions\DeleteAction
    {
        return $action
            ->before(fn (Category $record, Actions\DeleteAction $action): mixed => static::haltDeleteIfBlocked($record, $action))
            ->using(function (Category $record): bool {
                try {
                    return app(CategoryDeletionService::class)->delete($record);
                } catch (ValidationException $exception) {
                    static::notifyDeleteFailed($exception);

                    return false;
                }
            });
    }

    public static function configureBulkDeleteAction(Actions\DeleteBulkAction $action): Actions\DeleteBulkAction
    {
        return $action
            ->before(fn (EloquentCollection $records, Actions\DeleteBulkAction $action): mixed => static::haltBulkDeleteIfBlocked($records, $action))
            ->using(function (Actions\DeleteBulkAction $action, EloquentCollection $records): void {
                try {
                    app(CategoryDeletionService::class)->deleteMany($records);
                    $action->reportBulkProcessingSuccessfulRecordsCount($records->count());
                } catch (ValidationException $exception) {
                    static::notifyDeleteFailed($exception);
                    $action->reportCompleteBulkProcessingFailure();
                }
            });
    }

    private static function notifyDeleteFailed(ValidationException $exception): void
    {
        Notification::make()
            ->title('Không thể xóa')
            ->body(collect($exception->errors())->flatten()->first() ?? 'Danh mục không thể xóa do dữ liệu liên quan vừa thay đổi.')
            ->danger()
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Danh mục cha')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Actions\EditAction::make(),
                static::configureDeleteAction(Actions\DeleteAction::make()),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    static::configureBulkDeleteAction(Actions\DeleteBulkAction::make()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
