<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use App\Services\Account\AccountDeletionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('lock')
                ->label('Khóa tài khoản')
                ->color('danger')
                ->icon('heroicon-o-lock-closed')
                ->visible(fn (Account $record): bool => $record->is_active
                    && (int) $record->getKey() !== (int) Auth::id())
                ->requiresConfirmation()
                ->modalHeading('Khóa tài khoản')
                ->modalDescription('Tài khoản sẽ không thể đăng nhập cho đến khi được kích hoạt lại.')
                ->action(function (Account $record): void {
                    $record->update(['is_active' => false]);

                    Notification::make()
                        ->title('Đã khóa tài khoản')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('activate')
                ->label('Kích hoạt')
                ->color('success')
                ->icon('heroicon-o-lock-open')
                ->visible(fn (Account $record): bool => ! $record->is_active)
                ->requiresConfirmation()
                ->modalHeading('Kích hoạt tài khoản')
                ->modalDescription('Tài khoản sẽ có thể đăng nhập trở lại.')
                ->action(function (Account $record): void {
                    $record->update(['is_active' => true]);

                    Notification::make()
                        ->title('Đã kích hoạt tài khoản')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make()
                ->label('Xóa')
                ->visible(fn (Account $record): bool => ! $record->is_active
                    && (int) $record->getKey() !== (int) Auth::id())
                ->disabled(fn (Account $record): bool => $record->hasUnfinishedOrders())
                ->tooltip(fn (Account $record): ?string => $record->hasUnfinishedOrders()
                    ? 'Không thể xóa khi còn đơn chưa hoàn thành.'
                    : null)
                ->modalHeading('Xóa tài khoản')
                ->modalDescription('Tài khoản sẽ bị xóa và không thể khôi phục lại. Bạn có chắc muốn thực hiện thao tác?')
                ->action(function (Account $record): void {
                    $actor = Auth::user();

                    if (! $actor instanceof Account) {
                        return;
                    }

                    try {
                        app(AccountDeletionService::class)->softDeleteInactive($record, $actor);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Không thể xóa tài khoản')
                            ->body(collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Đã xóa tài khoản')
                        ->success()
                        ->send();

                    $this->redirect(AccountResource::getUrl('index'));
                }),
        ];
    }
}
