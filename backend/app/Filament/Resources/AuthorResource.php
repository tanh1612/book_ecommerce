<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuthorResource\Pages;
use App\Filament\Resources\AuthorResource\RelationManagers\BooksRelationManager;
use App\Models\Author;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components as Field;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'Danh mục';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Tác giả';

    protected static ?string $modelLabel = 'Tác giả';

    protected static ?string $pluralModelLabel = 'Tác giả';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make()->components([
                Field\TextInput::make('name')
                    ->label('Author Name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Field\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Author $record, DeleteAction $action) {
                        if ($record->books()->exists()) {
                            Notification::make()
                                ->title('Không thể xóa')
                                ->body("Tác giả \"{$record->name}\" đang có {$record->books()->count()} sách liên kết.")
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if ($record->books()->exists()) {
                                    Notification::make()
                                        ->title('Không thể xóa')
                                        ->body('Có tác giả được chọn đang có sách liên kết. Hãy xử lý trước.')
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
            BooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuthors::route('/'),
            'create' => Pages\CreateAuthor::route('/create'),
            'edit' => Pages\EditAuthor::route('/{record}/edit'),
        ];
    }
}
