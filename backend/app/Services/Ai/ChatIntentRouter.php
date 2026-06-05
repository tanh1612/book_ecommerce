<?php

namespace App\Services\Ai;

use App\Enums\Ai\ChatIntent;
use App\Services\Ai\Contracts\ChatIntentClassifier;
use App\Services\Ai\Dto\ChatIntentClassificationResult;
use App\Services\Ai\Dto\ChatIntentRouteResult;
use App\Services\Ai\Support\IntentTextNormalizer;
use Illuminate\Support\Facades\Log;

class ChatIntentRouter
{
    public function __construct(
        private readonly ChatIntentClassifier $chatIntentClassifier,
    ) {}

    /**
     * @return list<string>
     */
    private const BOOK_INTENT_PHRASES = [
        'sach',
        'cuon',
        'quyen',
        'doc',
        'tac gia',
        'the loai',
        'nha xuat ban',
        'gia sach',
        'gia ban',
        'gia bao nhieu',
        'bao nhieu tien',
        'con hang',
        'ton kho',
        'review',
        'danh gia',
        'goi y',
        'tu van',
    ];

    /**
     * @return list<string>
     */
    private const BOOK_DETAIL_PHRASES = [
        'gia sach',
        'gia ban',
        'gia bao nhieu',
        'bao nhieu tien',
        'con hang',
        'ton kho',
        'review',
        'danh gia',
        'tac gia',
        'nha xuat ban',
    ];

    /**
     * @return list<string>
     */
    private const BOOK_RECOMMENDATION_PHRASES = [
        'goi y',
        'tu van',
        'nen doc',
        'phu hop',
        'tim sach ve',
    ];

    /**
     * @return list<string>
     */
    private const BOOK_SEARCH_PHRASES = [
        'sach',
        'cuon',
        'quyen',
        'the loai',
        'doc',
    ];

