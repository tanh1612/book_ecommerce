# AI Chatbot - Scope And Quality Boundaries

Tai lieu nay danh gia hien trang chatbot Bookify va dat ra ranh gioi thiet ke cho mot he thong vua/nho voi nhiem vu duy nhat: tu van sach dua tren du lieu sach hien co.

Muc dich chinh: khi co su co trong tuong lai, doi ngu co the phan loai ro do la "gioi han thiet ke da chap nhan" hay "he thong dang lam chua tot".

Can cu da doc:

- `.cursor/rules/ai-chatbot-rag.mdc`
- `.cursor/rules/recommendation-system.mdc`
- `backend/docs/database_schema.md`
- `backend/docs/rag-chatbot-implementation-plan.md`
- `backend/docs/ai-chatbot-api.md`
- Code backend lien quan den `App\Services\Ai`, routes API, migrations AI chat, command/job/observer, config va tests AI

## 1. Ket luan ngan

Backend chatbot hien tai da vuot qua muc skeleton: co pipeline RAG, Gemini, Meilisearch hybrid/RRF, Redis history, exact-title/follow-up handling, log DB, evaluation rule-based, feedback, report/prune va test coverage kha rong. Kien truc backend nhin chung phu hop neu muc tieu la chatbot tu van sach co kiem soat hallucination.

Danh gia trong tai lieu nay chi tinh backend. Viec frontend co tao `session_id`, goi API, hien sources/feedback hay khong nam ngoai pham vi trach nhiem backend va khong duoc tinh la thieu sot cua BE.

Rui ro van hanh chinh cua BE khong nam o code path chat, ma nam o du lieu va moi truong: vector coverage, Meilisearch index/embedder config, Gemini quota/latency, Redis/cache store, queue worker va viec sync vector co thuc su chay hay khong.

## 2. Trang thai trien khai backend hien tai

### 2.1. Da trien khai o BE

| Hang muc | Trang thai | Bang chung trong code |
| --- | --- | --- |
| Chat API | Da co | `routes/api.php`, `ChatController`, `ChatRequest`, `ChatMessageResource` |
| Feedback API | Da co | `ChatFeedbackController`, `ChatFeedbackRequest`, `ChatFeedbackService`, `ChatFeedbackRating` |
| Rate limit guest/member | Da co | `AppServiceProvider::boot()` khai bao `ai-chat` va `ai-chat-feedback` |
| Orchestrator RAG | Da co | `ChatbotService` dieu phoi history, retrieval, prompt, Gemini, log, evaluation |
| Redis chat history | Da co | `ChatHistoryStore`, key `chat:{session_id}`, TTL/config sliding window |
| Redis last sources cho follow-up | Da co | `ChatContextStore`, key `chat:{session_id}:last_sources` |
| Follow-up resolver | Da co | `FollowUpQueryResolver`, tests `ChatFollowUpTest`, `FollowUpQueryResolverTest` |
| Exact title resolver | Da co | `ExactBookQueryResolver`, tests `ChatExactBookTest`, `ExactBookQueryResolverTest` |
| Gemini client | Da co | `GeminiClient` co chat, embed single, batch embed, timeout/retry/token usage |
| RAG retrieval | Da co | `BookRagRetriever`, hybrid search, RRF fallback, threshold, degrade khi search loi |
| RAG document factory | Da co | `BookRagDocumentFactory`, tach embedding text khoi gia/rating/stock dong |
| Load context tu MySQL | Da co | `RetrievedBookContextLoader` lay gia/rating/review/stock hien tai |
| Prompt guardrail | Da co | `PromptBuilder` chi dung retrieved context/history va no-context path |
| Deterministic intent guard | Da co + test theo nhom intent | `OutOfScopeIntentGuard` short-circuit order/payment/refund/private-account/non-book trong `ChatbotService`, test feature cho tung nhom cau hoi |
| Answer sources | Da co | `AnswerSourceSelector`, `BookMentionMatcher`, chi tra source sach duoc nhac trong answer |
| Message logging | Da co | Bang/model `ai_chat_messages` luu question, answer, retrieval, token, latency, error |
| Evaluation rule-based | Da co | `ChatEvaluationService`, `AiChatEvaluation`, risk flags gia/nam/so trang |
| User feedback | Da co | `AiChatFeedback`, upsert 1 feedback/message, auth theo guest/member |
| Vector sync command/job | Da co | `ai:sync-book-rag-documents`, `SyncPendingBookRagDocuments`, `SyncBookRagDocument` |
| Pending/retry/dead-letter sync | Da co | `QueueBookRagSyncService` claim batch, retry counts, dead letter, stale claim reclaim |
| Meilisearch vector config | Da co | `ConfigureMeilisearchVectorIndexCommand`, `MeilisearchRagIndexConfigurator` |
| Vector coverage command | Da co | `ai:rag:coverage` (text/`--json`) su dung `RagVectorCoverageReporter` de bao cao coverage van hanh |
| Observer incremental sync | Da co | `BookObserver`, `BookDetailObserver`, `AuthorObserver`, `CategoryObserver`, `PublisherObserver`, pivot models |
| Ops report | Da co | `ai:chatbot:report`, `ChatbotOperationsReportService` |
| Ops prune | Da co | `ai:chatbot:prune`, cascade evaluation/feedback qua FK |
| Backend tests | Da co kha rong | Feature/unit tests cho chat, RAG, exact, follow-up, feedback, sync, report, prune |

