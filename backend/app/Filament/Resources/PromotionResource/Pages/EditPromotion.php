<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\Promotion\FlashSaleCampaignValidator;
use App\Services\Promotion\FlashSaleScheduleMutex;
use App\Services\Promotion\PromotionLifecycleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected ?bool $hasDatabaseTransactions = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function authorizeAccess(): void
    {
        $this->record->refresh();

        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['status'], $data['type']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Promotion) {
            return parent::handleRecordUpdate($record, $data);
        }

        return app(FlashSaleScheduleMutex::class)->runExclusive(
            fn (): Model => app(PromotionLifecycleService::class)->updateScheduledPromotion(
                $record,
                function (Promotion $locked) use ($data): Model {
                    app(FlashSaleCampaignValidator::class)->assertEditFormData($locked, $data);

                    return parent::handleRecordUpdate($locked, $data);
                },
            ),
        );
    }

    protected function getHeaderActions(): array
    {
        $lifecycle = app(PromotionLifecycleService::class);

        return [
            Actions\Action::make('cancel')
                ->label('Hủy chiến dịch')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hủy chiến dịch')
                ->modalDescription('Chiến dịch sẽ chuyển sang trạng thái đã hủy. Sản phẩm trong chiến dịch được giữ nguyên.')
                ->visible(fn (): bool => $lifecycle->canCancel($this->getRecord()))
                ->action(function (Actions\Action $action) use ($lifecycle): void {
                    try {
                        $lifecycle->cancel($this->getRecord());
                        Notification::make()
                            ->title('Đã hủy chiến dịch')
                            ->success()
                            ->send();
                        $this->redirect(PromotionResource::getUrl('index'));
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Không thể hủy')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => PromotionResource::canDelete($this->getRecord()))
                ->action(function (Actions\DeleteAction $action) use ($lifecycle): void {
                    $record = $this->getRecord();

                    if (! $record instanceof Promotion) {
                        return;
                    }

                    try {
                        $lifecycle->deleteScheduledPromotion($record);
                        $this->redirect(PromotionResource::getUrl('index'));
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Không thể xóa')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
