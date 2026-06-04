<?php

namespace App\Services\Ai;

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\AiChatMessage;
use App\Services\Ai\Support\IntentTextNormalizer;
use Illuminate\Support\Carbon;

class ChatbotIntentAuditService
{
    /**
     * @return array{
     *     since: string,
     *     unmatched_questions: list<array{question: string, count: int}>,
     *     feedback_down_questions: list<array{question: string, count: int}>,
     *     short_unmatched_gemini_samples: list<string>,
     *     no_context_samples: list<string>,
     * }
     */
    public function build(int $days = 7, int $limit = 20): array
    {
        $since = Carbon::now()->subDays(max($days, 1));
        $noContextMessage = (string) config('ai.chat.no_context_message');

        $unmatchedQuestions = AiChatMessage::query()
            ->where('created_at', '>=', $since)
            ->where('retrieval_matched', false)
            ->whereNull('error_code')
            ->selectRaw('question, COUNT(*) as total')
            ->groupBy('question')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(static fn ($row): array => [
                'question' => (string) $row->question,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        $feedbackDownQuestions = AiChatMessage::query()
            ->join('ai_chat_feedback', 'ai_chat_feedback.message_id', '=', 'ai_chat_messages.id')
            ->where('ai_chat_messages.created_at', '>=', $since)
            ->where('ai_chat_feedback.rating', ChatFeedbackRating::Down->value)
            ->selectRaw('ai_chat_messages.question as question, COUNT(*) as total')
            ->groupBy('ai_chat_messages.question')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(static fn ($row): array => [
                'question' => (string) $row->question,
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();

        $shortUnmatchedGeminiSamples = $this->shortUnmatchedGeminiSamples($since, $limit);

        $noContextSamples = AiChatMessage::query()
            ->where('created_at', '>=', $since)
            ->whereNull('error_code')
            ->where('answer', $noContextMessage)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('question')
            ->map(static fn ($question): string => (string) $question)
            ->values()
            ->all();

        return [
            'since' => $since->toDateTimeString(),
            'unmatched_questions' => $unmatchedQuestions,
            'feedback_down_questions' => $feedbackDownQuestions,
            'short_unmatched_gemini_samples' => $shortUnmatchedGeminiSamples,
            'no_context_samples' => $noContextSamples,
        ];
    }

    /**
     * @return list<string>
     */
    private function shortUnmatchedGeminiSamples(Carbon $since, int $limit): array
    {
        return AiChatMessage::query()
            ->where('created_at', '>=', $since)
            ->where('retrieval_matched', false)
            ->whereNull('error_code')
            ->whereNotNull('token_usage')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['question'])
            ->filter(function (AiChatMessage $message): bool {
                $normalized = IntentTextNormalizer::normalize($message->question);
                $tokens = $normalized === '' ? [] : explode(' ', $normalized);

                return count($tokens) > 0 && count($tokens) <= 8;
            })
            ->take($limit)
            ->map(static fn (AiChatMessage $message): string => $message->question)
            ->values()
            ->all();
    }
}
