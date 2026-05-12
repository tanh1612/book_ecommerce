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

    protected static \UnitEnum|string|null $navigationGroup = 'Danh mục';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Sách';

    protected static ?string $modelLabel = 'Sách';

    protected static ?string $pluralModelLabel = 'Sách';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Tabs::make('Tabs')->tabs([
                Layout\Tabs\Tab::make('Thông tin chung')->components([
                    Field\TextInput::make('name')
                        ->label('Book Name')
                        ->required()
                        ->live(onBlur: true)
                        ->columnSpanFull()
                        ->afterStateUpdated(fn (string $operation, $state, \Filament\Schemas\Components\Utilities\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    Field\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),
                    Field\TextInput::make('sku')
                        ->label('SKU')
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),

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
                            ->label('Active')
                            ->inline(false)
                            ->default(true),
                    ])->columnSpanFull(),

                    Layout\Grid::make(2)->components([
                        Field\TextInput::make('review_count')
                            ->label('Đánh giá')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),
                        Field\TextInput::make('average_rating')
                            ->label('Điểm trung bình')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),
                    ])->columnSpanFull()->visibleOn('edit'),
                ]),
                Layout\Tabs\Tab::make('Phân loại')->components([
                    Field\Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->label('Supplier')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Field\Select::make('publisher_id')
                        ->relationship('publisher', 'name')
                        ->label('Publisher')
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                    Field\Select::make('authors')
                        ->relationship('authors', 'name')
                        ->label('Authors')
                        ->multiple()
                        ->preload()
                        ->columnSpanFull(),
                    Field\Select::make('categories')
                        ->relationship('categories', 'name', modifyQueryUsing: fn ($query) => $query->with('parent.parent'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getBreadcrumb())
                        ->label('Categories')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->columnSpanFull(),
                ]),
                Layout\Tabs\Tab::make('Chi tiết sách')->components([
                    Layout\Section::make()->relationship('detail')->schema([
                        Field\Textarea::make('description')
                            ->label('Mô tả')
                            ->rows(5)
                            ->columnSpanFull(),

                        Layout\Grid::make(2)->components([
                            Field\Select::make('language')
                                ->label('Ngôn ngữ')
                                ->options(\App\Enums\Book\BookLanguage::class)
                                ->default(\App\Enums\Book\BookLanguage::VI)
                                ->required(),
                            Field\TextInput::make('translator')
                                ->label('Dịch giả'),
                            Field\TextInput::make('publication_year')
                                ->label('Năm xuất bản')
                                ->numeric(),
                            Field\TextInput::make('num_pages')
                                ->label('Số trang')
                                ->numeric()
                                ->integer(),
                            Field\TextInput::make('weight')
                                ->label('Khối lượng (g)')
                                ->numeric(),
                        ])->columnSpanFull(),

                        Layout\Fieldset::make('Kích thước (cm)')->schema([
                            Field\Hidden::make('dimensions'),
                            Field\TextInput::make('dim_length')
                                ->label('Chiều dài')
                                ->numeric()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Field\TextInput $component, ?\App\Models\BookDetail $record) {
                                    if ($record && $record->dimensions) {
                                        preg_match_all('/(\d+(\.\d+)?)/', $record->dimensions, $matches);
                                        $component->state($matches[0][0] ?? null);
                                    }
                                })
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                                    $l = $get('dim_length');
                                    $w = $get('dim_width');
                                    $h = $get('dim_height');
                                    if ($l !== null || $w !== null || $h !== null) {
                                        $set('dimensions', ($l ?: '0').' x '.($w ?: '0').' x '.($h ?: '0').' cm');
                                    } else {
                                        $set('dimensions', null);
                                    }
                                }),
                            Field\TextInput::make('dim_width')
                                ->label('Chiều rộng')
                                ->numeric()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Field\TextInput $component, ?\App\Models\BookDetail $record) {
                                    if ($record && $record->dimensions) {
                                        preg_match_all('/(\d+(\.\d+)?)/', $record->dimensions, $matches);
                                        $component->state($matches[0][1] ?? null);
                                    }
                                })
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                                    $l = $get('dim_length');
                                    $w = $get('dim_width');
                                    $h = $get('dim_height');
                                    if ($l !== null || $w !== null || $h !== null) {
                                        $set('dimensions', ($l ?: '0').' x '.($w ?: '0').' x '.($h ?: '0').' cm');
                                    } else {
                                        $set('dimensions', null);
                                    }
                                }),
                            Field\TextInput::make('dim_height')
                                ->label('Độ dày')
                                ->numeric()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Field\TextInput $component, ?\App\Models\BookDetail $record) {
                                    if ($record && $record->dimensions) {
                                        preg_match_all('/(\d+(\.\d+)?)/', $record->dimensions, $matches);
                                        $component->state($matches[0][2] ?? null);
                                    }
                                })
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                                    $l = $get('dim_length');
                                    $w = $get('dim_width');
                                    $h = $get('dim_height');
                                    if ($l !== null || $w !== null || $h !== null) {
                                        $set('dimensions', ($l ?: '0').' x '.($w ?: '0').' x '.($h ?: '0').' cm');
                                    } else {
                                        $set('dimensions', null);
                                    }
                                }),
                        ])->columns(3)->columnSpanFull(),

                        Field\Select::make('format')
                            ->label('Định dạng')
                            ->options(\App\Enums\Book\BookFormat::class)
                            ->required()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
                ]),
                Layout\Tabs\Tab::make('Hình ảnh')->components([
                    Field\Repeater::make('images')
                        ->relationship('images')
                        ->schema([
                            Field\FileUpload::make('public_id')
                                ->label('Image')
                                ->disk('cloudinary')
                                ->directory(fn (\Filament\Schemas\Components\Utilities\Get $get) => 'books/'.($get('../../slug') ?? 'book'))
                                ->image()
                                ->imageEditor()
                                ->fetchFileInformation(false)
                                ->required(),
                            Field\Hidden::make('sort_order'),
                        ])
                        ->grid(4)
                        ->reorderable(true)
                        ->itemLabel(fn (array $state): ?string => $state['image_url'] ?? 'Ảnh mới')
                        ->columnSpanFull()
                        ->helperText('Ảnh đầu tiên sẽ được dùng làm ảnh đại diện (thumbnail).'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Book Name')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_price')
                    ->label('Original Price')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('authors.name')
                    ->label('Authors')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categories')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status'),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->before(function (Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $hasOrders = \Illuminate\Support\Facades\DB::table('order_items')
                                ->whereIn('book_id', $records->pluck('id'))
                                ->exists();

                            if ($hasOrders) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Thao tác thất bại')
                                    ->body('Một hoặc nhiều sách đã chọn đang tồn tại trong đơn hàng, không thể xóa.')
                                    ->send();

                                $action->halt();
                            }
                        }),
                ])
            ]);
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
