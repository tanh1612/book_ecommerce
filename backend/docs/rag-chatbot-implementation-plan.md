# Ke hoach trien khai RAG Chatbot

## 1. Muc tieu

Xay dung chatbot RAG muc co ban cho Bookify, co kha nang tra loi cau hoi cua khach hang dua tren du lieu sach trong he thong va co lop danh gia chat luong cau tra loi.

He thong can:

- Tra loi ve sach, tac gia, the loai, mo ta, gia, danh gia va tinh trang con hang.
- Goi y sach phu hop voi nhu cau nguoi dung dua tren ngu canh duoc truy xuat.
- Chi tra loi dua tren du lieu Bookify cung cap, khong bia thong tin.
- Luu lich su hoi thoai ngan han trong Redis voi TTL 24 gio.
- Ghi log chat va ket qua danh gia de phan tich chat luong ve sau.
- Cho phep nguoi dung danh gia cau tra loi bang feedback don gian.

## 2. Pham vi MVP

### 2.1. Lam trong MVP

- API chat: `POST /api/v1/ai/chat`.
- Lich su hoi thoai luu Redis theo `session_id`.
- Guest dung `session_id` do frontend sinh va gui len.
- Embedding cau hoi bang `gemini-embedding-2` (768 chieu qua `output_dimensionality`).
- Hybrid search tren Meilisearch: ket hop keyword search va vector search (`semanticRatio` 0.5–0.7).
- Prompt builder voi sliding window history.
- Goi `gemini-2.5-flash-lite` de sinh cau tra loi (co the nang cap `gemini-2.5-flash` sau).
- Fallback khi khong tim thay ngu canh phu hop.
- Fallback than thien khi Gemini/Meilisearch loi.
- Danh gia tu dong co ban sau khi co cau tra loi.
- API feedback cua nguoi dung.
- Luu chat message, retrieval metadata, model version, evaluation va feedback vao database.
- Test cho cac luong chinh.

### 2.2. Chua lam trong MVP

- Tra cuu don hang ca nhan, hoan tien, dia chi giao hang hoac thong tin rieng tu khac.
- LLM-as-judge nang cao cho hallucination detection.
- Query expansion.
- Fine-tuning model.
- Streaming response.
- Admin dashboard phan tich chatbot.

## 3. Luong nghiep vu muc tieu

```mermaid
flowchart TD
    A["Khach hang nhap va gui cau hoi"] --> B["API POST /api/v1/ai/chat"]
    B --> C["Validate question va session_id"]
    C --> D["Lay lich su hoi thoai tu Redis"]
    D --> E["Embed cau hoi bang Gemini"]
    E --> F["Hybrid search Meilisearch"]
    F --> G{"Kiem tra do tuong dong"}
    G -- "Cao" --> H["Trich xuat ngu canh tim duoc"]
    G -- "Thap" --> I["Tao co no_relevant_context"]
    H --> J["Lap prompt hoan chinh"]
    I --> J
    J --> K["Gui prompt qua Gemini chat API"]
    K --> L["Nhan cau tra loi"]
    L --> M["Danh gia cau tra loi"]
    M --> N["Luu DB va Redis history neu co cau tra loi that"]
    N --> O["Tra response JSON cho frontend"]
```

## 4. Quyet dinh ky thuat da chot

| Noi dung | Quyet dinh |
| --- | --- |
| Backend | Laravel API trong `backend/` |
| Auth | Sanctum cookie-based, API chat cho phep guest va member |
| LLM | Google Gemini Chat API — `gemini-2.5-flash-lite` (MVP); nang cap `gemini-2.5-flash` neu chat quality khong dat |
| Embedding | Gemini `gemini-embedding-2` |
| Embedding dimension | 768 (`output_dimensionality` qua Matryoshka; mac dinh model la 3072) |
| Vector store | Meilisearch |
| Vector mode | User-provided vectors |
| Search mode | Hybrid search: keyword + vector, `semanticRatio` 0.5–0.7 |
| Redis | Luu history voi TTL 24h |
| Database | MySQL luu message, evaluation, feedback |
| Frontend session | Guest sinh UUID v4 va luu `localStorage` |
| Backend session | Backend validate format, khong tu sinh session cho guest |
| Rate limit | Tach guest/member |
| History in prompt | Sliding window, mac dinh 10 luot gan nhat |
| Fallback Gemini loi | Tra fallback cho user, log DB voi `error_code`, khong luu fallback vao Redis history |
| Metadata dong trong prompt | Sau retrieval, fetch lai DB cho top K de lay gia/rating/ton kho hien tai |
| Evaluation MVP | Chay sync trong request vi rule-based nhanh; chi queue khi nang cap LLM-as-judge |

## 5. API contract du kien

### 5.1. Chat

Endpoint:

```text
POST /api/v1/ai/chat
```

Middleware:

```text
web, throttle:ai-chat
```

Request:

```json
{
  "session_id": "uuid-v4-string",
  "question": "Toi muon tim sach ve ky nang giao tiep"
}
```

Quy tac:

- `session_id` bat buoc cho guest va member.
- Frontend guest sinh UUID v4, luu `localStorage`, gui lai trong moi request chat.
- Backend chi validate `session_id`, khong dung session_id de xac thuc nguoi dung.
- Neu user da dang nhap, `account_id` lay tu `auth()->id()` de log va phan tich.
- `question` gioi han do dai, vi du 2 den 1000 ky tu.

Response thanh cong:

```json
{
  "data": {
    "message_id": 123,
    "answer": "Ban co the tham khao...",
    "sources": [
      {
        "book_id": 10,
        "name": "Ten sach",
        "slug": "ten-sach",
        "score": 0.82
      }
    ]
  },
  "meta": {
    "session_id": "uuid-v4-string",
    "model": "gemini-2.5-flash-lite",
    "retrieval": {
      "strategy": "hybrid",
      "top_score": 0.82,
      "matched": true
    },
    "evaluation": {
      "verdict": "pass",
      "groundedness_score": 0.8,
      "relevance_score": 0.8,
      "has_hallucination_risk": false
    }
  }
}
```

Response fallback khi Gemini loi hoac DB log loi:

```json
{
  "data": {
    "message_id": null,
    "answer": "Chatbot dang ban, vui long thu lai sau.",
    "sources": []
  },
  "meta": {
    "session_id": "uuid-v4-string",
    "model": null,
    "retrieval": {
      "strategy": "none",
      "top_score": null,
      "matched": false
    },
    "evaluation": null,
    "error_code": "gemini_chat_failed"
  }
}
```

Quy tac response:

- `message_id` nullable. Frontend chi hien feedback controls khi `message_id` khac null.
- `meta.evaluation` nullable neu khong co cau tra loi that tu Gemini hoac ghi DB/evaluation that bai.
- Fallback do Gemini chat loi khong duoc xem la cau tra loi that va khong ghi vao Redis history.

### 5.2. Feedback

Endpoint:

```text
POST /api/v1/ai/messages/{message}/feedback
```

Request guest:

```json
{
  "session_id": "uuid-v4-string",
  "rating": "up"
}
```

Request member cho message cua minh:

```json
{
  "rating": "down"
}
```

Gia tri `rating`:

- `up`
- `down`

Khong nhan `reason`/`comment` trong MVP de giam ma sat feedback.

Quy tac authorization:

- Guest message (`ai_chat_messages.account_id = null`): request phai co `session_id` khop message.
- Member message (`ai_chat_messages.account_id != null`): user dang nhap phai co `id` khop `account_id`; khong chap nhan chi dua vao `session_id`.
- User da dang nhap van co the feedback guest message cu neu gui dung `session_id` cu.

## 6. Cau hinh moi

Tao `config/ai.php`.

```php
<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
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
        'min_score' => (float) env('AI_RAG_MIN_SCORE', 0.80),
        'rrf_min_score' => (float) env('AI_RAG_RRF_MIN_SCORE', 0.016),
        'hybrid_semantic_ratio' => (float) env('AI_RAG_HYBRID_SEMANTIC_RATIO', 0.6),
        'sync_batch_size' => (int) env('AI_RAG_SYNC_BATCH_SIZE', 20),
        'sync_batch_sleep_ms' => (int) env('AI_RAG_SYNC_BATCH_SLEEP_MS', 500),
        'embed_batch_size' => (int) env('AI_RAG_EMBED_BATCH_SIZE', 25),
        'rate_limit_stop_on_429' => (bool) env('AI_RAG_SYNC_STOP_ON_429', true),
    ],

    'chat' => [
        'history_ttl_seconds' => (int) env('AI_CHAT_HISTORY_TTL_SECONDS', 86400),
        'history_max_turns' => (int) env('AI_CHAT_HISTORY_MAX_TURNS', 10),
        'max_question_length' => (int) env('AI_CHAT_MAX_QUESTION_LENGTH', 1000),
        'fallback_message' => env('AI_CHAT_FALLBACK_MESSAGE', 'Chatbot dang ban, vui long thu lai sau.'),
        'no_context_message' => env('AI_CHAT_NO_CONTEXT_MESSAGE', 'Minh chua tim thay thong tin phu hop trong du lieu hien co.'),
    ],

    'rate_limits' => [
        'guest_per_minute' => (int) env('AI_CHAT_RATE_LIMIT_GUEST', 20),
        'member_per_minute' => (int) env('AI_CHAT_RATE_LIMIT_MEMBER', 60),
    ],
];
```