### 2.2. BE da dat theo scope tu van sach

Nhung yeu cau cot loi trong muc "chatbot chi tu van sach" da co implementation backend:

- Co API nhan cau hoi va tra answer + sources + retrieval/evaluation meta.
- Co co che guest/member nhung khong bat buoc login.
- Co gioi han cau hoi bang validation va rate limit.
- Co retrieval dua tren catalog sach, khong lay du lieu don hang/user private.
- Co prompt noi ro chi ho tro sach va mua sach tren Bookify.
- Co no-context path de tranh bia sach khi retrieval khong matched.
- Co lay lai gia, rating, stock tu MySQL truoc khi dua vao prompt.
- Co exact title/follow-up de xu ly hoi gia/stock theo sach cu the.
- Co log, evaluation, feedback de sau nay phan tich loi.

### 2.3. BE da xac thuc bang moi truong that

Trang thai xac thuc ngay 2026-06-02, gioi han o backend:

- `GEMINI_API_KEY` da duoc cau hinh va goi duoc Gemini that.
- Model chat dang dung: `gemini-2.5-flash-lite`.
- Model embedding dang dung: `gemini-embedding-2`, dimension `768`.
- Meilisearch index `books` da co embedder user-provided `gemini_embedding_2_768`.
- Searchable attributes cua Meilisearch chi gom `name`, `author_names`, `description`; `rag_embedding_text` khong nam trong searchable attributes.
- Active books da vector hoa: `active_books=1756`, `vectorized_books=1756`, `coverage_pct=100.0`.
- `php artisan ai:sync-book-rag-documents --missing-vectors --dry-run` bao `would sync 0 active book(s)`.
- Redis chat history store da roundtrip thanh cong voi `AI_CHAT_HISTORY_STORE=redis`.
- Queue `ai-rag-sync` khong co pending/processing job tai thoi diem kiem tra.
- Smoke test BE that pass cho exact title/price, semantic recommendation, non-book product va order/private-data question.
- Test backend AI pass voi `php artisan test --filter=Ai`.
- Command van hanh vector coverage co the chay truc tiep: `php artisan ai:rag:coverage` hoac `php artisan ai:rag:coverage --json`.
- `ai:chatbot:report` da chay duoc va ghi nhan matched rate/error/evaluation/latency/token.

Con no van hanh:

