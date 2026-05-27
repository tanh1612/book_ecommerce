<?php

namespace App\Filament\Resources\PromotionResource\RelationManagers;

use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Services\Promotion\FlashSaleCampaignValidator;
use App\Services\Promotion\FlashSaleScheduleMutex;
use App\Services\Promotion\PromotionLifecycleService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PromotionItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Sản phẩm trong chương trình';

    private function ownerPromotion(): ?Promotion
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Promotion ? $owner : null;
    }

    private function assertFlashSaleBookWindow(Promotion $promotion, int $bookId): void
    {
        app(FlashSaleCampaignValidator::class)->assertScheduledCampaignRules(
            $promotion->start_at,
            $promotion->end_at,
            (int) $promotion->id,
            [$bookId],
            nested: true,
        );
    }

    private function handleLifecycleException(callable $callback, Actions\Action $action): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Không thể thay đổi')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
            $action->halt();
        }
    }

    private function runScheduledItemMutation(callable $callback, Actions\Action $action): void
    {
        $owner = $this->ownerPromotion();

        if ($owner === null) {
            $action->halt();

            return;
        }

        $mutation = fn () => app(PromotionLifecycleService::class)->runWhileScheduled($owner, $callback);

        $this->handleLifecycleException(
            fn () => app(FlashSaleScheduleMutex::class)->runExclusive($mutation),
            $action,
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('book_id')
                    ->label('Sách')
                    ->relationship('book', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('discount_value')
                    ->label('Phần trăm giảm')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(),
                Forms\Components\TextInput::make('stock_limit')
                    ->label('Giới hạn suất bán')
                    ->integer()
                    ->minValue(1),
                Forms\Components\TextInput::make('max_quantity_per_user')
                    ->label('Tối đa / khách')
                    ->integer()
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Sách')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Giảm')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_limit')
                    ->label('Giới hạn')
                    ->placeholder('Không giới hạn'),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã giữ/bán')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_quantity_per_user')
                    ->label('Tối đa / khách')
                    ->placeholder('Không giới hạn'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->action(function (array $data, Actions\CreateAction $action): void {
                        $this->runScheduledItemMutation(
                            function (Promotion $locked) use ($data): void {
                                $this->assertFlashSaleBookWindow($locked, (int) $data['book_id']);
                                $locked->items()->create($data);
                            },
                            $action,
                        );
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->action(function (PromotionItem $record, array $data, Actions\EditAction $action): void {
                        $this->runScheduledItemMutation(
                            function (Promotion $locked) use ($record, $data): void {
                                if ((int) $record->promotion_id !== (int) $locked->id) {
                                    throw ValidationException::withMessages([
                                        'status' => ['Sản phẩm không thuộc chiến dịch này.'],
                                    ]);
                                }

                                $this->assertFlashSaleBookWindow($locked, (int) $data['book_id']);
                                $record->update($data);
                            },
                            $action,
                        );
                    }),
                Actions\DeleteAction::make()
                    ->visible(fn (PromotionItem $record): bool => ! app(PromotionLifecycleService::class)->hasPromotionItemBeenUsed($record))
                    ->action(function (PromotionItem $record, Actions\DeleteAction $action): void {
                        $owner = $this->ownerPromotion();

                        if ($owner === null) {
                            $action->halt();

                            return;
                        }

                        $this->handleLifecycleException(
                            fn () => app(PromotionLifecycleService::class)->deleteScheduledPromotionItem($owner, $record),
                            $action,
                        );
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, Actions\DeleteBulkAction $action): void {
                            $owner = $this->ownerPromotion();

                            if ($owner === null) {
                                $action->halt();

                                return;
                            }

                            $this->handleLifecycleException(
                                fn () => app(PromotionLifecycleService::class)->deleteScheduledPromotionItems($owner, $records),
                                $action,
                            );
                        }),
                ]),
            ]);
    }
}
