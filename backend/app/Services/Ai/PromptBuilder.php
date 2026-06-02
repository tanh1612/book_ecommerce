<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\PromptBuildResult;
use App\Services\Ai\Dto\RetrievedBookPromptContext;

class PromptBuilder
{
    private const SYSTEM_INSTRUCTION = <<<'TEXT'
Ban la tro ly ao cua Bookify. 
Chi tu van thong tin va goi y sach; khong xy ly don hang, thanh toan, hoan tien hoac thong tin tai khoan.
Chi dung thong tin trong retrieved_context va conversation_history.
Khong bia gia, ton kho, nam xuat ban, so trang, ISBN, tac gia hoac ten sach.
Khi goi y sach, neu co the hay neu ten sach, tac gia, gia, rating va ly do ngan.
Neu sach het hang, khong khuyen khich "mua ngay"; chi noi hien sach het hang.
Tra loi bang tieng Viet, ngan gon, de hieu.
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
            return "## Conversation history\n(khong co)";
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
        return "## Retrieved context\n(khong co du lieu sach phu hop)";
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
                'Minh chua tim thay thong tin phu hop trong du lieu hien co.',
            );

            return <<<TEXT
## Instructions
no_relevant_context=true
Neu khong co retrieved context phu hop, hay tra loi theo huong: "{$message}"
Khong khang dinh rang Bookify chac chan khong ban san pham do.
Khong goi y sach cu the neu khong co retrieved context.
TEXT;
        }

        return <<<'TEXT'
## Instructions
no_relevant_context=false
Chi tra loi dua tren retrieved context va conversation history.
Neu thong tin khong co trong context, noi ro la chua co trong du lieu hien co.
Neu cau hoi hoi sach co hay/dang doc/hay hay do, hay danh gia dua tren Rating, So danh gia, Mo ta ngan va The loai; khong tra loi ve gia neu nguoi dung khong hoi gia.
TEXT;
    }

    private function formatBookContextBlock(RetrievedBookPromptContext $context): string
    {
        $lines = [
            "[Book #{$context->bookId}]",
            "Ten sach: {$context->name}",
            "Slug: {$context->slug}",
        ];

        if ($context->authorNames !== []) {
            $lines[] = 'Tac gia: '.implode(', ', $context->authorNames);
        }

        if ($context->categoryNames !== []) {
            $lines[] = 'The loai: '.implode(', ', $context->categoryNames);
        }

        if ($context->descriptionShort !== '') {
            $lines[] = "Mo ta ngan: {$context->descriptionShort}";
        }

        if ($context->publisherName !== null) {
            $lines[] = "Nha xuat ban: {$context->publisherName}";
        }

        $lines[] = 'Gia ban: '.$this->formatPrice($context->sellingPrice);
        $lines[] = 'Rating: '.$context->averageRating;
        $lines[] = 'So danh gia: '.$context->reviewCount;
        $lines[] = 'Con hang: '.($context->inStock ? 'co' : 'khong');
        $lines[] = 'Similarity score: '.number_format($context->similarityScore, 4, '.', '');

        return implode("\n", $lines);
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, '.', '.').' VND';
    }
}