- Redis dead-letter cua RAG sync con 16 book id active tu cac lan sync/test cu, trong khi vector coverage hien tai da 100%. Day co kha nang la stale dead-letter, nen khong tinh la thieu code; can retry/clear co chu dich neu muon dashboard sach.
- Queue worker chi duoc kiem tra o trang thai khong co pending job. Neu deploy production, van can dam bao process worker/scheduler duoc chay lien tuc.

### 2.4. BE chua can trien khai them

Trong scope hien tai, backend khong can them cac phan sau:

- API doc lich su chat.
- API thao tac gio hang/don hang/thanh toan qua chatbot.
- Personalization dai han theo purchase/wishlist/profile.
- Streaming response.
- LLM-as-judge nang cao.
- Query expansion bang LLM.
- Admin dashboard rieng.
- Feedback reason/comment/category.
- Fine-tuning, reranker, multi-agent, vector DB moi.

## 3. Pham vi thiet ke duoc chap nhan

Chatbot chi nen lam cac viec sau:

- Goi y sach theo nhu cau doc: the loai, chu de, tac gia, muc dich doc, so sanh ngan giua cac sach da tim thay.
- Tra loi thong tin sach co trong catalog: ten sach, tac gia, the loai, mo ta, nha xuat ban, ngon ngu, hinh thuc, nam xuat ban, so trang neu co.
- Tra loi gia, rating, so danh gia va tinh trang con hang neu thong tin do duoc load lai tu MySQL tai thoi diem tra loi.
- Xu ly cau hoi exact title, vi du "Dac Nhan Tam gia bao nhieu?", "sach X con hang khong?".
- Xu ly follow-up ngan dua tren ngu canh vua goi y, vi du "cuon dau tien gia bao nhieu?", "cuon do con hang khong?".
- Noi ro "chua tim thay thong tin phu hop trong du lieu hien co" khi retrieval khong du tin cay.
- Cho user gui feedback `up/down` cho mot cau tra loi da co `message_id`.

Chatbot khong nen lam cac viec sau trong he vua/nho nay:

- Tra cuu don hang, thanh toan, hoan tien, dia chi giao hang hoac thong tin rieng tu cua tai khoan.
- Tu dong them vao gio hang, dat hang, huy don, xu ly refund hoac thay doi du lieu nguoi dung.
- Tu van ngoai mien sach, vi du dien thoai, thoi tiet, y te, phap ly, tai chinh.
- Dua ra khang dinh ve sach khong co trong catalog Bookify nhu the do la su that tuyet doi.
- Luu tri nho dai han ve so thich ca nhan ngoai session chat ngan han.
- Dung chatbot de thay the he thong recommendation feed hien co.

## 4. Hien trang theo thanh phan

| Thanh phan | Hien trang | Danh gia |
| --- | --- | --- |
| API chat | `POST /api/v1/ai/chat`, guest/member, throttle rieng, validate UUID v4 va length cau hoi | Phu hop MVP |
| API feedback | `POST /api/v1/ai/messages/{message}/feedback`, rating `up/down`, auth theo `session_id` hoac `account_id` | Phu hop MVP |
| Orchestrator | `ChatbotService` noi history, follow-up, exact title, RAG, prompt, Gemini, log, evaluation | Tot, hoi nhieu lop nhung co ly do |
| History | Redis key `chat:{session_id}`, TTL 24h, max 10 turns | Dung muc vua/nho |
| Last sources | Redis key `chat:{session_id}:last_sources` de xu ly follow-up | Can thiet cho UX chat sach |
| Retrieval | Gemini embedding + Meilisearch hybrid, fallback RRF khi hybrid unsupported | Phu hop neu Meilisearch da duoc cau hinh dung |
| Exact title | Scan MySQL books de match ten/slug khi hoi gia/ton kho | Chap nhan voi khoang 1.8k sach; khong phu hop neu catalog rat lon |
| Context | Top K load lai MySQL de lay gia/rating/stock moi | Rat can thiet, khong over-engineering |
| Prompt | Chi tra loi theo retrieved context/history, no-context safe path | Can thiet |
| Gemini client | Timeout, retry, token usage, embed single/batch | Can thiet |
| Sync vector | Observer -> pending Redis -> batch job/command, stop on 429, dead-letter/retry | Hoi nang nhung hop ly vi tranh fanout Gemini |
| Evaluation | Rule-based groundedness/relevance, flag gia/nam/so trang sai | Huu ich noi bo, khong nen ky vong nhu LLM judge |
| Logging DB | `ai_chat_messages`, `ai_chat_evaluations`, `ai_chat_feedback` | Dung muc can co de debug |
| Ops | `ai:chatbot:report`, `ai:chatbot:prune`, retention 90 ngay | Du dung, khong can dashboard ngay |
| Tests backend | Feature/unit tests bao phu chat, RAG, feedback, exact, follow-up, sync, report/prune | Diem manh |

