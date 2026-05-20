<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Order;
use App\Services\Order\OrderInvoiceService;
use Filament\Facades\Filament;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DownloadOrderInvoiceController extends Controller
{
    public function __invoke(Order $order, OrderInvoiceService $invoiceService): Response
    {
        $account = auth()->user();
        if (! $account instanceof Account) {
            abort(HttpResponse::HTTP_UNAUTHORIZED);
        }

        $panel = Filament::getPanel('admin');
        if (! $account->canAccessPanel($panel)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        if (! in_array($order->current_status, [
            OrderStatus::PROCESSING,
            OrderStatus::SHIPPING,
            OrderStatus::DELIVERED,
        ], true)) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Đơn chưa ở giai đoạn cho phép xuất hóa đơn.');
        }

        return $invoiceService->downloadPdf($order);
    }
}
