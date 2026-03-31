<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Books';

    protected static ?string $modelLabel = 'Book';

    protected static ?string $pluralModelLabel = 'Books';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Tabs::make('Tabs')->tabs([
                Layout\Tabs\Tab::make('General Information')->components([
                    Field\TextInput::make('name')
                        ->label('Book Name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, \Filament\Schemas\Components\Utilities\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    Field\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Field\TextInput::make('sku')
                        ->label('SKU')
                        ->unique(ignoreRecord: true),

                    Layout\Grid::make(3)->components([
                        Field\TextInput::make('original_price')
                            ->label('Original Price')
                            ->default(0)
                            ->numeric()
                            ->required(),
                        Field\TextInput::make('selling_price')
                            ->label('Selling Price')
                            ->default(0)
                            ->numeric()
                            ->required(),
                        Field\Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),
                    ]),
                ]),
                Layout\Tabs\Tab::make('Classification')->components([
                    Field\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->label('Supplier')->searchable()->preload(),
                    Field\Select::make('publisher_id')
                        ->relationship('publisher', 'name')
                        ->label('Publisher')->searchable()->preload(),
                    Field\Select::make('authors')
                        ->relationship('authors', 'name')
                        ->label('Authors')
                        ->multiple()
                        ->preload(),
                    Field\Select::make('categories')
                        ->relationship('categories', 'name')
                        ->label('Categories')
                        ->multiple()
                        ->preload(),
                ]),
                Layout\Tabs\Tab::make('Images')->components([
                    Field\Repeater::make('images')
                        ->relationship('images')
                        ->schema([
                            Field\FileUpload::make('image_url')
                                ->label('Image')
                                ->disk('cloudinary')
                                ->directory('books')
                                ->image()
                                ->imageEditor()
                                ->required(),
                            Field\Hidden::make('sort_order'),
                        ])
                        ->grid(4)
                        ->reorderable('sort_order')
                        ->itemLabel(fn (array $state): ?string => $state['image_url'] ?? 'New Image')
                        ->columnSpanFull()
                        ->helperText('The first image in the sequence will be used as the thumbnail.'),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Thumbnail')
                    ->disk('cloudinary')
                    ->square(),
                Tables\Columns\TextColumn::make('name')->label('Book Name')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('selling_price')->label('Selling Price')->money('VND')->sortable(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Is Active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status'),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
