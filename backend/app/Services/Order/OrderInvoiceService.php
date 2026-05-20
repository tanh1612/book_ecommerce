<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Support\VietnameseAmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderInvoiceService
{
    public function downloadPdf(Order $order): Response
    {
        try {
            $order->loadMissing(['items.book']);

            $pdf = Pdf::loadHTML($this->renderHtml($order))
                ->setPaper('a4', 'portrait')
                ->setOption('defaultFont', 'DejaVu Sans')
                ->setOption('isRemoteEnabled', false);

            $filename = sprintf('hoa-don-%d.pdf', $order->id);

            return $pdf->download($filename);
        } catch (Throwable $e) {
            Log::error('Order invoice PDF generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function renderHtml(Order $order): string
    {
        return view()->file(
            storage_path('app/templates/invoice.blade.php'),
            $this->buildViewData($order),
        )->render();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Order $order): array
    {
        $invoiceItems = [];
        foreach ($order->items as $item) {
            $invoiceItems[] = [
                'name' => $item->book?->name ?? 'Sản phẩm #'.$item->book_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->price,
                'total' => (float) $item->total_price,
            ];
        }

        if ((float) $order->shipping_fee > 0) {
            $invoiceItems[] = [
                'name' => 'Phí vận chuyển',
                'quantity' => 1,
                'unit_price' => (float) $order->shipping_fee,
                'total' => (float) $order->shipping_fee,
            ];
        }

        $grandTotal = (float) $order->final_amount;
        $createdAt = $order->created_at ?? now();

        return [
            'sellerName' => config('invoice.seller_name'),
            'sellerAddress' => config('invoice.seller_address'),
            'sellerPhone' => config('invoice.seller_phone'),
            'customerName' => $order->shipping_name,
            'customerAddress' => $order->shipping_address,
            'invoiceItems' => $invoiceItems,
            'totalQuantity' => collect($invoiceItems)->sum('quantity'),
            'grandTotal' => $grandTotal,
            'amountInWords' => VietnameseAmountInWords::convert($grandTotal),
            'invoiceDay' => $createdAt->format('d'),
            'invoiceMonth' => $createdAt->format('m'),
            'invoiceYear' => $createdAt->format('Y'),
        ];
    }
}
