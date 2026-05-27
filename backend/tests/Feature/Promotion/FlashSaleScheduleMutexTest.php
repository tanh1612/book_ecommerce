<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Promotion;
use App\Services\Promotion\FlashSaleOverlapValidator;
use App\Services\Promotion\FlashSaleScheduleMutex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('flash sale schedule mutex blocks overlapping validation inside critical section', function (): void {
    Promotion::query()->create([
        'name' => 'Existing window',
        'type' => 'flash_sale',
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    app(FlashSaleScheduleMutex::class)->runExclusive(function (): void {
        expect(fn () => app(FlashSaleOverlapValidator::class)->assertNoOverlappingFlashSaleCampaign(
            now()->addDays(3),
            now()->addDays(6),
        ))->toThrow(ValidationException::class);
    });
});

test('flash sale schedule mutex uses mysql advisory lock when driver is mysql', function (): void {
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('MySQL GET_LOCK required.');
    }

    $mutex = app(FlashSaleScheduleMutex::class);

    $mutex->runExclusive(function () use ($mutex): void {
        $held = DB::selectOne('SELECT IS_USED_LOCK(?) AS held', ['bookify_flash_sale_schedule']);

        expect((int) ($held->held ?? 0))->toBeGreaterThan(0);

        $mutex->runExclusive(function (): void {
            expect(true)->toBeTrue();
        }, nested: true);
    });

    $released = DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', ['bookify_flash_sale_schedule']);

    expect((int) ($released->free ?? 0))->toBe(1);
});

test('concurrent connection waits for flash sale schedule lock on mysql', function (): void {
    if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('MySQL advisory lock with pcntl_fork required.');
    }

    $connection = config('database.connections.mysql');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        $connection['host'],
        $connection['port'] ?? 3306,
        $connection['database'],
    );

    $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $childPid = pcntl_fork();

    if ($childPid === -1) {
        test()->markTestSkipped('pcntl_fork unavailable.');
    }

    if ($childPid === 0) {
        fclose($pipe[0]);
        $pdo = new PDO($dsn, $connection['username'], $connection['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('SELECT GET_LOCK("bookify_flash_sale_schedule", 30)');
        usleep(400_000);
        $pdo->exec('SELECT RELEASE_LOCK("bookify_flash_sale_schedule")');
        fwrite($pipe[1], 'done');
        fclose($pipe[1]);
        exit(0);
    }

    fclose($pipe[1]);
    usleep(100_000);

    $startedAt = microtime(true);
    $acquired = (int) DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', ['bookify_flash_sale_schedule'])->acquired;
    $elapsedMs = (microtime(true) - $startedAt) * 1000;

    DB::selectOne('SELECT RELEASE_LOCK(?)', ['bookify_flash_sale_schedule']);

    pcntl_waitpid($childPid, $status);
    stream_set_blocking($pipe[0], true);
    $childSignal = stream_get_contents($pipe[0]);
    fclose($pipe[0]);

    expect($acquired)->toBe(1)
        ->and($elapsedMs)->toBeGreaterThan(150)
        ->and($childSignal)->toBe('done');
});
