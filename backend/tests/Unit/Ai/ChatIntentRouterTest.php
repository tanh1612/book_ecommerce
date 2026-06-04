<?php

use App\Enums\Ai\ChatIntent;
use App\Services\Ai\ChatIntentRouter;
use App\Services\Ai\Dto\ChatIntentRouteResult;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['ai.intent.classifier_enabled' => false]);
    $this->router = app(ChatIntentRouter::class);
});

function assertShortCircuitRoute(
    ChatIntentRouteResult $result,
    ChatIntent $intent,
    ?string $responseContains = null,
): void {
    expect($result->shouldShortCircuit)->toBeTrue()
        ->and($result->intent)->toBe($intent)
        ->and($result->confidence)->toBe(1.0)
        ->and($result->strategy)->toBe('rule');

    if ($responseContains !== null) {
        expect($result->response)->toBeString()
            ->and(str_contains($result->response, $responseContains))->toBeTrue();
    }
}

function assertPipelineRoute(ChatIntentRouteResult $result, ChatIntent $intent): void
{
    expect($result->shouldShortCircuit)->toBeFalse()
        ->and($result->intent)->toBe($intent)
        ->and($result->response)->toBeNull()
        ->and($result->strategy)->toBe('rule')
        ->and($result->confidence)->toBe(0.8);
}

test('small talk intents short-circuit with rule strategy', function (string $question, ChatIntent $intent, ?string $responseFragment): void {
    $result = $this->router->route($question);

    assertShortCircuitRoute($result, $intent, $responseFragment);
})->with([
    'alo' => ['alo', ChatIntent::SmallTalkGreeting, 'Chào bạn'],
    'chao ban' => ['chao ban', ChatIntent::SmallTalkGreeting, 'Chào bạn'],
    'status with greeting prefix' => ['alo, ban co nghe thay toi khong', ChatIntent::SmallTalkStatusCheck, 'Có, mình nghe thấy bạn'],
    'still there' => ['ban con do khong', ChatIntent::SmallTalkStatusCheck, 'Có, mình nghe thấy bạn'],
    'thanks' => ['cam on', ChatIntent::SmallTalkThanks, 'Rất vui được hỗ trợ bạn'],
    'goodbye' => ['tam biet', ChatIntent::SmallTalkGoodbye, 'Tạm biệt bạn'],
    'capability' => ['ban lam duoc gi', ChatIntent::SmallTalkCapability, 'Mình có thể hỗ trợ tìm sách'],
    'capability with book mention' => ['ban co the giup gi ve sach', ChatIntent::SmallTalkCapability, 'Mình có thể hỗ trợ tìm sách'],
    'capability ho tro ve sach' => ['ban ho tro gi ve sach', ChatIntent::SmallTalkCapability, 'Mình có thể hỗ trợ tìm sách'],
]);

test('book intents do not short-circuit', function (string $question, ChatIntent $intent): void {
    $result = $this->router->route($question);

    assertPipelineRoute($result, $intent);
})->with([
    'greeting with book search' => ['alo, tim sach ky nang giao tiep giup toi', ChatIntent::BookSearch],
    'status with recommendation' => ['ban con do khong, goi y sach tai chinh cho toi', ChatIntent::BookRecommendation],
    'book price detail' => ['Dac Nhan Tam gia bao nhieu tien', ChatIntent::BookDetail],
    'general book search' => ['ban co sach nao hay khong', ChatIntent::BookSearch],
]);

test('unsupported intents short-circuit', function (string $question, ChatIntent $intent): void {
    $result = $this->router->route($question);

    assertShortCircuitRoute($result, $intent);
})->with([
    'order' => ['Don hang cua toi dau roi', ChatIntent::UnsupportedOrder],
    'payment' => ['Thanh toan VNPAY bi loi', ChatIntent::UnsupportedPayment],
    'account' => ['Doi mat khau tai khoan the nao', ChatIntent::UnsupportedAccount],
    'non book product' => ['Bookify co ban dien thoai khong', ChatIntent::UnsupportedNonBookProduct],
    'non book with gia token' => ['Bookify co ban dien thoai gia re khong', ChatIntent::UnsupportedNonBookProduct],
    'tu van laptop' => ['tu van laptop', ChatIntent::UnsupportedNonBookProduct],
    'review dien thoai' => ['review dien thoai', ChatIntent::UnsupportedNonBookProduct],
    'goi y smartphone' => ['goi y smartphone', ChatIntent::UnsupportedNonBookProduct],
]);

test('book intent overrides non book product short-circuit', function (): void {
    $result = $this->router->route('Bookify co ban sach ve dien thoai khong');

    assertPipelineRoute($result, ChatIntent::BookSearch);
});

test('phrase boundary avoids false greeting and book detail matches', function (): void {
    $khiResult = $this->router->route('khi nao co sach moi');

    expect($khiResult->shouldShortCircuit)->toBeFalse()
        ->and($khiResult->intent)->toBe(ChatIntent::BookSearch);

    $giaReResult = $this->router->route('dien thoai gia re');

    expect($giaReResult->shouldShortCircuit)->toBeTrue()
        ->and($giaReResult->intent)->toBe(ChatIntent::UnsupportedNonBookProduct);

    $longWordResult = $this->router->route('tim sachhay');

    expect($longWordResult->intent)->toBe(ChatIntent::Unknown)
        ->and($longWordResult->shouldShortCircuit)->toBeFalse()
        ->and($longWordResult->strategy)->toBe('fallback');
});

test('empty question routes to unknown fallback', function (): void {
    $result = $this->router->route('   ');

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->shouldShortCircuit)->toBeFalse()
        ->and($result->confidence)->toBe(0.0)
        ->and($result->strategy)->toBe('fallback');
});
