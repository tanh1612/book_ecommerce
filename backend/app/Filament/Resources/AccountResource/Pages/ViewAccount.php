<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

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
        ];
    }
}
