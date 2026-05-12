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

    protected static \UnitEnum|string|null $navigationGroup = 'Người dùng';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Đánh giá';

    protected static ?string $modelLabel = 'Đánh giá';

    protected static ?string $pluralModelLabel = 'Đánh giá';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make('Chi tiết đánh giá')->components([
                Field\Select::make('account_id')->label('Customer')->relationship('account', 'email')->disabled(),
                Field\Select::make('book_id')->label('Book')->relationship('book', 'name')->disabled(),
                Field\TextInput::make('rating')->label('Rating')->disabled(),
                Field\Textarea::make('comment')->label('Comment')->disabled()->columnSpanFull(),
            ])->columns(4),
            Layout\Section::make('Quản lý')->components([
                Field\Select::make('status')->label('Status')->options(ReviewStatus::class)->required(),
                Field\Textarea::make('admin_reply')->label('Admin Reply')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Book')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('account.email')
                    ->label('Customer')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
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
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(ReviewStatus::class),
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