## 5. Nhung phan bat buoc phai lam tot

### 5.1. Retrieval dung sach

Chatbot sach phai tim dung sach truoc khi viet hay. Cac case can uu tien:

- Exact title: ten sach co dau/khong dau, partial title, slug gan dung.
- Hoi tac gia/the loai/chu de pho bien.
- Semantic intent nhu "sach ve ky nang giao tiep", "sach de bat dau dau tu", "truyen kinh di cua Stephen King".
- Out-of-domain: khong goi y sach random neu retrieval khong matched.

Tieu chi chat luong:

- Cau hoi exact title voi sach ton tai trong catalog phai match on dinh.
- Cau hoi semantic chi duoc danh gia sau khi nhom sach lien quan da co vector.
- `matched=false` khong duoc dien giai thanh "Bookify khong ban sach nay"; chi la "chua tim thay trong du lieu hien co".

### 5.2. Su that ve gia, stock, rating

Gia, stock, rating, review count la du lieu dong. Quy tac da dung trong code la dung: retrieval document co the chua metadata, nhung prompt phai lay lai MySQL qua `RetrievedBookContextLoader`.

Day la yeu cau bat buoc, khong phai over-engineering. Neu chatbot bia gia hoac ton kho, do la loi chat luong nghiem trong.

### 5.3. Khong hallucinate khi khong co context

Khi retrieval khong matched hoac book context rong, cau tra loi dung la:

- Noi khong tim thay thong tin phu hop trong du lieu hien co.
- Khong goi y ten sach cu the.
- Khong bia gia, tac gia, nam xuat ban, stock.

Neu no-context ma van goi y sach cu the, day la bug/quality failure.

### 5.4. Response API ngan va dung contract

Backend nen tra loi ngan, co ly do goi y, co nguon sach neu cau tra loi nhac sach. Khong can van phong dai, khong can giai thich pipeline RAG cho user cuoi.

Response BE can giu dung contract:

- Envelope `data` + `meta`.
- `data.message_id` nullable.
- `data.answer` la noi dung hien thi.
- `data.sources` chi gom sach duoc cite trong answer.
- `meta.retrieval` co `strategy`, `top_score`, `matched`.
- `meta.evaluation` nullable khi fallback/log/evaluation fail.
- `meta.error_code` chi co khi fallback/degrade.

### 5.5. Fallback va chi phi

Voi he vua/nho, can uu tien khong lam API sap khi dich vu AI/search loi:

- Gemini chat loi -> tra fallback, log `error_code=gemini_chat_failed`, khong append Redis history.
- Embedding loi -> fallback, log `error_code=embedding_failed`.
- Meilisearch loi -> degrade retrieval `strategy=none`, khong 500.
- Rate limit theo guest/member la bat buoc de kiem soat chi phi.

## 6. Phan dang hop ly, khong xem la over-engineering

Nhung phan sau co the trong co ve nhieu, nhung phu hop voi bai toan chatbot sach neu da trien khai:

