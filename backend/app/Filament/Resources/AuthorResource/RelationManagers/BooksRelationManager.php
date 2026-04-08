<?php

namespace App\Filament\Resources\AuthorResource\RelationManagers;

use App\Filament\Resources\BookResource;
use Filament\Forms\Components as Field;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Danh sách sách';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Field\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Thumbnail')
                    ->disk('cloudinary')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Book Name')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Actions\AttachAction::make()->preloadRecordSelect(),
            ])
            ->actions([
                Actions\Action::make('edit_book')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record): string => BookResource::getUrl('edit', ['record' => $record])),
                Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