    /**
     * Only these signals may override unsupported non_book_product routing.
     *
     * @return list<string>
     */
    private const STRONG_BOOK_PRODUCT_OVERRIDE_PHRASES = [
        'sach',
        'cuon',
        'quyen',
        'sach ve',
        'tac gia',
        'the loai',
        'nha xuat ban',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const SMALL_TALK_PHRASES = [
        'small_talk.status_check' => [
            'ban co nghe thay toi khong',
            'co nghe thay toi khong',
            'ban con do khong',
            'bot con hoat dong khong',
            'ban co online khong',
        ],
        'small_talk.capability' => [
            'ban lam duoc gi',
            'ban ho tro gi',
            'chatbot nay lam duoc gi',
            'ban co the giup gi',
        ],
        'small_talk.thanks' => [
            'cam on',
            'thanks',
            'thank you',
        ],
        'small_talk.goodbye' => [
            'tam biet',
            'bye',
            'hen gap lai',
        ],
        'small_talk.greeting' => [
            'chao ban',
            'hello',
            'hi',
            'alo',
            'chao',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const CATEGORY_PHRASES = [
        'order' => [
            'don hang',
            'ma don',
            'trang thai don',
            'huy don',
        ],
        'payment' => [
            'thanh toan',
            'vnpay',
            'giao dich',
        ],
        'refund' => [
            'hoan tien',
            'doi tra',
            'refund',
        ],
        'private_account' => [
            'tai khoan',
            'mat khau',
            'dia chi',
            'thong tin ca nhan',
            'profile',
        ],
        'non_book_product' => [
            'dien thoai',
            'smartphone',
            'laptop',
            'may tinh',
            'quan ao',
            'my pham',
        ],
    ];

    public function route(string $question): ChatIntentRouteResult
    {
        $normalized = $this->normalizeIntentText($question);

        if ($normalized === '') {
            return $this->fallbackUnknown();
        }

        $ruleResult = $this->routeByRules($normalized);

        if ($ruleResult->shouldShortCircuit || $ruleResult->intent !== ChatIntent::Unknown) {
            return $ruleResult;
        }

        return $this->applyClassifier($question);
    }

    private function routeByRules(string $normalized): ChatIntentRouteResult
    {
        $smallTalkCategory = $this->matchSmallTalk($normalized);

        if ($smallTalkCategory !== null && $this->shouldShortCircuitSmallTalk($normalized, $smallTalkCategory)) {
            return new ChatIntentRouteResult(
                intent: $this->smallTalkIntentFromCategory($smallTalkCategory),
                shouldShortCircuit: true,
                response: $this->responseForSmallTalkCategory($smallTalkCategory),
                confidence: 1.0,
                strategy: 'rule',
            );
        }

        $matchedCategory = $this->matchCategory($normalized);

        if ($matchedCategory !== null) {
            if ($matchedCategory === 'non_book_product' && $this->hasStrongBookProductOverride($normalized)) {
                return $this->routeBookIntent($normalized);
            }

            return new ChatIntentRouteResult(
                intent: $this->unsupportedIntentFromCategory($matchedCategory),
                shouldShortCircuit: true,
                response: $this->responseForUnsupportedCategory($matchedCategory),
                confidence: 1.0,
                strategy: 'rule',
            );
        }

        if ($this->hasBookIntent($normalized)) {
            return $this->routeBookIntent($normalized);
        }

        return $this->fallbackUnknown();
    }

    private function applyClassifier(string $question): ChatIntentRouteResult
    {
        if (! (bool) config('ai.intent.classifier_enabled', false)) {
            return $this->fallbackUnknown();
        }

        $maxLength = max((int) config('ai.intent.classifier_max_question_length', 240), 1);

        if (mb_strlen($question) > $maxLength) {
            return $this->fallbackUnknown();
        }

        try {
            $classification = $this->chatIntentClassifier->classify($question);
        } catch (\Throwable $e) {
            Log::warning('Chat intent classifier invocation failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->fallbackUnknown();
        }

        return $this->routeFromClassification($classification);
    }

    private function routeFromClassification(ChatIntentClassificationResult $classification): ChatIntentRouteResult
    {
        $threshold = (float) config('ai.intent.classifier_confidence_threshold', 0.80);

        if ($classification->confidence < $threshold || $classification->intent === ChatIntent::Unknown) {
            return $this->fallbackUnknown();
        }

        if ($this->shouldShortCircuitIntent($classification->intent)) {
            $response = $this->responseForIntent($classification->intent);

            if ($response === null) {
                return $this->fallbackUnknown();
            }

            return new ChatIntentRouteResult(
                intent: $classification->intent,
                shouldShortCircuit: true,
                response: $response,
                confidence: $classification->confidence,
                strategy: $classification->strategy,
            );
        }

        return new ChatIntentRouteResult(
            intent: $classification->intent,
            shouldShortCircuit: false,
            response: null,
            confidence: $classification->confidence,
            strategy: $classification->strategy,
        );
    }

    private function shouldShortCircuitIntent(ChatIntent $intent): bool
    {
        return str_starts_with($intent->value, 'small_talk.')
            || str_starts_with($intent->value, 'unsupported.');
    }

    private function responseForIntent(ChatIntent $intent): ?string
    {
        return match ($intent) {
            ChatIntent::SmallTalkGreeting => $this->responseForSmallTalkCategory('small_talk.greeting'),
            ChatIntent::SmallTalkStatusCheck => $this->responseForSmallTalkCategory('small_talk.status_check'),
            ChatIntent::SmallTalkThanks => $this->responseForSmallTalkCategory('small_talk.thanks'),
            ChatIntent::SmallTalkGoodbye => $this->responseForSmallTalkCategory('small_talk.goodbye'),
            ChatIntent::SmallTalkCapability => $this->responseForSmallTalkCategory('small_talk.capability'),
            ChatIntent::UnsupportedOrder => $this->responseForUnsupportedCategory('order'),
            ChatIntent::UnsupportedPayment => $this->responseForUnsupportedCategory('payment'),
            ChatIntent::UnsupportedRefund => $this->responseForUnsupportedCategory('refund'),
            ChatIntent::UnsupportedAccount => $this->responseForUnsupportedCategory('private_account'),
            ChatIntent::UnsupportedNonBookProduct => $this->responseForUnsupportedCategory('non_book_product'),
            default => null,
        };
    }

    private function routeBookIntent(string $normalized): ChatIntentRouteResult
    {
        return new ChatIntentRouteResult(
            intent: $this->classifyBookIntent($normalized),
            shouldShortCircuit: false,
            response: null,
            confidence: 0.8,
            strategy: 'rule',
        );
    }

    private function fallbackUnknown(): ChatIntentRouteResult
    {
        return new ChatIntentRouteResult(
            intent: ChatIntent::Unknown,
            shouldShortCircuit: false,
            response: null,
            confidence: 0.0,
            strategy: 'fallback',
        );
    }

    private function classifyBookIntent(string $normalized): ChatIntent
    {
        foreach (self::BOOK_DETAIL_PHRASES as $phrase) {
            if ($this->containsPhrase($normalized, $phrase)) {
                return ChatIntent::BookDetail;
            }
        }

        foreach (self::BOOK_RECOMMENDATION_PHRASES as $phrase) {
            if ($this->containsPhrase($normalized, $phrase)) {
                return ChatIntent::BookRecommendation;
            }
        }

        foreach (self::BOOK_SEARCH_PHRASES as $phrase) {
            if ($this->containsPhrase($normalized, $phrase)) {
                return ChatIntent::BookSearch;
            }
        }

        return ChatIntent::BookSearch;
    }

    private function smallTalkIntentFromCategory(string $category): ChatIntent
    {
        return match ($category) {
            'small_talk.greeting' => ChatIntent::SmallTalkGreeting,
            'small_talk.status_check' => ChatIntent::SmallTalkStatusCheck,
            'small_talk.thanks' => ChatIntent::SmallTalkThanks,
            'small_talk.goodbye' => ChatIntent::SmallTalkGoodbye,
            'small_talk.capability' => ChatIntent::SmallTalkCapability,
            default => ChatIntent::Unknown,
        };
    }

    private function unsupportedIntentFromCategory(string $category): ChatIntent
    {
        return match ($category) {
            'order' => ChatIntent::UnsupportedOrder,
            'payment' => ChatIntent::UnsupportedPayment,
            'refund' => ChatIntent::UnsupportedRefund,
            'private_account' => ChatIntent::UnsupportedAccount,
            'non_book_product' => ChatIntent::UnsupportedNonBookProduct,
            default => ChatIntent::Unknown,
        };
    }

    private function normalizeIntentText(string $text): string
    {
        return IntentTextNormalizer::normalize($text);
    }

    private function matchSmallTalk(string $normalizedQuestion): ?string
    {
        foreach (self::SMALL_TALK_PHRASES as $category => $phrases) {
            $sortedPhrases = $phrases;
            usort($sortedPhrases, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

            foreach ($sortedPhrases as $phrase) {
                if ($this->containsPhrase($normalizedQuestion, $phrase)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function shouldShortCircuitSmallTalk(string $normalizedQuestion, string $category): bool
    {
        if ($category === 'small_talk.capability') {
            return true;
        }

        if ($this->hasBookIntent($normalizedQuestion)) {
            return false;
        }

        if ($category === 'small_talk.status_check') {
            return $this->isStatusCheckOnlyUtterance($normalizedQuestion);
        }

        if ($category === 'small_talk.thanks') {
            foreach (['goi y', 'tim sach', 'sach', 'cuon'] as $phrase) {
                if ($this->containsPhrase($normalizedQuestion, $phrase)) {
                    return false;
                }
            }
        }

        if ($category === 'small_talk.greeting') {
            return $this->isShortSmallTalkUtterance($normalizedQuestion);
        }

        return true;
    }

    private function isStatusCheckOnlyUtterance(string $normalizedQuestion): bool
    {
        if ($this->isShortSmallTalkUtterance($normalizedQuestion)) {
            return true;
        }

        foreach (self::SMALL_TALK_PHRASES['small_talk.status_check'] as $phrase) {
            if (! $this->containsPhrase($normalizedQuestion, $phrase)) {
                continue;
            }

            if ($normalizedQuestion === $phrase) {
                return true;
            }

            if ($this->remainderIsOnlyGreetingPrefix($normalizedQuestion, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function remainderIsOnlyGreetingPrefix(string $normalizedQuestion, string $statusPhrase): bool
    {
        $remainder = trim(preg_replace(
            '/(?<!\p{L})'.preg_quote($statusPhrase, '/').'(?!\p{L})/u',
            ' ',
            $normalizedQuestion,
            1,
        ) ?? $normalizedQuestion);
        $remainder = trim(preg_replace('/\s+/u', ' ', $remainder) ?? '');

        if ($remainder === '') {
            return true;
        }

        /** @var list<string> $allowedRemainders */
        $allowedRemainders = [
            'alo',
            'chao',
            'chao ban',
            'hello',
            'hi',
        ];

        return in_array($remainder, $allowedRemainders, true);
    }

    private function isShortSmallTalkUtterance(string $normalizedQuestion): bool
    {
        return count(explode(' ', $normalizedQuestion)) <= 4;
    }

    private function matchCategory(string $normalizedQuestion): ?string
    {
        foreach (self::CATEGORY_PHRASES as $category => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->containsPhrase($normalizedQuestion, $phrase)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function hasBookIntent(string $normalizedQuestion): bool
    {
        foreach (self::BOOK_INTENT_PHRASES as $phrase) {
            if ($this->containsPhrase($normalizedQuestion, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function hasStrongBookProductOverride(string $normalizedQuestion): bool
    {
        foreach (self::STRONG_BOOK_PRODUCT_OVERRIDE_PHRASES as $phrase) {
            if ($this->containsPhrase($normalizedQuestion, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function containsPhrase(string $normalized, string $phrase): bool
    {
        if ($normalized === $phrase) {
            return true;
        }

        if (! str_contains($phrase, ' ')) {
            return in_array($phrase, explode(' ', $normalized), true);
        }

        $pattern = '/(?<!\p{L})'.preg_quote($phrase, '/').'(?!\p{L})/u';

        return (bool) preg_match($pattern, $normalized);
    }

    private function responseForSmallTalkCategory(string $category): string
    {
        return match ($category) {
            'small_talk.greeting' => 'Chào bạn, mình đang ở đây. Bạn muốn mình hỗ trợ tìm sách hay tư vấn sách nào không?',
            'small_talk.status_check' => 'Có, mình nghe thấy bạn. Bạn cần mình hỗ trợ tìm sách hoặc tư vấn nội dung sách nào không?',
            'small_talk.thanks' => 'Rất vui được hỗ trợ bạn. Khi cần tìm sách hoặc gợi ý sách, bạn cứ nhắn mình nhé.',
            'small_talk.goodbye' => 'Tạm biệt bạn. Khi cần tìm sách trên Bookify, mình luôn sẵn sàng hỗ trợ.',
            'small_talk.capability' => 'Mình có thể hỗ trợ tìm sách, gợi ý sách theo nhu cầu, tóm tắt thông tin sách và trả lời câu hỏi liên quan đến sách trên Bookify.',
            default => 'Mình chỉ hỗ trợ tư vấn thông tin và gợi ý sách trên Bookify.',
        };
    }

    private function responseForUnsupportedCategory(string $category): string
    {
        return match ($category) {
            'order' => 'Mình chỉ hỗ trợ tư vấn sách trên Bookify; mình không tra cứu hoặc xử lý đơn hàng.',
            'payment' => 'Mình chỉ hỗ trợ tư vấn sách trên Bookify; mình không xử lý thanh toán hoặc giao dịch.',
            'refund' => 'Mình chỉ hỗ trợ tư vấn sách trên Bookify; mình không xử lý hoàn tiền hoặc đổi trả.',
            'private_account' => 'Mình chỉ hỗ trợ tư vấn sách trên Bookify; mình không truy cập thông tin tài khoản cá nhân.',
            'non_book_product' => 'Mình chỉ hỗ trợ tư vấn thông tin và gợi ý sách trên Bookify.',
            default => 'Mình chỉ hỗ trợ tư vấn thông tin và gợi ý sách trên Bookify; mình không xử lý đơn hàng, thanh toán, hoàn tiền hoặc thông tin tài khoản.',
        };
    }
}
