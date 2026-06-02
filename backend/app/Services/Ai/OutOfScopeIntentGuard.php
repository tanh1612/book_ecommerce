<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\OutOfScopeIntentGuardResult;
use App\Services\Ai\Support\VietnameseAccentFolder;

class OutOfScopeIntentGuard
{
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

    public function evaluate(string $question): OutOfScopeIntentGuardResult
    {
        $normalized = $this->normalizeIntentText($question);

        if ($normalized === '') {
            return new OutOfScopeIntentGuardResult(
                matched: false,
                category: null,
                response: null,
            );
        }

        $matchedCategory = $this->matchCategory($normalized);

        if ($matchedCategory === null) {
            return new OutOfScopeIntentGuardResult(
                matched: false,
                category: null,
                response: null,
            );
        }

        if ($matchedCategory === 'non_book_product' && $this->hasBookIntent($normalized)) {
            return new OutOfScopeIntentGuardResult(
                matched: false,
                category: null,
                response: null,
            );
        }

        return new OutOfScopeIntentGuardResult(
            matched: true,
            category: $matchedCategory,
            response: $this->responseForCategory($matchedCategory),
        );
    }

    private function normalizeIntentText(string $text): string
    {
        $text = VietnameseAccentFolder::fold(mb_strtolower(trim($text)));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function matchCategory(string $normalizedQuestion): ?string
    {
        foreach (self::CATEGORY_PHRASES as $category => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalizedQuestion, $phrase)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function hasBookIntent(string $normalizedQuestion): bool
    {
        foreach (self::BOOK_INTENT_PHRASES as $phrase) {
            if (str_contains($normalizedQuestion, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function responseForCategory(string $category): string
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
