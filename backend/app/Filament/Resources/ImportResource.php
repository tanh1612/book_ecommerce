<?php

namespace App\Filament\Resources;

use App\Enums\Account\AccountRole;
use App\Filament\Resources\ImportResource\Pages;
use App\Filament\Resources\ImportResource\RelationManagers;
use App\Models\Account;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static \UnitEnum|string|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Lịch sử nhập CSV';

    protected static ?string $modelLabel = 'Phiên nhập CSV';

    protected static ?string $pluralModelLabel = 'Lịch sử nhập CSV';

    protected static ?string $recordTitleAttribute = 'file_name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof Account && $user->role === AccountRole::Admin;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin phiên nhập')->components([
                TextEntry::make('file_name')->label('Tên tệp'),
                TextEntry::make('file_path')->label('Đường dẫn lưu')->columnSpanFull(),
                TextEntry::make('importer')
                    ->label('Importer')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : ''),
                TextEntry::make('processed_rows')->label('Đã xử lý'),
                TextEntry::make('total_rows')->label('Tổng dòng'),
                TextEntry::make('successful_rows')->label('Dòng thành công'),
                TextEntry::make('completed_at')->label('Hoàn tất lúc')->dateTime('d/m/Y H:i'),
                TextEntry::make('user.email')->label('Người thực hiện'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('file_name')->label('Tệp')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('importer')
                    ->label('Importer')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('successful_rows')->label('Thành công')->sortable(),
                Tables\Columns\TextColumn::make('total_rows')->label('Tổng dòng')->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('Người thực hiện')->searchable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Hoàn tất')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Bắt đầu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FailedImportRowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImports::route('/'),
            'view' => Pages\ViewImport::route('/{record}'),
        ];
    }
}