Bien moi trong `.env`:

```env
GEMINI_API_KEY=
GEMINI_CHAT_MODEL=gemini-2.5-flash-lite
GEMINI_EMBEDDING_MODEL=gemini-embedding-2
AI_CHAT_TIMEOUT_SECONDS=15
AI_CHAT_RETRY_TIMES=2
AI_CHAT_RETRY_SLEEP_MS=200
AI_RAG_INDEX_NAME=books
AI_RAG_EMBEDDER_NAME=gemini_embedding_2_768
AI_RAG_EMBEDDING_DIMENSIONS=768
AI_RAG_TOP_K=5
AI_RAG_MIN_SCORE=0.80
AI_RAG_RRF_MIN_SCORE=0.016
AI_RAG_HYBRID_SEMANTIC_RATIO=0.6
AI_RAG_SYNC_BATCH_SIZE=20
AI_RAG_SYNC_BATCH_SLEEP_MS=500
AI_RAG_EMBED_BATCH_SIZE=25
AI_RAG_SYNC_STOP_ON_429=true
AI_CHAT_HISTORY_TTL_SECONDS=86400
AI_CHAT_HISTORY_MAX_TURNS=10
AI_CHAT_MAX_QUESTION_LENGTH=1000
AI_CHAT_RATE_LIMIT_GUEST=20
AI_CHAT_RATE_LIMIT_MEMBER=60
```

Sau khi thay doi `.env` tren moi moi truong:

```bash
php artisan config:clear
```

Ghi chu model:

- `gemini-1.5-flash` va `text-embedding-004` da shutdown (09/2025 va 01/2026); khong dung lai.
- LLM MVP: `gemini-2.5-flash-lite` — khong co thinking mac dinh, latency/chi phi thap hon `gemini-2.5-flash`. Neu chat quality khong dat sau manual test, doi `GEMINI_CHAT_MODEL=gemini-2.5-flash` (co the can `thinkingBudget=0` de giam latency).
- Embedding: `gemini-embedding-2` mac dinh 3072 chieu; bat buoc truyen `output_dimensionality` = `AI_RAG_EMBEDDING_DIMENSIONS` (768) trong moi request `embedContent` va moi item cua `batchEmbedContents`. Vector truncated duoc model tu normalize.
- Full sync khong goi 1 request Gemini cho tung sach neu co nhieu sach. Dung `batchEmbedContents` voi `AI_RAG_EMBED_BATCH_SIZE` de giam request/day. Vi du 1.800 sach voi batch 25 chi can khoang 72 embedding requests thay vi 1.800 requests.
- Batch embedding chi giam request count, khong giam tong input tokens. Van phai dieu tiet `AI_RAG_SYNC_BATCH_SLEEP_MS` theo TPM/RPM cua Google AI Studio, dac biet free tier co RPD/RPM/TPM thap.
- Doi embedding model hoac dimension bat buoc re-index toan bo Meilisearch va doi `AI_RAG_EMBEDDER_NAME` tuong ung.

## 7. Du lieu embedding cua sach

### 7.1. Nguyen tac

Text dua vao embedding phai on dinh va the hien noi dung ngu nghia cua sach. Khong dua metadata thay doi thuong xuyen vao embedding text.

Khong dua vao embedding text:

- Gia ban.
- Ton kho.
- Rating trung binh.
- Review count.
- Flash sale/promotion.

Cac truong dong nay chi dua vao retrieved context luc build prompt.

### 7.2. Template embedding text

Tao service/factory rieng, vi du:

```text
Ten sach: {book.name}
Tac gia: {author_names}
The loai: {category_names}
Mo ta: {description}
Nha xuat ban: {publisher_name}
Ngon ngu: {language}
Hinh thuc: {format}
Nam xuat ban: {publication_year}
```

Neu field khong co du lieu thi bo qua dong do. Can normalize whitespace va cat description qua dai neu can.

### 7.3. Context dua vao prompt

Context trong prompt co the giau hon embedding text:

```text
[Book #10]
Ten sach: ...
Slug: ...
Tac gia: ...
The loai: ...
Mo ta ngan: ...
Nha xuat ban: ...
Gia ban: ...
Rating: ...
So danh gia: ...
Con hang: co/khong
Similarity score: ...
```

Quy tac:

- Neu `in_stock = false`, van co the dung de tra loi thong tin sach, nhung khi goi y mua phai noi sach hien het hang.
- Gia, rating, ton kho bat buoc fetch lai tu MySQL cho top K `book_id` sau retrieval, truoc khi build prompt. Khong dung truc tiep metadata dong trong Meilisearch document de tra loi gia/ton kho.

## 8. Meilisearch vector va hybrid search

### 8.1. Embedder configuration

Vi embedding duoc tinh ben ngoai bang Gemini, Meilisearch can user-provided embedder.

Ten embedder mac dinh:

```text
gemini_embedding_2_768
```

Cau hinh index can khai bao embedder user-provided voi dimension 768 (khop `output_dimensionality` cua `gemini-embedding-2`). Viec nay nen lam bang command rieng:

```text
php artisan ai:meilisearch-configure
```

Command thuc hien:

- Lay index tu `config('ai.rag.index_name')`.
- Goi Meilisearch API cap nhat embedders.
- Khai bao embedder `userProvided`.
- Khai bao dimension `768`.
- Dam bao searchable/filterable/sortable attributes hien co cua `books` khong bi mat.
- Dam bao `rag_embedding_text` khong nam trong `searchableAttributes`; truong nay chi dung debug/vector rebuild, khong dung keyword search.

Ghi chu van hanh:

- Neu doi embedding model hoac dimension, bat buoc re-index toan bo vector documents.
- Khong tron vector cua nhieu model trong cung mot embedder.

### 8.2. Payload document

Khi sync document vao Meilisearch, payload can co `_vectors`.

Vi du:

```json
{
  "id": 10,
  "name": "Ten sach",
  "slug": "ten-sach",
  "description": "...",
  "author_names": ["Tac gia A"],
  "category_ids": [1, 2],
  "category_names": ["Ky nang"],
  "publisher_name": "NXB ...",
  "selling_price": 120000,
  "average_rating": 4.5,
  "review_count": 20,
  "available_stock": 12,
  "in_stock": true,
  "is_active": true,
  "rag_embedding_text": "Ten sach: ...",
  "_vectors": {
    "gemini_embedding_2_768": [0.01, 0.02]
  }
}
```

### 8.3. Hybrid search

Retrieval nen dung hybrid search thay vi pure vector.

Muc tieu:

- Cau hoi ngu nghia: "sach ve ky nang giao tiep" duoc vector search xu ly tot.
- Cau hoi exact match: "Dac Nhan Tam" van duoc keyword search xu ly tot.

Tham so de cau hinh:

- `top_k`: mac dinh 5.
- `min_score`: mac dinh 0.80 sau khi smoke test full-vector; `0.65` qua thap voi score scale hybrid thuc te va de match cau ngoai domain.
- `hybrid_semantic_ratio`: mac dinh 0.6; khoang khuyen nghi 0.5–0.7 (Meilisearch ecommerce). Tang neu cau hoi ngu nghia nhieu, giam neu exact match ten sach/tac gia quan trong hon.

Ghi chu khi chua sync du vector:

- Co the trien khai retriever khi vector coverage chua dat 100%, vi keyword search van tim duoc sach theo ten sach/tac gia/mo ta da index.
- Chatbot khong duoc hieu `matched = false` la "Bookify chac chan khong co sach phu hop" khi vector coverage con thap. Chi dien giai la "chua tim thay thong tin phu hop trong du lieu hien co".
- Manual test semantic query chi co gia tri khi cac sach lien quan da co `_vectors`. Truoc demo can ghi nhan coverage: tong active books, so active books co vector, ty le phan tram.
- Uu tien chay `php artisan ai:sync-book-rag-documents --missing-vectors --limit=...` de lap day cac vector con thieu truoc khi danh gia chat luong semantic.

Neu Meilisearch local chua ho tro hybrid dung API version hien tai, fallback co kiem soat:

1. Chay keyword search voi query goc.
2. Chay vector search voi embedding.
3. Merge ket qua bang Reciprocal Rank Fusion, khong cong truc tiep raw score vi score keyword va vector khac scale.

