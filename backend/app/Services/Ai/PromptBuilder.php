<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\PromptBuildResult;
use App\Services\Ai\Dto\RetrievedBookPromptContext;

class PromptBuilder
{
    private const SYSTEM_INSTRUCTION = <<<'TEXT'
Bạn là trợ lý ảo của Bookify.
Chỉ tư vấn thông tin và gợi ý sách; không xử lý đơn hàng, thanh toán, hoàn tiền hoặc thông tin tài khoản.
Chỉ dùng thông tin trong retrieved_context và conversation_history.
Không bịa giá, tồn kho, năm xuất bản, số trang, ISBN, tác giả hoặc tên sách.
Khi gợi ý sách, nếu có thể hãy nêu tên sách, tác giả, giá, rating và lý do ngắn.
Nếu sách hết hàng, không khuyến khích "mua ngay"; chỉ nói hiện sách hết hàng.
Trả lời bằng tiếng Việt có dấu, ngắn gọn, dễ hiểu.
TEXT;

    /**
     * @param  array<int, array{role: string, content: string, created_at: string}>  $history
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    public function build(
        string $question,
        array $history,
        BookRagRetrievalResult $retrieval,
        array $bookContexts,
    ): PromptBuildResult {
        $noRelevantContext = ! $retrieval->matched || $bookContexts === [];

        $sections = [
            $this->formatHistorySection($history),
            $noRelevantContext
                ? $this->formatNoContextSection()
                : $this->formatRetrievedContextSection($bookContexts),
            $this->formatQuestionSection($question),
            $this->formatInstructionSection($noRelevantContext),
        ];

        return new PromptBuildResult(
            systemInstruction: self::SYSTEM_INSTRUCTION,
            userText: implode("\n\n", array_filter($sections)),
            noRelevantContext: $noRelevantContext,
        );
    }

    /**
     * @param  array<int, array{role: string, content: string, created_at: string}>  $history
     */
    private function formatHistorySection(array $history): string
    {
        if ($history === []) {
            return "## Conversation history\n(không có)";
        }

        $lines = ["## Conversation history"];

        foreach ($history as $entry) {
            $role = $entry['role'] === 'assistant' ? 'Assistant' : 'User';
            $lines[] = "{$role}: {$entry['content']}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function formatRetrievedContextSection(array $bookContexts): string
    {
        $blocks = array_map(
            fn (RetrievedBookPromptContext $context): string => $this->formatBookContextBlock($context),
            $bookContexts,
        );

        return "## Retrieved context\n".implode("\n\n", $blocks);
    }

    private function formatNoContextSection(): string
    {
        return "## Retrieved context\n(không có dữ liệu sách phù hợp)";
    }

    private function formatQuestionSection(string $question): string
    {
        return "## Current question\n{$question}";
    }

    private function formatInstructionSection(bool $noRelevantContext): string
    {
        if ($noRelevantContext) {
            $message = (string) config(
                'ai.chat.no_context_message',
                'Tôi chưa tìm thấy thông tin phù hợp trong dữ liệu hiện có của Bookify.',
            );

            return <<<TEXT
## Instructions
no_relevant_context=true
Nếu không có retrieved context phù hợp, hãy trả lời theo hướng: "{$message}"
Không khẳng định rằng Bookify chắc chắn không bán sản phẩm đó.
Không gợi ý sách cụ thể nếu không có retrieved context.
TEXT;
        }

        return <<<'TEXT'
## Instructions
no_relevant_context=false
Chỉ trả lời dựa trên retrieved context và conversation history.
Nếu thông tin không có trong context, nói rõ là chưa có trong dữ liệu hiện có.
Nếu câu hỏi hỏi sách có hay/đáng đọc/nên đọc không, hãy đánh giá dựa trên Rating, Số đánh giá, Mô tả ngắn và Thể loại; không trả lời về giá nếu người dùng không hỏi giá.
TEXT;
    }

    private function formatBookContextBlock(RetrievedBookPromptContext $context): string
    {
        $lines = [
            "[Book #{$context->bookId}]",
            "Tên sách: {$context->name}",
            "Slug: {$context->slug}",
        ];

        if ($context->authorNames !== []) {
            $lines[] = 'Tác giả: '.implode(', ', $context->authorNames);
        }

        if ($context->categoryNames !== []) {
            $lines[] = 'Thể loại: '.implode(', ', $context->categoryNames);
        }

        if ($context->descriptionShort !== '') {
            $lines[] = "Mô tả ngắn: {$context->descriptionShort}";
        }

        if ($context->publisherName !== null) {
            $lines[] = "Nhà xuất bản: {$context->publisherName}";
        }

        $lines[] = 'Giá bán: '.$this->formatPrice($context->sellingPrice);
        $lines[] = 'Rating: '.$context->averageRating;
        $lines[] = 'Số đánh giá: '.$context->reviewCount;
        $lines[] = 'Còn hàng: '.($context->inStock ? 'có' : 'không');
        $lines[] = 'Similarity score: '.number_format($context->similarityScore, 4, '.', '');

        return implode("\n", $lines);
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, '.', '.').' VND';
    }
}
