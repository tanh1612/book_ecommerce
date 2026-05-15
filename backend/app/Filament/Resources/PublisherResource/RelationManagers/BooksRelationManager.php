<?php

namespace App\Filament\Resources\PublisherResource\RelationManagers;

use App\Filament\Resources\BookResource;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
                    ->label('Ảnh bìa')
                    ->disk('cloudinary')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên sách')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Giá bán')
                    ->money('VND')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Actions\Action::make('edit_book')
                    ->label('Chỉnh sửa')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record): string => BookResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                //
            ]);
    }
}
