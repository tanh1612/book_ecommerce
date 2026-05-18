<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
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
                    ->afterStateUpdated(fn (string $operation, $state, \Filament\Schemas\Components\Utilities\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
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

                        // Loại trừ chính mình khỏi danh sách
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

                            // 1. CHỐNG VÒNG LẶP: Không được chọn con cháu làm cha (chỉ check khi edit)
                            if ($record) {
                                $descendantIds = $record->getDescendantIds();
                                if (in_array($value, $descendantIds)) {
                                    $fail('Không thể chọn danh mục con cháu làm danh mục cha (tránh vòng lặp vô hạn).');

                                    return;
                                }
                            }

                            // 2. KIỂM TRA ĐỘ SÂU TỐI ĐA
                            $parentDepth = $parent->getDepth();
                            $maxDepthBranch = 1; // Mặc định là chính nó

                            if ($record) {
                                // Nếu là edit, tính thêm độ sâu của các nhánh con hiện có
                                $maxDepthBranch = $record->getMaxDescendantDepth() + 1;
                            }

                            // Tổng độ sâu tối đa cho phép là 3 cấp (Root = 0, Cấp 2 = 1, Cấp 3 = 2)
                            if (($parentDepth + $maxDepthBranch) > 2) {
                                $fail('Thao tác này làm vượt quá giới hạn 3 cấp danh mục. Vui lòng kiểm tra lại cấu trúc nhánh.');
                            }
                        },
                    ])
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->placeholder('— Không có (Danh mục gốc) —'),
                Field\Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->inline(false)
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set): void {
                        if (! $state || ! self::hasInactiveAncestor((int) $get('parent_id'))) {
                            return;
                        }

                        $set('is_active', false);

                        \Filament\Notifications\Notification::make()
                            ->title('Không thể bật danh mục')
                            ->body('Không cho phép bật danh mục này vì danh mục cha đang tắt.')
                            ->danger()
                            ->send();
                    })
                    ->rules([
                        fn (\Filament\Schemas\Components\Utilities\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get): void {
                            if (! $value) {
                                return;
                            }

                            if (self::hasInactiveAncestor((int) $get('parent_id'))) {
                                $fail('Không cho phép bật danh mục này vì danh mục cha đang tắt.');
                            }
                        },
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    private static function hasInactiveAncestor(int $parentId): bool
    {
        while ($parentId > 0) {
            $parent = Category::query()
                ->whereKey($parentId)
                ->first(['id', 'parent_id', 'is_active']);

            if ($parent === null || ! $parent->is_active) {
                return true;
            }

            $parentId = (int) $parent->parent_id;
        }

        return false;
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
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Trạng thái'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (Category $record, Actions\DeleteAction $action) {
                        if ($record->children()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Không thể xóa')
                                ->body("Danh mục \"{$record->name}\" đang có ".$record->children()->count().' danh mục con. Hãy xóa hoặc chuyển các danh mục con trước.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Actions\DeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if ($record->children()->exists()) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Không thể xóa')
                                        ->body('Có danh mục được chọn đang chứa danh mục con. Hãy xử lý các danh mục con trước.')
                                        ->danger()
                                        ->send();
                                    $action->halt();
                                }
                            }
                        }),
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