- Hybrid search + RRF fallback: can cho ca exact keyword va semantic query.
- Exact title resolver: can de hoi gia/stock theo ten sach khong phu thuoc vector coverage.
- Follow-up resolver ngan: can cho hoi "cuon do", "cuon dau tien".
- Redis history TTL 24h va sliding window: du nhe, tang UX ro.
- Fetch lai MySQL cho gia/stock/rating: bat buoc de tranh stale fact.
- Log message/evaluation/feedback: can de phan biet loi retrieval, loi prompt, loi model, loi UX.
- Batch embedding va pending sync: can de khong tao hang tram request Gemini khi import/sua metadata.
- Prune/report command: du nhe, can cho van hanh.

## 7. Phan nen xem la over-engineering trong giai do nay

Khong nen dau tu cac phan sau truoc khi RAG core va moi truong van hanh on dinh:

- Fine-tuning model.
- LLM-as-judge phuc tap hoac multi-judge scoring.
- Streaming response.
- Admin dashboard rieng cho chatbot.
- Query expansion/rewrite bang LLM cho moi request.
- Multi-agent/tool-calling framework.
- Long-term memory ca nhan hoa.
- Collaborative filtering hoac matrix factorization cho chatbot.
- Reranker model rieng.
- Chunking nhieu doan cho moi sach neu moi sach hien chi co mo ta ngan/vua.
- Multi-vector-store hoac chuyen vector DB khi Meilisearch dang du dung.
- Feedback co reason/comment/category truoc khi thumbs `up/down` duoc su dung that.
- Chatbot thao tac gio hang/don hang/thanh toan.

Neu muon lam cac muc tren, can co so lieu chung minh: retrieval miss cao, latency/cost chap nhan duoc, feedback down lap lai theo nhom loi ro rang, va backend da qua smoke test voi du lieu that.

## 8. Ranh gioi phan loai su co

### 8.1. Day la gioi han thiet ke da chap nhan

Xem la gioi han thiet ke, khong phai bug, neu:

- User hoi don hang, thanh toan, refund, dia chi giao hang, thong tin tai khoan.
- User hoi ngoai mien sach hoac yeu cau tu van khong lien quan catalog Bookify.
- User hoi sach khong co trong catalog hoac chua duoc index/vector hoa; bot tra "chua tim thay trong du lieu hien co".
- Semantic query miss khi vector coverage cua nhom sach lien quan chua du.
- Bot khong nho lich su sau 24h TTL hoac sau khi user mat `session_id`.
- Bot khong ca nhan hoa theo lich su mua hang, wishlist, profile dai han.
- Bot khong tra streaming.
- Bot khong co dashboard phan tich trong admin.
- Evaluation noi bo co false positive/false negative nho nhung khong anh huong response user.

### 8.2. Day la he thong dang lam chua tot

Xem la bug hoac quality failure, can sua, neu:

- Exact title cua sach dang co trong catalog nhung bot khong tim duoc khi hoi gia/stock/tac gia.
- Bot goi y sach cu the khi `retrieval_matched=false` va khong co context.
- Bot bia gia, stock, rating, tac gia, nam xuat ban, so trang.
- Bot khuyen "mua ngay" voi sach het hang.
- `sources` tra ve sach khong duoc nhac trong cau tra loi hoac khong dung sach duoc nhac.
- Gemini/Meilisearch loi lam API 500 thay vi fallback/degrade.
- Fallback cua Gemini bi append vao Redis history.
- Feedback guest/member bi sai authorization.
- Rate limit khong chan spam.
- Report/log khong ghi du `retrieval_strategy`, `retrieval_matched`, `error_code`, latency/token khi co du lieu.

### 8.3. Can dieu tra truoc khi ket luan

Cac case sau khong nen vo vang gan la bug:

- Semantic query tra ket qua kem: can kiem tra vector coverage, embedding text, Meilisearch config, threshold, va du lieu sach co mo ta hay khong.
- Bot tra cau "chua tim thay": can xem `retrieval_top_score`, `retrieval_strategy`, `retrieved_books`, coverage va query user co vuot scope khong.
- Evaluation warning: can xem `risk_flags`; co the la rule-based false positive.
- Latency cao: can tach Gemini embedding, Meilisearch search, Gemini chat, DB context load.
- Feedback down: can gom nhom theo message/retrieval/evaluation truoc khi quy loi.