Cong thuc fallback RRF:

```text
rrf_score(book) = sum(1 / (rank + 60))
```

Quy tac fallback RRF:

- Rank bat dau tu 1 trong tung danh sach keyword/vector.
- Ket qua co trong ca hai danh sach se duoc cong diem hai lan.
- Khi dung RRF fallback, `min_score = 0.80` khong ap dung. Dung threshold rieng `AI_RAG_RRF_MIN_SCORE`.
- Voi cong thuc tren, top 1 chi xuat hien trong mot list co diem `1 / 61 = 0.0164`. Neu giu threshold `0.02`, ket qua chi co o keyword hoac chi co o vector se khong `matched`. De MVP than thien hon voi exact title va du lieu vector chua day du, threshold RRF nen la `0.016` hoac co rule rieng cho keyword top 1 score cao/exact title.
- Chi fallback sang RRF khi loi la hybrid unsupported, vi du unknown field `hybrid` hoac invalid hybrid parameter. Khong fallback cho loi cau hinh/quyen truy cap nhu 401/403, index khong ton tai, filter invalid, embedder invalid; cac loi nay phai log va degrade `strategy = none`.
- Neu hybrid tra ve hit nhung diem thap, giu `strategy = hybrid`, `top_score` va `documents`, chi dat `matched = false`. Chi dung `strategy = none` khi khong co hit hoac retrieval loi.
- Truoc khi code fallback, can test Meilisearch version local/production co ho tro hybrid `semanticRatio` hay khong de quyet dinh co can RRF path.

### 8.4. Dong bo khi du lieu sach thay doi

Vector document phai duoc cap nhat khi embedding text thay doi.

Trigger MVP:

- `BookObserver`: enqueue sync request cho book khi `name`, `is_active` hoac cac truong search quan trong thay doi.
- `BookDetailObserver`: enqueue sync request khi `description`, `language`, `format`, `publication_year` thay doi.
- `AuthorObserver`/quan he `book_authors`: enqueue cac `book_id` bi anh huong khi ten tac gia hoac gan tac gia thay doi.
- `CategoryObserver`/quan he `book_categories`: enqueue cac `book_id` bi anh huong khi ten danh muc hoac gan danh muc thay doi.
- `PublisherObserver`: enqueue cac `book_id` bi anh huong khi ten nha xuat ban thay doi.

Khong dispatch mot job Gemini rieng cho tung sach ngay trong observer khi co thay doi quan he lon. Observer chi ghi/debounce sync request, sau do worker/command xu ly theo batch.

Co che batch/debounce de tranh fanout:

- Redis set/list tam: `ai:rag:sync:pending_book_ids`.
- Observer add `book_id` vao pending set sau commit; trung lap tu nhieu observer chi giu mot lan.
- Job/command batch lay toi da `AI_RAG_SYNC_BATCH_SIZE`, mac dinh 20 book/lua.
- Trong moi batch sach, service chia tiep thanh cac request `batchEmbedContents` co toi da `AI_RAG_EMBED_BATCH_SIZE`, mac dinh 25 embedding texts/lua. Neu batch sach nho hon embed batch thi chi tao 1 request Gemini.
- Giua cac batch sleep `AI_RAG_SYNC_BATCH_SLEEP_MS`, mac dinh 500ms. Khi dung free tier nen cau hinh sleep theo TPM/RPM/RPD thuc te tren Google AI Studio; batch embedding giam RPD nhung van co the cham TPM neu text qua dai hoac batch qua lon.
- Queue cho sync nen tach rieng, vi du `ai-rag-sync`, de khong canh tranh voi checkout/payment/order jobs.
- Neu mot NXB co 500 sach, he thong tao pending set 500 id nhung worker xu ly theo cac batch nho, moi batch tao it request `batchEmbedContents` thay vi dispatch 500 request Gemini rieng le.
- Khi Gemini tra HTTP 429 trong full sync, command phai dung som neu `AI_RAG_SYNC_STOP_ON_429=true`, ghi cac book chua xu ly vao pending set hoac in range resume, khong tiep tuc fail hang loat.
- Pending worker khong duoc lam mat book id khi sync fail; failed ids phai duoc re-add vao pending set hoac dua vao dead-letter co command retry ro rang.

Thanh phan goi y:

- `QueueBookRagSyncService`: nhan danh sach `book_id`, ghi pending set va debounce.
- `SyncPendingBookRagDocuments`: job/command doc pending set theo batch, goi embedding va update Meilisearch.
- `SyncBookRagDocument`: chi dung cho sync mot sach thu cong hoac trong batch worker, khong dispatch hang loat truc tiep tu observer.

Schedule bo tro:

- Chay full/partial sync dinh ky hang dem neu can, de sua cac event bi miss.
- Metadata dong nhu gia, rating, ton kho co the sync vao Meilisearch de phuc vu filter/debug, nhung prompt van fetch lai MySQL cho top K.

## 9. Thiet ke database bo sung

### 9.1. Bang `ai_chat_messages`

Muc dich: log moi luot hoi-dap va metadata phuc vu debug/chat quality.

| Cot | Kieu goi y | Ghi chu |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `session_id` | `char(36)` | UUID v4 tu frontend |
| `account_id` | `bigint unsigned nullable` | Member neu da dang nhap |
| `question` | `text` | Cau hoi cua nguoi dung |
| `answer` | `text` | Cau tra loi tra ve |
| `model_version` | `varchar(100)` | Vi du `gemini-2.5-flash-lite` |
| `retrieval_strategy` | `varchar(30)` | `hybrid`, `vector`, `keyword`, `none` |
| `retrieval_top_score` | `decimal(6,4) nullable` | Score cao nhat |
| `retrieval_matched` | `boolean` | Co dat threshold hay khong |
| `retrieved_books` | `json nullable` | Chi luu book_id, score, chunk key; khong luu full context dai |
| `token_usage` | `json nullable` | Neu Gemini tra ve |
| `latency_ms` | `integer unsigned nullable` | Tong latency |
| `error_code` | `varchar(50) nullable` | Neu degrade/fallback |
| `created_at`, `updated_at` | `timestamp nullable` | Theo Laravel |

Index:

- `session_id, created_at`
- `account_id, created_at`
- `retrieval_matched, created_at`

### 9.2. Bang `ai_chat_evaluations`

Muc dich: luu ket qua danh gia tu dong.

| Cot | Kieu goi y | Ghi chu |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `message_id` | `bigint unsigned` | FK `ai_chat_messages.id` |
| `groundedness_score` | `decimal(4,3)` | 0 den 1 |
| `relevance_score` | `decimal(4,3)` | 0 den 1 |
| `has_hallucination_risk` | `boolean` | Regex/entity rule |
| `verdict` | `varchar(20)` | `pass`, `warning`, `fail` |
| `risk_flags` | `json nullable` | Danh sach flag |
| `evaluated_at` | `timestamp` | Thoi diem danh gia |

Constraint:

- FK cascade delete theo message.
- Co the unique `message_id` neu moi message chi co mot evaluation.

### 9.3. Bang `ai_chat_feedback`

Muc dich: luu danh gia cua nguoi dung.

| Cot | Kieu goi y | Ghi chu |
| --- | --- | --- |
| `id` | `bigint unsigned` | Primary key |
| `message_id` | `bigint unsigned` | FK `ai_chat_messages.id` |
| `account_id` | `bigint unsigned nullable` | Neu da dang nhap |
| `session_id` | `char(36)` | De guest feedback |
| `rating` | `varchar(10)` | `up`, `down` |
| `created_at`, `updated_at` | `timestamp nullable` | Theo Laravel |

Index:

- unique `message_id`
- `account_id, created_at`
- `session_id, created_at`

Quy tac:

- Moi `message_id` chi co mot feedback; gui lai thi update rating cu.
- Guest message (`account_id = null`): request phai co `session_id` khop `ai_chat_messages.session_id`.
- Member message (`account_id != null`): user dang nhap phai co `id` khop `ai_chat_messages.account_id`; khong cho phep feedback member message chi bang `session_id`.
- Guest chat roi dang nhap sau do van duoc feedback message cu neu gui dung `session_id` cu.
- Member chat tren thiet bi A va feedback tren thiet bi B van hop le neu `account_id` khop, du `session_id` khac.
- MVP khong luu `reason`/`comment`.

## 10. Service va thanh phan du kien

