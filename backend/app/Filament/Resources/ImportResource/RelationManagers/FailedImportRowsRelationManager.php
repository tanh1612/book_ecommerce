<?php

namespace App\Filament\Resources\ImportResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FailedImportRowsRelationManager extends RelationManager
{
    protected static string $relationship = 'failedRows';

    protected static ?string $title = 'Dòng lỗi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('validation_error')
                    ->label('Lỗi')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('data')
                    ->label('Dữ liệu dòng')
                    ->formatStateUsing(function (?array $state): string {
                        if ($state === null || $state === []) {
                            return '';
                        }

                        return Str::limit(json_encode($state, JSON_UNESCAPED_UNICODE), 400);
                    })
                    ->tooltip(function ($record): ?string {
                        if (! $record || ! is_array($record->data)) {
                            return null;
                        }

                        return json_encode($record->data, JSON_UNESCAPED_UNICODE);
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }
}
