<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Account;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('processOrder')
                ->label('Xử lý đơn')
                ->color('primary')
                ->icon('heroicon-o-cog-6-tooth')
                ->visible(fn (): bool => $this->orderIs(OrderStatus::CONFIRMED))
                ->requiresConfirmation()
                ->modalHeading('Xử lý đơn hàng')
                ->modalDescription('Chuyển đơn sang trạng thái Đang xử lý để chuẩn bị và xuất hóa đơn.')
                ->schema($this->transitionNoteSchema(OrderStatusTransitionService::TIMELINE_NOTE_PROCESS))
                ->action(fn (array $data) => $this->runTransition(
                    fn (OrderStatusTransitionService $service, Order $order, Account $actor) => $service->processOrder(
                        $order,
                        $actor,
                        $data['note'] ?? null,
                    ),
                    'Đã chuyển đơn sang Đang xử lý',
                )),
            Actions\Action::make('exportInvoice')
                ->label('Xuất hóa đơn PDF')
                ->color('gray')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => $this->orderIs(
                    OrderStatus::PROCESSING,
                    OrderStatus::SHIPPING,
                    OrderStatus::DELIVERED,
                ))
                ->url(fn (): string => route('admin.orders.invoice', ['order' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Actions\Action::make('shipOrder')
                ->label('Bắt đầu giao hàng')
                ->color('info')
                ->icon('heroicon-o-truck')
                ->visible(fn (): bool => $this->orderIs(OrderStatus::PROCESSING))
                ->requiresConfirmation()
                ->modalHeading('Bắt đầu giao hàng')
                ->modalDescription('Chuyển đơn sang trạng thái Đang giao hàng.')
                ->schema($this->transitionNoteSchema(OrderStatusTransitionService::TIMELINE_NOTE_SHIP))
                ->action(fn (array $data) => $this->runTransition(
                    fn (OrderStatusTransitionService $service, Order $order, Account $actor) => $service->shipOrder(
                        $order,
                        $actor,
                        $data['note'] ?? null,
                    ),
                    'Đã chuyển đơn sang Đang giao hàng',
                )),
            Actions\Action::make('confirmCodPayment')
                ->label('Xác nhận đã thu tiền')
                ->color('warning')
                ->icon('heroicon-o-banknotes')
                ->visible(fn (): bool => $this->orderIs(OrderStatus::SHIPPING)
                    && $this->orderHasPayment(PaymentMethod::COD, PaymentStatus::PENDING))
                ->requiresConfirmation()
                ->modalHeading('Xác nhận đã thu tiền COD')
                ->modalDescription('Ghi nhận khách đã thanh toán khi nhận hàng. Sau bước này mới có thể xác nhận đã giao.')
                ->schema(fn (): array => $this->transitionNoteSchema(
                    $this->getRecord() instanceof Order
                        ? OrderStatusTransitionService::timelineNoteCodPaid($this->getRecord())
                        : OrderStatusTransitionService::TIMELINE_NOTE_COD_PAID,
                ))
                ->action(fn (array $data) => $this->runTransition(
                    fn (OrderStatusTransitionService $service, Order $order, Account $actor) => $service->confirmCodPayment(
                        $order,
                        $actor,
                        $data['note'] ?? null,
                    ),
                    'Đã xác nhận thu tiền COD',
                )),
            Actions\Action::make('deliverOrder')
                ->label('Xác nhận đã giao')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (): bool => $this->orderIs(OrderStatus::SHIPPING)
                    && $this->orderHasPayment(null, PaymentStatus::PAID))
                ->requiresConfirmation()
                ->modalHeading('Xác nhận đã giao hàng')
                ->modalDescription('Hoàn tất giao hàng và chuyển đơn sang trạng thái Đã giao hàng.')
                ->schema($this->transitionNoteSchema(OrderStatusTransitionService::TIMELINE_NOTE_DELIVERED))
                ->action(fn (array $data) => $this->runTransition(
                    fn (OrderStatusTransitionService $service, Order $order, Account $actor) => $service->deliverOrder(
                        $order,
                        $actor,
                        $data['note'] ?? null,
                    ),
                    'Đã xác nhận giao hàng thành công',
                )),
            Actions\Action::make('cancelOrder')
                ->label('Hủy đơn')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => $this->orderIs(OrderStatus::CONFIRMED))
                ->requiresConfirmation()
                ->modalHeading('Hủy đơn hàng')
                ->modalDescription('Chỉ hủy được khi đơn đang ở trạng thái Đã xác nhận và chưa chuyển sang Đang xử lý.')
                ->schema($this->transitionNoteSchema(OrderStatusTransitionService::TIMELINE_NOTE_CANCEL))
                ->action(fn (array $data) => $this->runTransition(
                    fn (OrderStatusTransitionService $service, Order $order, Account $actor) => $service->cancelConfirmedOrder(
                        $order,
                        $actor,
                        $data['note'] ?? null,
                    ),
                    'Đã hủy đơn hàng',
                )),
        ];
    }

    /**
     * @return array<int, Textarea>
     */
    private function transitionNoteSchema(string $defaultNote): array
    {
        return [
            Textarea::make('note')
                ->label('Ghi chú')
                ->default($defaultNote)
                ->maxLength(500),
        ];
    }

    private function orderIs(OrderStatus ...$statuses): bool
    {
        $record = $this->getRecord();

        return $record instanceof Order
            && in_array($record->current_status, $statuses, true);
    }

    private function orderHasPayment(?PaymentMethod $method, PaymentStatus $paymentStatus): bool
    {
        $record = $this->getRecord();

        if (! $record instanceof Order) {
            return false;
        }

        if ($method !== null && $record->payment_method !== $method) {
            return false;
        }

        return $record->payment_status === $paymentStatus;
    }

    /**
     * @param  callable(OrderStatusTransitionService, Order, Account): Order  $callback
     */
    private function runTransition(callable $callback, string $successTitle): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Order) {
            return;
        }

        $actor = auth()->user();
        if (! $actor instanceof Account) {
            return;
        }

        try {
            $updated = $callback(app(OrderStatusTransitionService::class), $record, $actor);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Không thể thực hiện')
                ->body(collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($successTitle)
            ->success()
            ->send();

        $this->redirect(OrderResource::getUrl('view', ['record' => $updated]));
    }
}