| Thanh phan | Vai tro |
| --- | --- |
| `App\Http\Controllers\Api\V1\Ai\ChatController` | Nhan request, goi service, tra resource |
| `App\Http\Controllers\Api\V1\Ai\ChatFeedbackController` | Nhan feedback |
| `App\Http\Requests\Ai\ChatRequest` | Validate `session_id`, `question` |
| `App\Http\Requests\Ai\ChatFeedbackRequest` | Validate feedback |
| `App\Http\Resources\Ai\ChatMessageResource` | Response chat |
| `App\Services\Ai\ChatbotService` | Orchestrate toan bo pipeline |
| `App\Services\Ai\PromptBuilder` | Build system prompt, history, context |
| `App\Services\Ai\GeminiClient` | Goi chat, single embedding va batch embedding API |
| `App\Services\Ai\BookRagRetriever` | Hybrid retrieval tu Meilisearch |
| `App\Services\Ai\BookRagDocumentFactory` | Tao text embedding va payload context |
| `App\Services\Ai\ChatHistoryStore` | Doc/ghi Redis history |
| `App\Services\Ai\ChatEvaluationService` | Danh gia groundedness/relevance/risk |
| `App\Services\Ai\ChatFeedbackService` | Luu/update feedback |
| `App\Services\Ai\QueueBookRagSyncService` | Debounce va gom `book_id` can sync vao Redis pending set |
| `App\Console\Commands\Ai\ConfigureMeilisearchVectorIndexCommand` | Cau hinh embedder |
| `App\Console\Commands\Ai\SyncBookRagDocumentsCommand` | Rebuild/sync vector documents |
| `App\Jobs\Ai\SyncPendingBookRagDocuments` | Xu ly pending sync theo batch va sleep giua batch |
| `App\Jobs\Ai\SyncBookRagDocument` | Sync vector cho mot sach neu can queue |

## 11. Rate limiting

Can them limiter rieng trong `AppServiceProvider` hoac provider phu hop:

- Guest: theo IP + `session_id`, mac dinh 20 request/phut.
- Member: theo `account_id`, mac dinh 60 request/phut.

Route dung:

```text
throttle:ai-chat
```

Ly do:

- Moi request co the goi Gemini embedding va chat, anh huong chi phi.
- Guest khong co account_id nen khong the chi limit theo user.

Neu vuot gioi han:

- Tra HTTP `429`.
- Message JSON ngan gon: "Ban dang gui qua nhieu tin nhan, vui long thu lai sau."

## 12. Error handling va degrade

### 12.1. Gemini embedding loi

Embedding loi thi fail fast vi khong co vector de retrieval.

Ung xu:

- Log warning/error voi latency va message.
- Tra fallback message than thien.
- Luu `ai_chat_messages.error_code = embedding_failed` neu co tao message log.
- Khong append question/fallback vao Redis history.

### 12.2. Meilisearch loi

Meilisearch loi thi degrade co kiem soat.

Ung xu MVP:

- Log warning.
- Build prompt voi context rong va chi thi "khong co ngu canh truy xuat".
- Van co the goi Gemini de tra loi fallback theo system prompt.
- `retrieval_strategy = none`, `retrieval_matched = false`.

### 12.3. Gemini chat loi

Gemini timeout/rate limit/5xx:

- Bat exception trong `GeminiClient`.
- Log warning voi status code/latency.
- Tra message chuan trong config: `AI_CHAT_FALLBACK_MESSAGE`.
- Khong de loi 500 bubble len frontend.
- Co the luu DB log voi `error_code = gemini_chat_failed`, `answer` la fallback message de thong ke loi.
- Khong luu fallback message vao Redis history, de tranh lam nhieu context cua cac luot sau.

### 12.4. Database log loi

Neu ghi log DB loi:

- Khong lam request chat fail neu da co cau tra loi.
- Log error voi context toi thieu.
- Response co the khong co `message_id`, frontend khi do an feedback buttons hoac disable feedback.
- Neu Redis ghi history thanh cong nhung DB log loi, response van tra cau tra loi that voi `message_id = null`.

## 13. Prompt design

### 13.1. System instruction

System prompt can co cac quy tac:

- Ban la tro ly ao cua Bookify, chi ho tro ve sach va mua sach tren Bookify.
- Chi dung thong tin trong `retrieved_context` va `conversation_history`.
- Khong bia gia, ton kho, nam xuat ban, so trang, ISBN, tac gia.
- Neu khong co thong tin phu hop, noi ro chua tim thay trong du lieu Bookify.
- Khi goi y sach, neu co the hay neu ten sach, tac gia, gia, rating va ly do ngan.
- Neu sach het hang, khong khuyen khich "mua ngay"; chi noi hien sach het hang.
- Tra loi bang tieng Viet, ngan gon, de hieu.

### 13.2. Sliding window history

`PromptBuilder` chi dua toi da `AI_CHAT_HISTORY_MAX_TURNS` luot gan nhat vao prompt.

Mot "turn" gom:

- user question
- assistant answer

Mac dinh:

```text
AI_CHAT_HISTORY_MAX_TURNS=10
```

Redis co the luu nhieu hon trong TTL, nhung prompt chi lay window gan nhat.

### 13.3. Redis history structure

Redis key:

```text
chat:{session_id}
```

Value luu dang JSON array:

```json
[
  {
    "role": "user",
    "content": "Toi muon tim sach ve ky nang giao tiep",
    "created_at": "2026-05-29T10:00:00+07:00"
  },
  {
    "role": "assistant",
    "content": "Ban co the tham khao...",
    "created_at": "2026-05-29T10:00:03+07:00"
  }
]
```

Quy tac:

- Chi append vao Redis khi Gemini chat tra ve cau tra loi that.
- Khong append fallback do embedding/chat API loi.
- Co the append cau "khong tim thay thong tin phu hop" neu day la cau tra loi that tu Gemini duoc sinh theo prompt low-context.

## 14. Danh gia ket qua MVP

### 14.1. Metric tu dong

`ChatEvaluationService` tinh cac truong:

- `groundedness_score`
- `relevance_score`
- `has_hallucination_risk`
- `verdict`
- `risk_flags`

Evaluation MVP chay sync trong request. Ly do: implementation chi la string matching va regex scan, muc tieu duoi 10ms. Khi nang cap sang LLM-as-judge hoac evaluator ton token, phai chuyen sang queue va response se tra `meta.evaluation.status = pending`.

### 14.2. Groundedness MVP

Dung rule-based matching:

1. Trich entity quan trong tu answer:
   - ten sach trong retrieved books
   - tac gia
   - gia tien
   - nam xuat ban
   - so trang neu co
2. Neu answer nhac ten sach/tac gia/gia co trong context, cong diem.
3. Neu answer nhac thong tin cu the khong co trong context, tru diem va them risk flag.

Nguon context de doi chieu khong chi la text prompt thuan. `ChatEvaluationService` phai nhan structured facts da fetch tu MySQL cho top K:

- `book_id`
- `name`
- `author_names`
- `selling_price`
- `average_rating`
- `review_count`
- `available_stock`
- `publication_year`
- `num_pages`

Quy tac: so lieu trong answer chi bi coi la risk neu khong khop voi bat ky structured fact nao cua retrieved books. Vi du answer noi `150.000 dong` va `selling_price` cua mot retrieved book la `150000.00` thi khong flag hallucination risk, du text context khong chua dung chuoi `150.000`.

Chap nhan string matching don gian o MVP:

- Normalize lowercase.
- `ChatEvaluationService::normalizeText()` dung accent-folding (`VietnameseAccentFolder`) de match cau Gemini co dau voi phrase/rule khong dau. Can test false-positive neu hai cum tu khac nghia trung nhau sau fold.
- Gom whitespace.
- So sanh contains.

### 14.3. Hallucination risk MVP

Flag `has_hallucination_risk = true` neu answer chua claim cu the ma structured facts khong co. Regex chi dung de tim candidate claim, khong duoc tu no quyet dinh hallucination.

- Gia tien: `120.000`, `120000`, `120k`, `VNĐ`, `dong`.
- Nam: so 4 chu so trong khoang hop ly.
- So trang: `trang`, `pages`.
- ISBN.
- Ty le phan tram.

Quy trinh de giam false positive:

1. Regex tim candidate claim trong answer.
2. Chuan hoa candidate claim ve dang so/co cau truc, vi du `150.000 dong` -> `150000`.
3. Doi chieu voi structured facts cua retrieved books da fetch tu MySQL.
4. Chi them risk flag neu candidate claim khong khop facts nao.
5. Khong flag chi vi text prompt khong chua cung mot dinh dang chuoi.

Day la canh bao, khong chan response.

### 14.4. Relevance MVP

Tinh don gian:

- Neu retrieval matched va answer khong rong: diem mac dinh 0.7.
- Neu answer co it nhat mot entity tu retrieved context: tang diem.
- Neu retrieval khong matched va answer noi khong tim thay: diem cao.
- Neu retrieval khong matched nhung answer van goi y sach cu the: diem thap va `verdict = warning` hoac `fail`.

### 14.5. Verdict

Quy tac de xuat:

| Dieu kien | Verdict |
| --- | --- |
| `has_hallucination_risk = false` va groundedness >= 0.7 | `pass` |
| Co risk nhe hoac groundedness 0.4-0.69 | `warning` |
| Retrieval thap nhung answer bia thong tin cu the | `fail` |

