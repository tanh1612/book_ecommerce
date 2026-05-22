<?php

namespace App\Filament\Resources\BookResource\RelationManagers;

use App\Enums\Review\ReviewStatus;
use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Đánh giá';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account.email')
                    ->label('Khách hàng')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Điểm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(ReviewStatus::class),
            ])
            ->headerActions([])
            ->actions([
                Actions\Action::make('view')
                    ->label('Xem')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Review $record): string => ReviewResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
