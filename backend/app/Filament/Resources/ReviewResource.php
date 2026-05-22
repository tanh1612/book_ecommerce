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
                Field\Select::make('status')->label('Trạng thái')->options(ReviewStatus::class)->disabled(),
                Field\TextInput::make('created_at')
                    ->label('Ngày tạo')
                    ->disabled()
                    ->formatStateUsing(fn (?Review $record): ?string => $record?->created_at?->format('d/m/Y H:i')),
            ])->columns(2),
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
                    ->icon(fn (ReviewStatus $state): string => $state->icon()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Trạng thái')->options(ReviewStatus::class),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('Xem'),
            ])
            ->recordActionsColumnLabel('Thao tác')
            ->recordUrl(fn (Review $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
        ];
    }
}
