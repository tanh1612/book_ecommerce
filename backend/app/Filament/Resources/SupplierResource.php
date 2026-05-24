<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static \UnitEnum|string|null $navigationGroup = 'Danh mục';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Nhà cung cấp';

    protected static ?string $modelLabel = 'Nhà cung cấp';

    protected static ?string $pluralModelLabel = 'Nhà cung cấp';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make()->components([
                Field\TextInput::make('name')
                    ->label('Tên nhà cung cấp')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Field\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Tên')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Ngày tạo')->dateTime()->sortable()->toggleable(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (Supplier $record, Actions\DeleteAction $action) {
                        if ($record->books()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Không thể xóa')
                                ->body("Nhà cung cấp \"{$record->name}\" đang có {$record->books()->count()} sách liên kết.")
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Actions\DeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if ($record->books()->exists()) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Không thể xóa')
                                        ->body('Có nhà cung cấp được chọn đang có sách liên kết. Hãy xử lý trước.')
                                        ->danger()
                                        ->send();
                                    $action->halt();
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
