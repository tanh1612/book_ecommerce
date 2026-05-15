<?php

namespace App\Filament\Resources\BookResource\RelationManagers;

use App\Enums\Review\ReviewStatus;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Đánh giá';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('rating')
                    ->label('Điểm số')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->required(),
                Forms\Components\Textarea::make('comment')
                    ->label('Nội dung')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(ReviewStatus::class)
                    ->required(),
                Forms\Components\Textarea::make('admin_reply')
                    ->label('Phản hồi từ quản trị')
                    ->columnSpanFull(),
            ]);
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
                    ->badge()
                    ->color(fn (ReviewStatus $state): string => match ($state) {
                        ReviewStatus::APPROVED => 'success',
                        ReviewStatus::REJECTED => 'danger',
                        default => 'warning',
                    }),
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
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
