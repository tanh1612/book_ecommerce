<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Enums\Order\OrderStatus;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderTimelinesRelationManager extends RelationManager
{
    protected static string $relationship = 'timelines';

    protected static ?string $title = 'Lịch sử trạng thái';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('status')
                    ->label('Trạng thái')
                    ->options(OrderStatus::class)
                    ->required(),
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'processing' => 'info',
                        'shipping' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'returned' => 'gray',
                        'refund_closed' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(function (string $state): string {
                        $enum = OrderStatus::tryFrom($state);

                        return $enum?->getLabel() ?? match ($state) {
                            'returned' => 'Trả hàng',
                            default => $state,
                        };
                    }),
                Tables\Columns\TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Thêm trạng thái'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
