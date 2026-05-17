<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Resources\WarehouseResource\RelationManagers;
use App\Models\Warehouse;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home-modern';

    protected static \UnitEnum|string|null $navigationGroup = 'Kho hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Kho';

    protected static ?string $modelLabel = 'Kho';

    protected static ?string $pluralModelLabel = 'Kho';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin kho')
                ->columns(1)
                ->components([
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\TextInput::make('name')
                                ->label('Tên kho')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(['default' => 'full', 'lg' => 8]),
                            Field\Toggle::make('is_active')
                                ->label('Đang hoạt động')
                                ->inline(false)
                                ->default(true)
                                ->columnSpan(['default' => 'full', 'lg' => 4]),
                        ]),
                    Field\Textarea::make('address')
                        ->label('Địa chỉ')
                        ->required()
                        ->maxLength(500)
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên kho')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Địa chỉ')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Trạng thái'),
            ])
            ->actions([
                Actions\EditAction::make(),
                static::configureWarehouseDeleteAction(Actions\DeleteAction::make()),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    static::configureWarehouseDeleteBulkAction(Actions\DeleteBulkAction::make()),
                ]),
            ]);
    }

    public static function configureWarehouseDeleteAction(Actions\DeleteAction $action): Actions\DeleteAction
    {
        return $action
            ->before(function (Warehouse $record): void {
                if ($record->inventories()->exists()) {
                    Notification::make()
                        ->danger()
                        ->title('Không thể xóa kho')
                        ->body('Kho vẫn còn dòng tồn kho. Hãy xóa hoặc chuyển hết sản phẩm trong mục tồn kho của kho này trước.')
                        ->send();

                    throw (new Halt)->rollBackDatabaseTransaction(false);
                }
            })
            ->failureNotification(null)
            ->using(function (Warehouse $record): bool {
                try {
                    return (bool) $record->delete();
                } catch (QueryException $e) {
                    Log::error('Warehouse delete failed', [
                        'warehouse_id' => $record->getKey(),
                        'error' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->danger()
                        ->title('Không thể xóa kho')
                        ->body('Không xóa được do ràng buộc dữ liệu. Kiểm tra các bản ghi liên quan hoặc thử lại sau.')
                        ->send();

                    return false;
                }
            });
    }

    public static function configureWarehouseDeleteBulkAction(Actions\DeleteBulkAction $action): Actions\DeleteBulkAction
    {
        return $action
            ->before(function (Collection $records): void {
                $blocked = $records->filter(fn (mixed $record): bool => $record instanceof Warehouse && $record->inventories()->exists());

                if ($blocked->isNotEmpty()) {
                    Notification::make()
                        ->danger()
                        ->title('Không thể xóa kho')
                        ->body(
                            $blocked->count() === $records->count()
                                ? 'Các kho đã chọn vẫn còn tồn kho. Hãy xóa hoặc chuyển hết tồn trước.'
                                : sprintf('Có %d kho trong số đã chọn vẫn còn tồn kho.', $blocked->count())
                        )
                        ->send();

                    throw (new Halt)->rollBackDatabaseTransaction(false);
                }
            });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InventoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
