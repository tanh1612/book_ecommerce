<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Services\Content\BannerCatalogService;
use App\Services\Media\BannerImageStorageService;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationLabel = 'Banner trang chủ';

    protected static ?string $modelLabel = 'Banner';

    protected static ?string $pluralModelLabel = 'Banner trang chủ';

    protected static \UnitEnum|string|null $navigationGroup = 'Nội dung';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin banner')
                ->components([
                    Field\TextInput::make('title')
                        ->label('Tiêu đề')
                        ->required()
                        ->maxLength(255),
                    Field\Toggle::make('is_active')
                        ->label('Đang hiển thị')
                        ->inline(false)
                        ->default(true),
                ])->columns(2),
            Layout\Section::make('Hình ảnh')
                ->components([
                    Field\FileUpload::make('public_id')
                        ->label('Ảnh banner')
                        ->required()
                        ->rules(fn (?Banner $record): array => [
                            'required',
                            Rule::unique('banners', 'public_id')->ignore($record),
                        ])
                        ->validationMessages([
                            'unique' => 'Ảnh banner này đã được gán cho banner khác.',
                        ])
                        ->disk('cloudinary')
                        ->directory(fn (): string => app(BannerImageStorageService::class)->homeBannersFolder())
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => app(BannerImageStorageService::class)->newBannerImageBasename(),
                        )
                        ->image()
                        ->imageEditor()
                        ->fetchFileInformation(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Ảnh')
                    ->height(46),
                Tables\Columns\TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hiển thị')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(
                fn (Actions\Action $action, bool $isReordering): Actions\Action => $action
                    ->label($isReordering ? 'Hoàn tất sắp xếp' : 'Sắp xếp')
            )
            ->afterReordering(function (): void {
                app(BannerCatalogService::class)->forgetHomeBannersCache();
            })
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Trạng thái'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Xóa banner')
                    ->modalDescription('Bạn có chắc muốn xóa banner này? Hành động không thể hoàn tác.'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->modalHeading('Xóa banner đã chọn')
                        ->modalDescription('Bạn có chắc muốn xóa các banner đã chọn? Hành động không thể hoàn tác.'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