## 15. Trinh tu trien khai theo lat nho

### Lat 1: Nen tang config, route skeleton va session contract

Muc tieu: tao khung chatbot chua goi Gemini that, dam bao API contract va session flow ro rang.

Pham vi:

- Tao `config/ai.php`.
- Them `.env.example` neu du an co file nay.
- Tao `ChatRequest` validate `session_id` UUID va `question`.
- Tao route `POST /api/v1/ai/chat`.
- Tao `ChatController` va response stub co cau truc JSON dung contract.
- Them rate limiter `ai-chat`.
- Document frontend: guest sinh UUID v4 luu `localStorage`.
- Test request validation va rate limit.

Ket qua demo:

- Goi API voi `session_id` hop le nhan response JSON stub.
- Goi thieu `session_id` hoac question qua dai nhan 422.

Chua lam:

- Chua goi Gemini.
- Chua goi Meilisearch.
- Chua luu DB.

### Lat 2: Database log va Redis history

Muc tieu: moi request chat co the luu history va log message.

Pham vi:

- Tao migration/model `AiChatMessage`.
- Tao `ChatHistoryStore`.
- Redis key: `chat:{session_id}`.
- Redis value la JSON array gom object `{role, content, created_at}`.
- TTL sliding: refresh TTL sau moi interaction.
- Prompt history chi dung toi da `AI_CHAT_HISTORY_MAX_TURNS`.
- Log message voi `model_version`, `latency_ms`, `retrieval_strategy = none`.
- Test Redis history append, TTL refresh va DB log.

Ket qua demo:

- Chat stub nhieu lan cung `session_id` thi Redis co history lien tuc.
- Reload frontend van giu context neu gui lai cung `session_id`.

### Lat 3: GeminiClient va fallback loi

Muc tieu: co client goi Gemini chat/embedding voi timeout, retry va fallback.

Pham vi:

- Tao `GeminiClient`.
- Method `embedText(string $text): array` — goi `embedContent` voi `output_dimensionality` tu `config('ai.rag.embedding_dimensions')`.
- Method `embedTexts(array $texts): array` — goi `batchEmbedContents`, moi item truyen cung model va `output_dimensionality`; response phai giu dung thu tu input.
- Method `generateAnswer(array $payload): GeminiChatResult`.
- Timeout va retry theo config.
- Bat timeout, rate-limit, 5xx.
- Log token usage va latency neu co.
- Test bang fake HTTP response.

Ket qua demo:

- Service co the goi fake Gemini va parse cau tra loi.
- Service co the goi fake Gemini batch embedding va map dung vector ve tung input text.
- Khi fake timeout, API tra fallback message, khong 500.

### Lat 4: RAG document text va Meilisearch vector config

Muc tieu: dinh nghia cach tao vector document cho sach va cau hinh Meilisearch embedder.

Pham vi:

- Tao `BookRagDocumentFactory`.
- Tao embedding text theo template o muc 7.
- Bo gia/rating/ton kho khoi embedding text.
- Them metadata dong vao document payload de dung trong prompt.
- Tao command `ai:meilisearch-configure`.
- Command cau hinh user-provided embedder dimension 768.
- Test factory tao text dung, khong include price/rating/stock trong embedding text.

Ket qua demo:

- Mot sach co document payload day du.
- Meilisearch index da co embedder cau hinh dung.

### Lat 5: Sync vector documents

Muc tieu: dua vector cua sach vao Meilisearch.

Pham vi:

- Tao command `ai:sync-book-rag-documents`.
- Tao job `SyncBookRagDocument` de sync mot sach.
- Tao `QueueBookRagSyncService` va `SyncPendingBookRagDocuments` de gom pending `book_id` theo batch.
- Lay sach active va relation can thiet: detail, authors, categories, publisher, inventories.
- Ho tro `BookRagSyncService::syncMany(array $bookIds)` de load nhieu sach, build embedding texts, goi Gemini `batchEmbedContents`, map vector ve tung book theo thu tu, roi upsert documents vao Meilisearch.
- Goi Gemini embedding cho embedding text (model `gemini-embedding-2`, `output_dimensionality` = 768). Single-book path dung `embedContent`; multi-book path dung `batchEmbedContents`.
- Ghi `_vectors.{embedder_name}` vao Meilisearch.
- Ho tro option `--book-id=` de sync mot sach.
- Ho tro option `--all`, `--pending`, `--from-id=`, `--limit=`, `--missing-vectors`, `--dry-run`.
- Ho tro chunking sach voi `AI_RAG_SYNC_BATCH_SIZE` va chunking embedding request voi `AI_RAG_EMBED_BATCH_SIZE`.
- Observer khong dispatch 1 job Gemini cho tung sach; observer chi enqueue `book_id` vao pending set.
- Batch worker lay pending set theo lo, sleep giua cac lo theo `AI_RAG_SYNC_BATCH_SLEEP_MS`.
- `SyncPendingBookRagDocuments` nen dung `ShouldBeUniqueUntilProcessing` hoac co co che dispatch batch tiep theo sau khi unique lock da release. Khong de pending set con ids nhung khong con worker tiep tuc xu ly.
- Dispatch incremental sync qua queue rieng `ai-rag-sync` khi `Book`, `BookDetail`, tac gia, danh muc hoac nha xuat ban anh huong den embedding text.
- Them schedule sync bo tro hang dem neu can chong miss event.
- Log loi tung sach nhung khong lam dung ca batch neu co the tiep tuc. Rieng HTTP 429/rate-limit phai dung som hoac requeue failed/chua xu ly de tranh fail hang loat va mat ids.
- Test command voi fake Gemini/Meilisearch client.

Ket qua demo:

- Chay command sync duoc mot sach vao Meilisearch voi vector 768 chieu.
- Chay full sync 1.800 sach khong tao 1.800 request Gemini rieng le; voi `AI_RAG_EMBED_BATCH_SIZE=25`, chi can khoang 72 request embedding truoc khi tinh retry.
- Khi cap nhat mot NXB co nhieu sach, he thong chi tao pending ids va batch worker xu ly theo lo, moi lo dung batch embedding, khong tao dot bien hang tram request Gemini cung luc.

Ghi chu:

- Neu model embedding thay doi, can chay lai full sync.
- Batch embedding giam request/day nhung khong giam token/minute. Neu dung free tier, can chon `AI_RAG_EMBED_BATCH_SIZE` va `AI_RAG_SYNC_BATCH_SLEEP_MS` theo Rate Limit page.

### Lat 6: BookRagRetriever voi hybrid search

Muc tieu: truy xuat top K ngu canh sach tu cau hoi, tra ve ket qua co cau truc cho Lat 7 (`PromptBuilder`, log `retrieval_*`).

#### 6.1. Trang thai du lieu hien tai (vector coverage chua day du)

Lat 5 co the bi dung som do Gemini HTTP 429 (free tier RPM/TPM/RPD). Day **khong chan** Lat 6:

| Kha nang retrieval | Can vector? | Hoat dong khi coverage thap |
| --- | --- | --- |
| Exact title / tac gia / SKU | Khong (keyword) | Van hoat dong — sach da index qua Scout |
| Mo ta co keyword ro | Khong (keyword) | Van hoat dong |
| Cau hoi ngu nghia ("sach ve ky nang giao tiep") | Co (vector/hybrid) | Chi tot trong pham vi sach da co `_vectors` |

Quy tac nghiep vu:

- `matched = false` **khong** duoc hieu la "Bookify chac chan khong ban sach do". Chi co nghia la retrieval khong dat nguong voi du lieu hien co.
- Lat 7 khi `matched = false` dung `AI_CHAT_NO_CONTEXT_MESSAGE` / prompt flag `no_relevant_context` voi wording **"du lieu hien co"**, khong noi chac chan la khong ban.
- Unit test Lat 6 dung mock Meilisearch/Gemini — **khong phu thuoc** vao coverage that.
- Manual demo semantic chi co gia tri sau khi ghi nhan coverage va uu tien sync vector cho nhom sach test.

**Song song voi code Lat 6**, tiep tuc lap day vector (khong chay lai `--all` khi dang bi 429):

```bash
# Cau hinh an toan free tier (xem muc 20)
php artisan ai:sync-book-rag-documents --missing-vectors --limit=100
# Neu 429: tang AI_RAG_SYNC_BATCH_SLEEP_MS, giam --limit, doi reset quota roi resume
php artisan ai:sync-book-rag-documents --missing-vectors --from-id=<last_id> --limit=100
php artisan ai:sync-book-rag-documents --pending
```

Truoc manual demo, ghi nhan coverage (tinker hoac helper Lat 6):

