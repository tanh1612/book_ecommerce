<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Support\InventoryFilamentRules;
use App\Models\Inventory;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static \UnitEnum|string|null $navigationGroup = 'Kho hàng';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tồn kho';

    protected static ?string $modelLabel = 'Tồn kho';

    protected static ?string $pluralModelLabel = 'Tồn kho';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['book', 'warehouse']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin tồn kho')
                ->columns(1)
                ->components([
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\Select::make('book_id')
                                ->label('Sách')
                                ->relationship('book', 'name', fn (Builder $query) => $query->where('is_active', true))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->rule(fn (Get $get, ?Inventory $record) => InventoryFilamentRules::uniqueBookIdForInventory($record))
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\Select::make('warehouse_id')
                                ->label('Kho')
                                ->relationship('warehouse', 'name', fn (Builder $query) => $query->where('is_active', true))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
                    Layout\Grid::make(3)
                        ->columnSpanFull()
                        ->components([
                            Field\TextInput::make('quantity')
                                ->label('Số lượng tồn')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                                ->rules(['integer', 'min:0'])
                                ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state))),
                            Field\TextInput::make('sold_quantity')
                                ->label('Đã bán')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                                ->rules(['integer', 'min:0'])
                                ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state))),
                            Field\TextInput::make('reserved_quantity')
                                ->label('Đang giữ')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                                ->rules(['integer', 'min:0'])
                                ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state)))
                                ->rule(InventoryFilamentRules::reservedQuantityLteOnHand()),
                        ]),
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\TextInput::make('location_code')
                                ->label('Mã vị trí')
                                ->maxLength(50)
                                ->mutateStateForValidationUsing(fn (mixed $state): string => $state === null ? '' : trim((string) $state))
                                ->rules(['string', 'max:50'])
                                ->dehydrateStateUsing(fn (mixed $state): string => $state === null ? '' : trim((string) $state))
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\DateTimePicker::make('last_restocked_at')
                                ->label('Nhập kho gần nhất')
                                ->seconds(false)
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
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
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Kho')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Số lượng tồn')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Đang giữ')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Có thể bán')
                    ->sortable(true, function (Builder $query, string $direction): Builder {
                        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                        return $query->orderByRaw('(quantity - reserved_quantity) '.$dir);
                    })
                    ->color(fn (Inventory $record): string => match (true) {
                        $record->available_stock <= 0 => 'danger',
                        $record->available_stock <= 5 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã bán')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Mã vị trí')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_restocked_at')
                    ->label('Nhập kho gần nhất')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Kho')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('book_id')
                    ->label('Sách')
                    ->relationship('book', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('stock_status')
                    ->label('Trạng thái tồn')
                    ->options([
                        'in_stock' => 'Còn hàng',
                        'out_of_stock' => 'Hết hàng',
                        'low' => 'Sắp hết (1–5)',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        match ($data['value'] ?? null) {
                            'out_of_stock' => $query->whereRaw('(quantity - reserved_quantity) <= 0'),
                            'in_stock' => $query->whereRaw('(quantity - reserved_quantity) > 0'),
                            'low' => $query->whereRaw('(quantity - reserved_quantity) > 0')
                                ->whereRaw('(quantity - reserved_quantity) <= 5'),
                            default => null,
                        };
                    }),
            ])
            ->emptyStateHeading('Không có tồn kho nào')
            ->emptyStateDescription(null)
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
