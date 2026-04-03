<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home-modern';
    protected static \UnitEnum|string|null $navigationGroup = 'Kho hàng';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Nhà kho';
    protected static ?string $modelLabel = 'Nhà kho';
    protected static ?string $pluralModelLabel = 'Nhà kho';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make()->components([
                Field\TextInput::make('name')
                    ->label('Warehouse Name')
                    ->required()
                    ->maxLength(255),
                Field\TextInput::make('code')
                    ->label('Warehouse Code')
                    ->required()
                    ->unique(Warehouse::class, 'code', ignoreRecord: true)
                    ->maxLength(255),
                Field\TextInput::make('manager_name')
                    ->label('Manager Name')
                    ->maxLength(255),
                Field\TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(255),
                Field\Textarea::make('address')
                    ->label('Address')
                    ->columnSpanFull(),
                Field\Toggle::make('is_active')
                    ->label('Active')
                    ->inline(false)
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('manager_name')->label('Manager')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status'),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
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