- `active_books`: so sach `is_active = true` trong MySQL.
- `vectorized_books`: so active book co `_vectors.{embedder_name}` tren Meilisearch.
- `coverage_pct = vectorized_books / active_books * 100`.

#### 6.2. Phu thuoc (da co tu Lat 1–5)

| Thanh phan | Vai tro |
| --- | --- |
| `GeminiClient::embedText()` | Embed cau hoi, 768 chieu |
| `MeilisearchRagDocumentWriter` | Pattern `Meilisearch\Client`, index `books` |
| `BookRagDocumentFactory` | Document shape da sync |
| `config('ai.rag.*')` | `top_k`, `min_score`, `rrf_min_score`, `hybrid_semantic_ratio` |

Chua lam o Lat 6: wire vao `ChatbotService`, fetch MySQL gia/ton kho (Lat 7), populate API `sources`.

#### 6.3. Thanh phan moi

| File | Vai tro |
| --- | --- |
| `App\Services\Ai\BookRagRetriever` | Orchestrate embed + search + threshold |
| `App\Services\Ai\Dto\BookRagRetrievalResult` | `matched`, `topScore`, `documents`, `strategy`, latency |
| `App\Services\Ai\Dto\BookRagRetrievedDocument` | `bookId`, `score`, `name`, `slug`, `raw` |
| `App\Services\Ai\Support\ReciprocalRankFusionMerger` | Merge rank lists khi RRF fallback |
| `App\Services\Ai\RagVectorCoverageReporter` (optional) | Doc/count active vs vectorized books cho demo ops |

`BookRagRetrievalResult` contract:

```php
final readonly class BookRagRetrievalResult
{
    /** @param list<BookRagRetrievedDocument> $documents */
    public function __construct(
        public bool $matched,
        public ?float $topScore,
        public array $documents,
        public string $strategy, // hybrid | rrf | none
        public ?int $embeddingLatencyMs = null,
        public ?int $searchLatencyMs = null,
    ) {}
}
```

Public API:

```php
public function retrieve(string $question): BookRagRetrievalResult;
```

#### 6.4. Luong xu ly

```mermaid
flowchart TD
    A["retrieve(question)"] --> B["embedText(question)"]
    B --> C{"Hybrid API kha dung?"}
    C -- "Co" --> D["hybridSearch + filter is_active"]
    C -- "Unsupported" --> E["keywordSearch + vectorSearch"]
    E --> F["RRF merge"]
    D --> G["mapHits"]
    F --> G
    G --> H{"Co hit?"}
    H -- "Khong" --> I["matched=false, strategy thuc te hoac none"]
    H -- "Co" --> J{"Dat nguong?"}
    J -- "RRF + keyword top1 cao" --> K["matched=true (boost rule)"]
    J -- "Score >= threshold" --> K
    J -- "Score < threshold" --> L["matched=false, giu documents + strategy"]
    K --> M["BookRagRetrievalResult"]
    L --> M
    I --> M
```

**Buoc 1 — Embed cau hoi**

- Goi `GeminiClient::embedText($question)`.
- Embedding loi: rethrow `GeminiClientException` — Lat 7 xu ly `embedding_failed`, fail fast (muc 12.1).

**Buoc 2 — Hybrid search (uu tien)**

```php
$index->search($question, [
    'hybrid' => [
        'semanticRatio' => config('ai.rag.hybrid_semantic_ratio'),
        'embedder' => config('ai.rag.embedder_name'),
    ],
    'vector' => $embeddingVector,
    'filter' => 'is_active = true',
    'limit' => config('ai.rag.top_k'),
    'showRankingScore' => true,
]);
```

- `strategy = 'hybrid'`.
- Score lay tu `_rankingScore`.
- Khi coverage thap, hybrid van tra hit keyword — exact title khong phu thuoc vector.

**Buoc 3 — RRF fallback (chi khi hybrid unsupported)**

- Chi kich hoat khi loi la hybrid unsupported (unknown field `hybrid`, invalid hybrid param).
- **Khong** fallback RRF cho 401/403, index khong ton tai, filter/embedder invalid — log `error` va degrade `strategy = none`.
- Keyword search + vector search rieng, merge:

```text
rrf_score(book_id) = sum(1 / (rank + 60))
```

- Rank bat dau tu 1. Sach co trong ca hai list duoc cong hai lan.
- `strategy = 'rrf'`.
- Vector list co the rong khi chua sync du — RRF van chay voi keyword list; ket qua phu thuoc keyword.

**Buoc 4 — Nguong `matched`**

| Strategy | Dieu kien `matched = true` |
| --- | --- |
| `hybrid` | Co hit **va** `topScore >= AI_RAG_MIN_SCORE` (0.80) |
| `rrf` | Co hit **va** (`topScore >= AI_RAG_RRF_MIN_SCORE` (0.016) **hoac** keyword top-1 boost — xem duoi) |

**Keyword top-1 boost (quan trong khi coverage thap / RRF fallback):**

- Neu keyword search tra top 1 voi `_rankingScore >= 0.70` (config optional `ai.rag.keyword_top1_min_score`), dat `matched = true` du RRF score thap.
- Muc dich: exact title "Dac Nhan Tam" van match khi vector chua co hoac RRF top-1 chi dat `0.0164`.

**Khi co hit nhung duoi nguong:**

- Van tra `documents`, `top_score`, `strategy` thuc te (`hybrid`/`rrf`).
- Chi dat `matched = false`.
- **Khong** doi thanh `strategy = none`.

**Khi Meilisearch loi (connection, auth, index):**

- Log `warning` voi question truncated, latency, exception class.
- Tra `matched=false`, `documents=[]`, `strategy=none` — degrade, khong throw (muc 12.2).

#### 6.5. Pham vi implement

- Tao cac file muc 6.3.
- Inject `?Meilisearch\Client` de test (giong `MeilisearchRagDocumentWriter`).
- `ReciprocalRankFusionMerger` tach rieng, co unit test doc lap.
- `RagVectorCoverageReporter` (optional): method `report(): array{active_books, vectorized_books, coverage_pct}` — doc MySQL count + sample Meilisearch hoac dry-run `--missing-vectors` count.
- Khong sua `ChatbotService` o lat nay.

#### 6.6. Cau hinh lien quan

| Key | Mac dinh | Ghi chu |
| --- | --- | --- |
| `AI_RAG_TOP_K` | 5 | `limit` search |
| `AI_RAG_MIN_SCORE` | 0.80 | hybrid threshold; tune theo smoke test full-vector |
| `AI_RAG_RRF_MIN_SCORE` | **0.016** | top-1 mot list = `1/61 ≈ 0.0164` |
| `AI_RAG_HYBRID_SEMANTIC_RATIO` | 0.6 | tang neu semantic quan trong hon |
| `AI_RAG_EMBEDDER_NAME` | `gemini_embedding_2_768` | hybrid + vector leg |
| `AI_CHAT_NO_CONTEXT_MESSAGE` | "... du lieu hien co." | Lat 7 dung khi `matched=false` |

Optional them khi implement (khong bat buoc MVP):

```php
'keyword_top1_min_score' => (float) env('AI_RAG_KEYWORD_TOP1_MIN_SCORE', 0.70),
'rrf_k' => (int) env('AI_RAG_RRF_K', 60),
```

**Luu y:** `config/ai.php` hien tai co the van mac dinh `rrf_min_score = 0.02`; can doi ve `0.016` khi implement Lat 6 de khop doc.

#### 6.7. Kiem thu

**Unit test (mock — khong can vector that):**

| Case | Ky vong |
| --- | --- |
| Hybrid hit score 0.82 | `matched=true`, `strategy=hybrid` |
| Hybrid hit score 0.40 | `matched=false`, van co `documents` |
| Exact title (mock keyword-heavy) | `matched=true` |
| Out of domain / empty hits | `matched=false` |
| Filter `is_active = true` | assert request params |
| Hybrid unsupported → RRF | `strategy=rrf`, merge dung rank |
| RRF score 0.015 | `matched=false` |
| RRF score 0.017 hoac keyword top-1 boost | `matched=true` |
| Vector leg rong (mock) | keyword-only RRF van tra hit |
| Meilisearch exception | `strategy=none`, khong throw |
| Embedding fail | `GeminiClientException` bubble |

File: `tests/Unit/Ai/BookRagRetrieverTest.php`, `tests/Unit/Ai/ReciprocalRankFusionMergerTest.php`.

**Manual demo — tach 2 tier theo coverage:**

| Tier | Dieu kien | Cau hoi mau | Ky vong |
| --- | --- | --- | --- |
| A — keyword | Luon chay duoc | `"Dac Nhan Tam"`, `"Sach cua tac gia X"` | `matched=true` neu sach active da index |
| B — semantic | Can vector cho sach lien quan | `"sach ve ky nang giao tiep"` | `matched=true` chi khi nhom sach test da sync vector |

