<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Enums\Review\ReviewStatus;
use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReview extends ViewRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Phê duyệt')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (Review $record): bool => $record->status === ReviewStatus::PENDING)
                ->requiresConfirmation()
                ->modalHeading('Phê duyệt đánh giá')
                ->modalDescription('Đánh giá sẽ hiển thị công khai trên trang sách sau khi phê duyệt.')
                ->action(function (Review $record): void {
                    $record->update(['status' => ReviewStatus::APPROVED]);

                    Notification::make()
                        ->title('Đã phê duyệt đánh giá')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('reject')
                ->label('Từ chối')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (Review $record): bool => $record->status === ReviewStatus::PENDING)
                ->requiresConfirmation()
                ->modalHeading('Từ chối đánh giá')
                ->modalDescription('Đánh giá sẽ không được hiển thị công khai.')
                ->action(function (Review $record): void {
                    $record->update(['status' => ReviewStatus::REJECTED]);

                    Notification::make()
                        ->title('Đã từ chối đánh giá')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make()->label('Xóa')
        ];
    }
}
