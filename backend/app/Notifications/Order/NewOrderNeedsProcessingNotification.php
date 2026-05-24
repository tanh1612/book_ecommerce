<?php

namespace App\Notifications\Order;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderNeedsProcessingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
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
        $order = $this->order->loadMissing(['account']);

        $body = sprintf(
            "#%d\nKhách hàng: %s\nTổng thu: %s\nTrạng thái: %s",
            $order->id,
            $order->account?->email ?? '---',
            number_format((float) $order->final_amount, 0, ',', '.').' đ',
            $order->current_status?->getLabel() ?? '---',
        );

        return FilamentNotification::make()
            ->title('Có đơn hàng mới cần xử lý')
            ->body($body)
            ->info()
            ->actions([
                Action::make('view_order')
                    ->label('Xem đơn hàng')
                    ->url(OrderResource::getUrl('view', ['record' => $order]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