Moi lan demo ghi ro: `active_books`, `vectorized_books`, `coverage_pct`.

#### 6.8. Tieu chi nghiem thu Lat 6

- [ ] `BookRagRetriever::retrieve()` tra `BookRagRetrievalResult` dung contract.
- [ ] Hybrid: dung `semanticRatio`, `embedder`, `filter is_active = true`, `limit = top_k`.
- [ ] RRF chi khi hybrid unsupported; loi auth/index → `strategy=none`.
- [ ] Hit duoi nguong: giu `documents` + `strategy`, chi `matched=false`.
- [ ] Keyword top-1 boost hoac `rrf_min_score=0.016` — exact title khong bi fail khi coverage thap.
- [ ] Meilisearch loi degrade, embedding loi fail fast.
- [ ] `php artisan test --filter=BookRagRetriever` pass.

#### 6.9. Ket qua demo

- Tier A: `"Dac Nhan Tam"` tim dung sach bang keyword/hybrid (khong can 100% vector).
- Tier B: `"sach ve ky nang giao tiep"` chi pass semantic khi sach lien quan da co vector.
- `"Bookify co ban dien thoai khong?"` → `matched=false`.
- Bao cao coverage tai thoi diem test.

#### 6.10. Ghi chu tich hop Lat 7

`ChatbotService` se goi `BookRagRetriever`, chi inject context khi `matched=true`, fetch MySQL top K, log `retrieval_strategy` / `retrieval_matched` / `retrieved_books`. Lat 6 khong populate API `sources`.

### Lat 7: PromptBuilder va luong chat that

Muc tieu: noi Redis history + retrieval + Gemini thanh pipeline chat hoan chinh.

Pham vi:

- Tao `PromptBuilder`.
- Build prompt theo system rules.
- Dua retrieved context vao prompt khi `matched = true`.
- Dua `no_relevant_context=true` khi `matched = false`; system prompt / fallback dung wording "du lieu hien co", khong khang dinh kho sach khong ban sach do.
- Sau retrieval, fetch lai MySQL cho top K `book_id` de lay gia, rating va ton kho hien tai truoc khi build context.
- Chi dua sliding window history.
- `ChatbotService` orchestrate:
  1. load history
  2. retrieve context
  3. fetch current book context from DB
  4. build prompt
  5. call Gemini
  6. save Redis history only when Gemini returns a real answer
  7. save message DB
- Test high-match, low-match va Gemini failure.

Ket qua demo:

- Chatbot tra loi dua tren context sach.
- Khi khong co context, chatbot noi khong tim thay thay vi bia sach.
- Khi Gemini loi, fallback tra ve user nhung khong duoc append vao Redis history.

### Lat 8: Evaluation tu dong

Muc tieu: moi cau tra loi co ket qua danh gia co ban.

Pham vi:

- Tao migration/model `AiChatEvaluation`.
- Tao `ChatEvaluationService`.
- Implement groundedness string matching.
- Implement hallucination risk regex.
- Implement relevance/verdict rules.
- Chay evaluation sync trong request va luu evaluation sau message.
- Response tra `meta.evaluation`.
- Test cac case:
  - answer co ten sach trong context -> pass
  - answer co gia khong nam trong context -> warning/fail
  - low context nhung answer bia sach -> fail
  - low context va answer noi khong tim thay -> pass

Ket qua demo:

- Moi message co evaluation record va verdict.

### Lat 9: Feedback cua nguoi dung

Muc tieu: nguoi dung danh gia cau tra loi.

Pham vi:

- Tao migration/model `AiChatFeedback`.
- Tao feedback route.
- Validate `rating` (`up`, `down`) va `session_id` cho guest.
- Cho guest feedback bang `session_id`.
- Cho member feedback member message neu `account_id` khop message.
- Ho tro guest chat truoc, dang nhap sau, feedback message cu bang session_id cu.
- Ho tro member feedback tren thiet bi khac bang account_id khop.
- Cho phep update feedback cu thay vi tao trung lap.
- Khong nhan `reason`/`comment` trong MVP.
- Test `up`/`down`, ownership va validation.

Ket qua demo:

- Frontend co the gui thumbs up/down cho mot `message_id`.

### Lat 10: Frontend integration co ban

Muc tieu: noi UI chatbot hien co voi API that.

Pham vi:

- Frontend tao/lay `bookify_chat_session_id` trong `localStorage`.
- Gui `session_id` va `question` den API.
- Hien thi loading, answer va error fallback.
- Hien thi sources neu backend tra ve.
- Hien thi nut thumbs up/down khi co `message_id`.
- Khong hien debug metric cho user cuoi.

Ket qua demo:

- Khach vang lai chat duoc sau reload van giu context.
- Member chat duoc va feedback duoc.

### Lat 11: Van hanh va cleanup

Muc tieu: chuan bi chay on dinh.

Pham vi:

- Them command/schedule neu can sync vector dinh ky.
- Ghi log latency, token usage, retrieval miss rate.
- Them command prune chat logs neu can retention.
- Cap nhat `backend/docs/database_schema.md` sau migration.
- Cap nhat `.cursor/rules/ai-chatbot-rag.mdc` neu quyet dinh ky thuat thay doi.

Ket qua demo:

- Co huong dan config, sync index va theo doi loi.

## 16. Kiem thu tong hop

### 16.1. Feature tests

- `POST /api/v1/ai/chat` validate input.
- Guest co `session_id` hop le chat duoc.
- Member chat duoc va gan `account_id`.
- Rate limit guest/member hoat dong.
- Gemini timeout tra fallback.
- Gemini timeout khong append fallback vao Redis history.
- Meilisearch loi degrade gracefully.
- Low similarity khong bia thong tin.
- High similarity inject context.
- Top K retrieval fetch lai MySQL truoc khi build context gia/ton kho.
- RRF fallback merge hoat dong neu hybrid API khong kha dung.
- Khi vector coverage chua day du, low semantic match khong duoc dien giai thanh "khong co sach"; response dung wording an toan ve du lieu hien co.
- Hybrid unsupported moi fallback RRF; loi auth/index/filter/embedder degrade va log, khong che loi cau hinh bang RRF.
- Redis history sliding window.
- DB luu `model_version`.
- Feedback luu/update dung theo rule `session_id` khop hoac `account_id` khop.

### 16.2. Unit tests

- `BookRagDocumentFactory` tao embedding text dung.
- `PromptBuilder` cat history theo max turns.
- `ChatEvaluationService` flag risk dung.
- `BookRagRetriever` check threshold dung.
- `BookRagRetriever` merge RRF dung rank khi fallback.
- `BookRagRetriever` keyword top-1 boost hoac `rrf_min_score=0.016` cho exact title khi coverage thap.
- `BookRagRetriever` giu `documents` + `strategy` khi hit duoi nguong, chi `matched=false`.
- `BookRagRetriever` RRF threshold xu ly dung case top 1 chi nam trong mot list (`1 / 61 = 0.0164`).
- `BookRagRetriever` giu `strategy`, `top_score`, `documents` khi co hit nhung duoi nguong.
- `BookRagRetriever` phan biet hybrid unsupported voi loi cau hinh/quyen truy cap.
- `GeminiClient` parse response, token usage, va `output_dimensionality` embedding dung.
- `GeminiClient` batch embedding goi `batchEmbedContents`, validate count/dimension, va giu dung thu tu vector theo input.
- `QueueBookRagSyncService` deduplicate pending book ids va batch theo config.
- `BookRagSyncService::syncMany` gom nhieu sach vao it request Gemini theo `AI_RAG_EMBED_BATCH_SIZE` va upsert dung vector cho tung book.
- `SyncPendingBookRagDocuments` khong goi Gemini vuot batch size trong mot lo va khong lam mat pending ids khi tung book/batch fail.
- Command full sync dung som hoac requeue khi Gemini tra 429, co test khong fail hang loat sau rate limit.
- `ChatEvaluationService` khong flag gia/nam/so trang khi claim khop structured facts tu MySQL.

### 16.3. Manual test cases

| Cau hoi | Ky vong |
| --- | --- |
| "Toi muon sach ve ky nang giao tiep" | Goi y sach lien quan, co ly do ngan |
| "Sach Dac Nhan Tam gia bao nhieu?" | Tim exact title va tra gia neu co trong context |
| "Co sach nao cua tac gia X khong?" | Tim theo tac gia neu co |
| "Bookify co ban dien thoai khong?" | Noi khong tim thay/pham vi khong phu hop |
| "Sach nay con hang khong?" | Chi tra loi neu context co ton kho |
| "Don hang cua toi dau roi?" | MVP chua ho tro, huong dan nguoi dung vao trang don hang |

## 17. Tieu chi nghiem thu MVP