## 9. Gioi han cau hinh de chot cho he vua/nho

Gia tri hien tai phu hop de bat dau:

| Muc | Gia tri | Ghi chu |
| --- | --- | --- |
| Chat model | `gemini-2.5-flash-lite` | Doi len model manh hon chi khi co bang chung quality kem |
| Embedding model | `gemini-embedding-2` | Dung `outputDimensionality=768` |
| Vector dimension | `768` | Doi dimension bat buoc re-index |
| Retrieval top K | `5` | Du cho goi y ngan |
| Hybrid semantic ratio | `0.6` | Tune sau smoke test |
| Hybrid min score | `0.80` | Khong ha neu chua co ly do |
| RRF min score | `0.016` | Cho top-1 mot list van co the match |
| History TTL | `86400` giay | 24h |
| History max turns | `10` | Khong can dai hon cho MVP |
| Question length | `2..1000` ky tu | Kiem soat prompt/cost |
| Guest rate limit | `20/phut` | Co the giam neu ton chi phi |
| Member rate limit | `60/phut` | Co the giam neu ton chi phi |
| Log retention | `90 ngay` | Du de debug van hanh |
| Sync batch size | `20` | Dieu chinh theo quota |
| Embed batch size | `25` | Dieu chinh theo quota |

## 10. Checklist BE truoc khi go production

- Chay migrate va cap nhat `database_schema.md` neu schema thay doi.
- Meilisearch embedder user-provided da cau hinh; tiep tuc kiem tra lai sau khi doi index/model.
- Vector coverage hien tai da 100%; tiep tuc sync khi them/sua/xoa sach active.
- Manual test exact title/price, semantic query, out-of-domain va no-context da co ket qua pass toi thieu; stock can lap lai trong smoke test truoc release neu muon go production.
- Chay test backend AI lien quan truoc release.
- Kiem tra `.env`: Gemini key, Meilisearch host/key, rate limit, timeout, queue.
- Dam bao queue `ai-rag-sync` duoc worker xu ly lien tuc trong production neu muon incremental sync.
- Chay `php artisan ai:chatbot:report` sau mot dot test de doc matched rate/error/evaluation/feedback.
- Xu ly hoac ghi chu 16 stale dead-letter neu dung dead-letter nhu chi so van hanh.

## 11. Manual test BE toi thieu

| Cau hoi | Ky vong |
| --- | --- |
| "Dac Nhan Tam gia bao nhieu?" | Match dung sach, tra gia tu MySQL, co source |
| "Dac Nhan Tam con hang khong?" | Tra stock hien tai, khong bia |
| "Toi muon sach ve ky nang giao tiep" | Goi y sach lien quan neu vector coverage du |
| "Co sach nao cua Stephen King khong?" | Tim theo tac gia neu catalog co |
| "Cuon dau tien gia bao nhieu?" sau khi bot goi y list | Follow-up dung cuon dau tien |
| "Bookify co ban dien thoai khong?" | Tu choi/no-context, khong goi y sach random |
| "Don hang cua toi dau roi?" | Noi chatbot hien chi ho tro tu van sach; khong tra cuu order/private data |

## 12. Huong uu tien tiep theo cho BE

Thu tu uu tien backend:

1. Dam bao worker/scheduler cho `ai-rag-sync` chay lien tuc trong production.
2. Xu ly hoac ghi chu stale dead-letter hien co de tranh nham voi loi sync moi.
3. Lap lai smoke test muc 11 truoc moi dot release hoac sau khi doi prompt/retrieval/model.
4. Theo doi `php artisan ai:chatbot:report` sau moi dot test de doc matched rate/error/latency/token.
5. Tune threshold/embedding text neu retrieval kem.
6. Chi sau do moi can xem xet streaming, dashboard, LLM judge, query expansion hoac ca nhan hoa nang cao.
