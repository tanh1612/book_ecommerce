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
        'embed_batch_size' => (int) env('AI_RAG_EMBED_BATCH_SIZE', 25),
        'rate_limit_stop_on_429' => (bool) env('AI_RAG_SYNC_STOP_ON_429', true),
        'embedding_text_max_description_chars' => (int) env('AI_RAG_EMBEDDING_TEXT_MAX_DESCRIPTION_CHARS', 3000),
        'sync_pending_key' => env('AI_RAG_SYNC_PENDING_KEY', 'ai:rag:sync:pending_book_ids'),
        'sync_queue' => env('AI_RAG_SYNC_QUEUE', 'ai-rag-sync'),
        'sync_job_unique_for' => (int) env('AI_RAG_SYNC_JOB_UNIQUE_FOR', 3600),
        'sync_max_retries' => (int) env('AI_RAG_SYNC_MAX_RETRIES', 5),
        'sync_failed_retry_delay_seconds' => (int) env('AI_RAG_SYNC_FAILED_RETRY_DELAY_SECONDS', 60),
        'sync_retry_counts_key' => env('AI_RAG_SYNC_RETRY_COUNTS_KEY', 'ai:rag:sync:retry_counts'),
        'sync_dead_letter_key' => env('AI_RAG_SYNC_DEAD_LETTER_KEY', 'ai:rag:sync:dead_letter_book_ids'),
        'sync_processing_claims_key' => env('AI_RAG_SYNC_PROCESSING_CLAIMS_KEY', 'ai:rag:sync:processing_claims'),
        'sync_processing_claim_ttl_seconds' => (int) env('AI_RAG_SYNC_PROCESSING_CLAIM_TTL_SECONDS', 900),
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
