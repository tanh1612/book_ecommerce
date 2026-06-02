<?php

namespace App\Services\Ai;

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\AiChatEvaluation;
use App\Models\AiChatFeedback;
use App\Models\AiChatMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChatbotOperationsReportService
{
    /**
     * @return array{
     *     messages_24h: int,
     *     messages_7d: int,
     *     retrieval_matched_rate_7d: float|null,
     *     retrieval_strategy_7d: array<string, int>,
     *     gemini_errors_7d: array<string, int>,
     *     evaluation_verdict_7d: array<string, int>,
     *     hallucination_risk_count_7d: int,
     *     feedback_up_7d: int,
     *     feedback_down_7d: int,
     *     avg_latency_ms_7d: float|null,
     *     avg_token_total_7d: float|null,
     * }
     */
    public function build(?Carbon $now = null): array
    {
        $now ??= now();
        $since24h = $now->copy()->subDay();
        $since7d = $now->copy()->subDays(7);

        $messages24h = AiChatMessage::query()
            ->where('created_at', '>=', $since24h)
            ->count();

        $messages7d = AiChatMessage::query()
            ->where('created_at', '>=', $since7d)
            ->count();

        $matched7d = AiChatMessage::query()
            ->where('created_at', '>=', $since7d)
            ->where('retrieval_matched', true)
            ->count();

        $retrievalMatchedRate7d = $messages7d > 0
            ? round($matched7d / $messages7d * 100, 2)
            : null;

        $retrievalStrategy7d = AiChatMessage::query()
            ->where('created_at', '>=', $since7d)
            ->select('retrieval_strategy', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('retrieval_strategy')
            ->orderBy('retrieval_strategy')
            ->pluck('aggregate', 'retrieval_strategy')
            ->map(fn ($count) => (int) $count)
            ->all();

        $geminiErrors7d = AiChatMessage::query()
            ->where('created_at', '>=', $since7d)
            ->whereNotNull('error_code')
            ->select('error_code', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('error_code')
            ->orderBy('error_code')
            ->pluck('aggregate', 'error_code')
            ->map(fn ($count) => (int) $count)
            ->all();

        $evaluationVerdict7d = AiChatEvaluation::query()
            ->join('ai_chat_messages', 'ai_chat_messages.id', '=', 'ai_chat_evaluations.message_id')
            ->where('ai_chat_messages.created_at', '>=', $since7d)
            ->select('ai_chat_evaluations.verdict', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('ai_chat_evaluations.verdict')
            ->orderBy('ai_chat_evaluations.verdict')
            ->pluck('aggregate', 'verdict')
            ->map(fn ($count) => (int) $count)
            ->all();

        $hallucinationRiskCount7d = AiChatEvaluation::query()
            ->join('ai_chat_messages', 'ai_chat_messages.id', '=', 'ai_chat_evaluations.message_id')
            ->where('ai_chat_messages.created_at', '>=', $since7d)
            ->where('ai_chat_evaluations.has_hallucination_risk', true)
            ->count();

        $feedbackUp7d = AiChatFeedback::query()
            ->where('created_at', '>=', $since7d)
            ->where('rating', ChatFeedbackRating::Up)
            ->count();

        $feedbackDown7d = AiChatFeedback::query()
            ->where('created_at', '>=', $since7d)
            ->where('rating', ChatFeedbackRating::Down)
            ->count();

        $avgLatencyMs7d = AiChatMessage::query()
            ->where('created_at', '>=', $since7d)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        $avgTokenTotal7d = $this->averageTokenTotalLast7Days($since7d);

        return [
            'messages_24h' => $messages24h,
            'messages_7d' => $messages7d,
            'retrieval_matched_rate_7d' => $retrievalMatchedRate7d,
            'retrieval_strategy_7d' => $retrievalStrategy7d,
            'gemini_errors_7d' => $geminiErrors7d,
            'evaluation_verdict_7d' => $evaluationVerdict7d,
            'hallucination_risk_count_7d' => $hallucinationRiskCount7d,
            'feedback_up_7d' => $feedbackUp7d,
            'feedback_down_7d' => $feedbackDown7d,
            'avg_latency_ms_7d' => $avgLatencyMs7d !== null ? round((float) $avgLatencyMs7d, 2) : null,
            'avg_token_total_7d' => $avgTokenTotal7d,
        ];
    }

    private function averageTokenTotalLast7Days(Carbon $since7d): ?float
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $average = AiChatMessage::query()
                ->where('created_at', '>=', $since7d)
                ->whereNotNull('token_usage')
                ->get(['token_usage'])
                ->map(fn (AiChatMessage $message) => $message->token_usage['total'] ?? null)
                ->filter(fn ($total) => $total !== null)
                ->avg();

            return $average !== null ? round((float) $average, 2) : null;
        }

        $average = AiChatMessage::query()
            ->where('created_at', '>=', $since7d)
            ->whereNotNull('token_usage')
            ->selectRaw("AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(token_usage, '$.total')) AS UNSIGNED)) as aggregate")
            ->value('aggregate');

        return $average !== null ? round((float) $average, 2) : null;
    }
}
