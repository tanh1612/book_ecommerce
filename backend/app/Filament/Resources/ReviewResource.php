<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';
    protected static \UnitEnum|string|null $navigationGroup = 'Users';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Reviews';
    protected static ?string $modelLabel = 'Review';
    protected static ?string $pluralModelLabel = 'Reviews';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make('Review Details')->components([
                Field\Select::make('account_id')->label('Customer')->relationship('account', 'email')->disabled(),
                Field\Select::make('book_id')->label('Book')->relationship('book', 'name')->disabled(),
                Field\TextInput::make('rating')->label('Rating')->disabled(),
                Field\Textarea::make('comment')->label('Comment')->disabled()->columnSpanFull(),
            ])->columns(4),
            Layout\Section::make('Management')->components([
                Field\Select::make('status')->label('Status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])->required(),
                Field\Textarea::make('admin_reply')->label('Admin Reply')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')->label('Book')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('account.email')->label('Customer')->searchable()->limit(25),
                Tables\Columns\TextColumn::make('rating')->label('Rating')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
