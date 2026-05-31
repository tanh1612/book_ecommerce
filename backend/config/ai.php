<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-2.5-flash-lite'),
        'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-2'),
        'timeout_seconds' => (int) env('AI_CHAT_TIMEOUT_SECONDS', 15),
        'retry_times' => (int) env('AI_CHAT_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('AI_CHAT_RETRY_SLEEP_MS', 200),
    ],

    'rag' => [
        'index_name' => env('AI_RAG_INDEX_NAME', 'books'),
        'embedder_name' => env('AI_RAG_EMBEDDER_NAME', 'gemini_embedding_2_768'),
        'embedding_dimensions' => (int) env('AI_RAG_EMBEDDING_DIMENSIONS', 768),
        'top_k' => (int) env('AI_RAG_TOP_K', 5),
        'min_score' => (float) env('AI_RAG_MIN_SCORE', 0.65),
        'rrf_min_score' => (float) env('AI_RAG_RRF_MIN_SCORE', 0.02),
        'hybrid_semantic_ratio' => (float) env('AI_RAG_HYBRID_SEMANTIC_RATIO', 0.6),
        'sync_batch_size' => (int) env('AI_RAG_SYNC_BATCH_SIZE', 20),
        'sync_batch_sleep_ms' => (int) env('AI_RAG_SYNC_BATCH_SLEEP_MS', 500),
        'embedding_text_max_description_chars' => (int) env('AI_RAG_EMBEDDING_TEXT_MAX_DESCRIPTION_CHARS', 3000),
    ],

    'chat' => [
        'history_store' => env('AI_CHAT_HISTORY_STORE', 'redis'),
        'history_ttl_seconds' => (int) env('AI_CHAT_HISTORY_TTL_SECONDS', 86400),
        'history_max_turns' => (int) env('AI_CHAT_HISTORY_MAX_TURNS', 10),
        'min_question_length' => (int) env('AI_CHAT_MIN_QUESTION_LENGTH', 2),
        'max_question_length' => (int) env('AI_CHAT_MAX_QUESTION_LENGTH', 1000),
        'stub_message' => env('AI_CHAT_STUB_MESSAGE', 'Chatbot dang duoc trien khai. Vui long quay lai sau.'),
        'fallback_message' => env('AI_CHAT_FALLBACK_MESSAGE', 'Chatbot dang ban, vui long thu lai sau.'),
        'no_context_message' => env('AI_CHAT_NO_CONTEXT_MESSAGE', 'Minh chua tim thay thong tin phu hop trong du lieu Bookify.'),
    ],

    'rate_limits' => [
        'guest_per_minute' => (int) env('AI_CHAT_RATE_LIMIT_GUEST', 20),
        'member_per_minute' => (int) env('AI_CHAT_RATE_LIMIT_MEMBER', 60),
    ],
];
