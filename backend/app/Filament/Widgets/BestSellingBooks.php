<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryResource;
use App\Models\Inventory;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class BestSellingBooks extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Sách bán chạy nhất';

    public function table(Table $table): Table
    {
        return $table
            ->description('Top sách có số lượng đã bán cao nhất.')
            ->query(
                Inventory::query()
                    ->with(['book'])
                    ->where('sold_quantity', '>', 0)
                    ->orderByDesc('sold_quantity')
                    ->orderByRaw('(quantity - reserved_quantity) asc')
                    ->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Sách')
                    ->searchable()
                    ->limit(32)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('book.sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã bán')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Có thể bán')
                    ->numeric()
                    ->sortable(true, function (Builder $query, string $direction): Builder {
                        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                        return $query->orderByRaw('(quantity - reserved_quantity) '.$dir);
                    })
                    ->color(fn (Inventory $record): string => $record->available_stock <= 5 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Đang giữ')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Vị trí')
                    ->toggleable(),
            ])
            ->recordUrl(fn (Inventory $record): string => InventoryResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Actions\EditAction::make()
                    ->label('Kho')
                    ->url(fn (Inventory $record): string => InventoryResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Chưa có sách bán ra')
            ->emptyStateDescription('Sách sẽ xuất hiện khi đơn hàng hoàn tất làm tăng số lượng đã bán trong tồn kho.')
            ->emptyStateIcon('heroicon-o-book-open')
            ->paginated(false);
    }
}
