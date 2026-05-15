<?php

namespace App\Filament\Resources;

use App\Enums\Review\ReviewStatus;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static \UnitEnum|string|null $navigationGroup = 'Khách hàng';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Đánh giá';

    protected static ?string $modelLabel = 'Đánh giá';

    protected static ?string $pluralModelLabel = 'Đánh giá';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Chi tiết đánh giá')->components([
                Field\Select::make('account_id')->label('Khách hàng')->relationship('account', 'email')->disabled(),
                Field\Select::make('book_id')->label('Sách')->relationship('book', 'name')->disabled(),
                Field\TextInput::make('rating')->label('Điểm số')->disabled(),
                Field\Textarea::make('comment')->label('Nội dung')->disabled()->columnSpanFull(),
            ])->columns(4),
            Layout\Section::make('Quản lý')->components([
                Field\Select::make('status')->label('Trạng thái')->options(ReviewStatus::class)->required(),
                Field\Textarea::make('admin_reply')->label('Phản hồi từ quản trị')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Sách')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('account.email')
                    ->label('Khách hàng')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Điểm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (ReviewStatus $state): string => match ($state) {
                        ReviewStatus::PENDING => 'warning',
                        ReviewStatus::APPROVED => 'success',
                        ReviewStatus::REJECTED => 'danger',
                    })
                    ->icon(fn (ReviewStatus $state): string => match ($state) {
                        ReviewStatus::PENDING => 'heroicon-o-clock',
                        ReviewStatus::APPROVED => 'heroicon-o-check-circle',
                        ReviewStatus::REJECTED => 'heroicon-o-x-circle',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Trạng thái')->options(ReviewStatus::class),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
