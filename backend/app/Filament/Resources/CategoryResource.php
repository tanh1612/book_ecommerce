<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
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
        return $schema->components([
            Layout\Section::make()->components([
                Field\TextInput::make('name')
                    ->label('Name')
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
                    ->label('Parent Category')
                    ->relationship('parent', 'name', modifyQueryUsing: function ($query, ?Category $record) {
                        $q = $query->with('parent.parent'); // Eager load
                        return $record ? $q->whereNotIn('id', [...$record->getDescendantIds(), $record->id]) : $q;
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getBreadcrumb())
                    ->rules([
                        fn () => function (string $attribute, $value, \Closure $fail) {
                            if (!$value) return; // Không chọn cha → OK
                            $parent = Category::find($value);
                            if ($parent && $parent->getDepth() >= 2) {
                                $fail('Chỉ cho phép tối đa 3 cấp danh mục (cha → con → cháu).');
                            }
                        }
                    ])
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->placeholder('— Không có (Danh mục gốc) —'),
                Field\Toggle::make('is_active')
                    ->label('Active')
                    ->inline(false)
                    ->default(true),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent Category')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Is Active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated At')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (Category $record, Actions\DeleteAction $action) {
                        if ($record->children()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Không thể xóa')
                                ->body("Danh mục \"{$record->name}\" đang có " . $record->children()->count() . " danh mục con. Hãy xóa hoặc chuyển các danh mục con trước.")
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
                                        ->body("Có danh mục được chọn đang chứa danh mục con. Hãy xử lý các danh mục con trước.")
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
