<?php

use App\Models\Account;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Services\Promotion\PromotionAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->connectionsToTransact = [];
});

test('parallel promotion reserves cannot oversell single flash sale unit', function (): void {
    if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('MySQL and pcntl_fork are required for parallel reserve test.');
    }

    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $book = checkoutBookWithStock(10);

    $promotion = Promotion::query()->create([
        'name' => 'Parallel reserve',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 1,
    ]);

    $orderItemA = reserveTestOrderItem($accountA, $book, $promotion, $item);
    $orderItemB = reserveTestOrderItem($accountB, $book, $promotion, $item);

    $resultDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bookify_parallel_'.uniqid('', true);
    mkdir($resultDir);

    $workers = [
        ['suffix' => 'a', 'account_id' => $accountA->id, 'order_item_id' => $orderItemA->id],
        ['suffix' => 'b', 'account_id' => $accountB->id, 'order_item_id' => $orderItemB->id],
    ];

    $promotionItemId = $item->id;
    $pids = [];

    foreach ($workers as $worker) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            test()->markTestSkipped('pcntl_fork failed.');
        }

        if ($pid === 0) {
            DB::purge('mysql');
            DB::reconnect('mysql');

            $account = Account::query()->findOrFail($worker['account_id']);
            $orderItem = \App\Models\OrderItem::query()->findOrFail($worker['order_item_id']);
            $promotionItem = PromotionItem::query()->findOrFail($promotionItemId);

            try {
                app(PromotionAllocationService::class)->reserve($account, $promotionItem, $orderItem);
                file_put_contents($resultDir.'/ok_'.$worker['suffix'], '1');
            } catch (Throwable) {
                file_put_contents($resultDir.'/fail_'.$worker['suffix'], '1');
            }

            exit(0);
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $successCount = count(glob($resultDir.DIRECTORY_SEPARATOR.'ok_*') ?: []);
    $failCount = count(glob($resultDir.DIRECTORY_SEPARATOR.'fail_*') ?: []);

    array_map('unlink', glob($resultDir.DIRECTORY_SEPARATOR.'*') ?: []);
    rmdir($resultDir);

    expect($successCount)->toBe(1)
        ->and($failCount)->toBe(1)
        ->and((int) PromotionItem::query()->findOrFail($item->id)->sold_quantity)->toBe(1)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $item->id)->count())->toBe(1);
});
