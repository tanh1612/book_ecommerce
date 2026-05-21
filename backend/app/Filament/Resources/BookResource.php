<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers;
use App\Models\Book;
use App\Services\Media\BookImageStorageService;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
        return $schema->columns(1)->components([
            Layout\Tabs::make('Tabs')->tabs([
                Layout\Tabs\Tab::make('Thông tin chung')->components([
                    Field\TextInput::make('name')
                        ->label('Tên sách')
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
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),

                    Layout\Grid::make(3)->components([
                        Field\TextInput::make('original_price')
                            ->label('Giá gốc')
                            ->default(0)
                            ->numeric()
                            ->required()
                            ->rules(['gt:0']),
                        Field\TextInput::make('selling_price')
                            ->label('Giá bán')
                            ->default(0)
                            ->numeric()
                            ->required()
                            ->rules(['gt:0']),
                        Field\Toggle::make('is_active')
                            ->label('Đang hoạt động')
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
                        ->label('Nhà cung cấp')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Field\Select::make('publisher_id')
                        ->relationship('publisher', 'name')
                        ->label('Nhà xuất bản')
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                    Field\Select::make('authors')
                        ->relationship('authors', 'name')
                        ->label('Tác giả')
                        ->multiple()
                        ->required()
                        ->preload()
                        ->columnSpanFull(),
                    Field\Select::make('categories')
                        ->relationship('categories', 'name', modifyQueryUsing: fn ($query) => $query->with('parent.parent'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getBreadcrumb())
                        ->label('Danh mục')
                        ->multiple()
                        ->required()
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
                        ->orderColumn('sort_order')
                        ->schema([
                            Field\FileUpload::make('public_id')
                                ->label('Ảnh')
                                ->disk('cloudinary')
                                ->directory(function (Get $get): string {
                                    $slug = (string) ($get('../../slug') ?? 'book');

                                    return app(BookImageStorageService::class)->bookImagesFolderForSlug($slug);
                                })
                                ->getUploadedFileNameForStorageUsing(
                                    function (TemporaryUploadedFile $file, Get $get): string {
                                        $slug = (string) ($get('../../slug') ?? 'book');

                                        return app(BookImageStorageService::class)
                                            ->newBookImageBasename($slug);
                                    },
                                )
                                ->image()
                                ->imageEditor()
                                ->fetchFileInformation(false),
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
                Tables\Columns\TextColumn::make('original_price')
                    ->label('Giá gốc')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
                Tables\Columns\TextColumn::make('authors.name')
                    ->label('Tác giả')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Danh mục')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Trạng thái'),
            ])
            ->actions([
                Actions\ViewAction::make()->label('Xem'),
                Actions\EditAction::make(),
            ])
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
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InventoriesRelationManager::class,
            RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'view' => Pages\ViewBook::route('/{record}'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