- API chat hoat dong cho guest va member.
- Guest reload trang van giu context neu frontend gui lai cung `session_id`.
- Chatbot khong tra loi bia khi similarity thap.
- Chatbot co fallback than thien khi Gemini loi.
- Fallback do Gemini loi khong duoc luu vao Redis history.
- Meilisearch loi khong lam API 500.
- Moi answer co message log va evaluation log.
- Response co `model_version`, retrieval meta va evaluation meta.
- Response cho phep `message_id = null` va frontend an feedback khi khong co message id.
- Feedback cua user duoc luu.
- Feedback authorization tach theo loai message: guest message can `session_id` khop; member message can `account_id` khop.
- Rate limiting ngan spam Gemini API.
- History dua vao prompt bi gioi han boi `AI_CHAT_HISTORY_MAX_TURNS`.
- Vector document dung `_vectors.{embedder_name}` voi embedder user-provided (`gemini_embedding_2_768`, dimension 768).
- `GeminiClient` truyen `output_dimensionality` khi embed single va batch.
- Text embedding khong include gia/rating/ton kho.
- Context prompt luon lay gia/rating/ton kho moi tu MySQL cho top K.
- `rag_embedding_text` khong nam trong `searchableAttributes`.
- Sync vector co duong incremental khi du lieu sach/tac gia/danh muc/nha xuat ban thay doi.
- Incremental sync quan he lon phai qua pending set va batch worker, khong dispatch hang tram job Gemini cung luc.
- Full sync nhieu sach phai dung batch embedding de khong vuot request/day khi dataset co khoang 1.800 sach.
- Evaluation doi chieu regex candidate voi structured facts tu MySQL truoc khi flag hallucination risk.

## 18. File/thanh phan du kien thay doi

| Lat | File/thanh phan chinh |
| --- | --- |
| 1 | `config/ai.php`, `routes/api.php`, `ChatRequest`, `ChatController`, rate limiter |
| 2 | migration/model `AiChatMessage`, `ChatHistoryStore` |
| 3 | `GeminiClient`, exception/fallback classes neu can |
| 4 | `BookRagDocumentFactory`, command configure Meilisearch |
| 5 | command/job sync RAG documents, pending sync set, batch worker, queue `ai-rag-sync` |
| 6 | `BookRagRetriever`, DTO retrieval, `ReciprocalRankFusionMerger`, optional `RagVectorCoverageReporter` |
| 7 | `PromptBuilder`, `ChatbotService`, `ChatMessageResource` |
| 8 | migration/model `AiChatEvaluation`, `ChatEvaluationService` |
| 9 | migration/model `AiChatFeedback`, `ChatFeedbackController`, `ChatFeedbackService` |
| 10 | `frontend/src/components/UI/Chatbot.jsx`, service API frontend |
| 11 | `backend/docs/database_schema.md`, schedule/retention docs |

## 19. Rui ro va cach xu ly

| Rui ro | Anh huong | Xu ly |
| --- | --- | --- |
| Guest session_id khong on dinh | Mat context sau reload | Frontend sinh UUID v4 va luu `localStorage` |
| User doan/doi session_id cua nguoi khac | Co the doc context neu API expose history | MVP khong co API doc history rieng; chat request chi dung session_id de noi context hien tai |
| Gemini API loi/cham | Tra loi cham hoac 500 | Timeout, retry, fallback message |
| Chi phi Gemini tang | Ton chi phi API | Rate limit, max question length, max history turns |
| Chat quality flash-lite khong dat | Tra loi so san/khong tu nhien | Doi `GEMINI_CHAT_MODEL=gemini-2.5-flash`; danh gia lai latency/chi phi |
| Vector dimension sai | Search loi hoac ket qua sai | Config `output_dimensionality` 768, test vector length, re-index khi doi model/embedder |
| Vector coverage chua day du | Semantic query miss sach phu hop, `matched = false` co the bi hieu sai | Cho phep trien khai Lat 6 nhung log/bao cao coverage, uu tien `--missing-vectors`, va Lat 7 dung wording "du lieu hien co" khi khong matched |
| Embedding text chua tot | Retrieval kem | Template ro rang, co manual test query, iterate sau MVP |
| Pure vector kem exact match | Tim sai ten sach | Dung hybrid search tu dau |
| Evaluation rule-based co false positive | Canh bao sai | Regex chi tim candidate claim; doi chieu structured facts tu MySQL truoc khi flag risk |
| Retrieved context qua lon | Tang token/cost | Luu DB chi book_id/score, prompt cat description/context |
| Du lieu gia/ton kho cu trong Meilisearch | Tra loi sai | Bat buoc fetch lai MySQL cho top K truoc khi build context |
| Fallback Gemini bi luu vao history | Lam nhieu context cac luot sau | Chi luu Redis khi co cau tra loi that tu Gemini |
| Feedback bi chan sai khi doi thiet bi/dang nhap sau | User khong danh gia duoc cau tra loi hop le | Guest message cho phep `session_id` khop, member message cho phep `account_id` khop |
| Meilisearch khong ho tro hybrid API | Retrieval khong chay duoc | Chi fallback RRF khi loi la hybrid unsupported; loi auth/index/filter/embedder degrade va log de khong che loi cau hinh |
| RRF threshold qua cao | Exact title hoac vector-only top 1 bi `matched = false` khi fallback | Ha `AI_RAG_RRF_MIN_SCORE` ve khoang `0.016` hoac them rule keyword top 1 exact/high score |
| Full sync tao 1 request embedding cho moi sach | Dataset 1.800 sach vuot free tier RPD 1K va fail 429 hang loat | Dung `batchEmbedContents` voi `AI_RAG_EMBED_BATCH_SIZE`, them `--limit`/`--from-id`/`--missing-vectors`, va dung som khi 429 |
| Batch embedding qua lon | Giam RPD nhung van vuot TPM/RPM, bi 429 | Bat dau `AI_RAG_EMBED_BATCH_SIZE=25`, tang sleep theo Rate Limit page, theo doi token/request |
| Pending worker mat id khi sync fail | Sach fail khong duoc retry, index thieu vector | Re-add failed ids vao pending hoac dead-letter; khong pop vinh vien khi loi tam thoi |
| Unique lock chan batch worker tiep theo | Pending set con id nhung khong co job tiep tuc xu ly | Dung `ShouldBeUniqueUntilProcessing` hoac dispatch worker tiep theo sau khi lock release |
| Observer fanout khi doi NXB/danh muc/tac gia | Dot bien request Gemini, bi throttle hoac tang chi phi | Observer chi enqueue pending ids; batch worker xu ly theo `AI_RAG_SYNC_BATCH_SIZE`, dung batch embedding va sleep giua batch |
| Regex evaluation false positive | Cau tra loi dung bi gan warning | Regex chi tim candidate claim; risk chi flag sau khi doi chieu structured facts tu MySQL |

## 20. Lenh van hanh du kien

```bash
php artisan migrate
php artisan config:clear
php artisan scout:sync-index-settings
php artisan ai:meilisearch-configure
php artisan ai:sync-book-rag-documents --book-id=1
php artisan ai:sync-book-rag-documents --all --limit=500
php artisan ai:sync-book-rag-documents --all --from-id=501 --limit=500
php artisan ai:sync-book-rag-documents --missing-vectors --limit=500
php artisan ai:sync-book-rag-documents --pending
php artisan test --filter=Ai
```

Ghi chu:

- `scout:sync-index-settings` giu cau hinh search hien co cua index `books`.
- `ai:meilisearch-configure` chi quan ly phan vector embedder.
- `ai:sync-book-rag-documents` can `GEMINI_API_KEY` va Meilisearch dang chay.
- Với free tier Gemini Embedding 2, cau hinh an toan ban dau:

```env
AI_RAG_EMBED_BATCH_SIZE=25
AI_RAG_SYNC_BATCH_SIZE=25
AI_RAG_SYNC_BATCH_SLEEP_MS=30000
AI_RAG_SYNC_STOP_ON_429=true
AI_RAG_RRF_MIN_SCORE=0.016
```

- Neu Rate Limit page cho thay TPM con du, co the tang `AI_RAG_EMBED_BATCH_SIZE` len 50. Neu con gap 429, giam batch hoac tang sleep.
- Khong chay lai `--all` tu dau khi dang bi 429. Dung `--from-id`, `--limit`, `--missing-vectors` hoac `--pending` de resume/retry.
- **Truoc manual demo Lat 6/Lat 7:** uu tien `--missing-vectors --limit=...` cho nhom sach test semantic; ghi nhan `active_books`, `vectorized_books`, `coverage_pct`. Tier A (exact title) demo duoc ngay ca khi coverage thap; Tier B (semantic) chi danh gia sau khi sach lien quan da co vector.
- Lat 6 implement khong bi chan boi coverage; dat `AI_RAG_RRF_MIN_SCORE=0.016` de exact title khong fail khi RRF fallback.
