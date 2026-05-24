<?php

namespace App\Notifications\Review;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewReviewPendingApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Review $review,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $review = $this->review->loadMissing(['account', 'book']);
        $comment = trim((string) $review->comment);

        $body = sprintf(
            "%s\nKhách hàng: %s\nĐiểm: %s/5%s",
            $review->book?->name ?? '---',
            $review->account?->email ?? '---',
            number_format((float) $review->rating, 1, ',', '.'),
            $comment !== '' ? "\nNội dung: ".Str::limit($comment, 120) : '',
        );

        return FilamentNotification::make()
            ->title('Có đánh giá mới cần duyệt')
            ->body($body)
            ->warning()
            ->actions([
                Action::make('view_review')
                    ->label('Xem đánh giá')
                    ->url(ReviewResource::getUrl('view', ['record' => $review]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
