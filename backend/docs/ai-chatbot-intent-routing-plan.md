# AI Chatbot Intent Routing Plan

Tai lieu nay chia nho ke hoach sua chatbot Bookify de tranh day moi cau hoi vao RAG. Van de cu the: nhung cau hoi hoi thoai nhu "alo, ban co nghe thay toi khong" hien bi xu ly nhu truy van du lieu sach va tra ve "chua tim thay thong tin phu hop".

Muc tieu khong phai hard-code tat ca cach noi cua nguoi dung. Muc tieu la tao mot lop routing truoc RAG:

```text
User message
-> Normalize
-> High-confidence rules
-> Optional intent classifier khi can
-> Book RAG neu la cau hoi ve sach
-> Direct response neu la small talk/status
-> Scope-limited response neu ngoai pham vi
-> Clarify neu mo ho
```

## 1. Danh gia quy mo thay doi

### 1.1. Hotfix nho

Neu chi them vai phrase vao `OutOfScopeIntentGuard` de bat:

- "alo"
- "chao"
- "cam on"
- "tam biet"
- "ban co nghe thay toi khong"

thi day la thay doi nho, co the lam nhanh trong 1 lan sua.

Nhung cach nay chi giai quyet case ro rang. No khong mo rong tot neu nguoi dung noi khac di.

### 1.2. Thiet ke dung hon

Neu tach thanh `ChatIntentRouter`/`ChatIntentClassifier` rieng, co enum intent, co fallback `unknown`, co test chong bat nham, va co huong mo rong sang classifier mem, thi day la thay doi vua/lon.

Day la huong nen lam, vi:

- Tranh bien `OutOfScopeIntentGuard` thanh danh sach phrase ngay cang dai.
- Tach ro "ngoai pham vi" voi "hoi thoai thong thuong".
- Chi tra "khong tim thay du lieu" khi cau hoi that su la cau hoi ve sach.
- Sau nay co the them LLM/embedding classifier ma khong sua manh `ChatbotService`.

Ket luan: nen lam theo nhieu phase. Phase 1 sua du loi hien tai nhung van dat nen mong dung cho phase sau. Sau khi hotfix on dinh, nen lam Phase 2 som vi ten `OutOfScopeIntentGuard` se bat dau lech nghia neu no vua xu ly unsupported intents vua xu ly small talk.

## 2. Nguyen tac thiet ke

1. Hard-code chi dung cho case high-confidence.

   Vi du "cam on", "tam biet", "alo" la cac cau ngan, it nhap nhang. Khong nen hard-code moi bien the dai cua nguoi dung.

2. Khong short-circuit neu cau co intent sach ro rang.

   Vi du "alo, tim sach ky nang giao tiep giup toi" phai di tiep vao RAG, khong dung o greeting.

3. Khong dung RAG no-context cho small talk.

   "Ban co nghe thay toi khong" khong lien quan du lieu sach, nen khong duoc tra "chua tim thay thong tin phu hop trong du lieu hien co".

4. Giu contract API hien tai.

   Response van co `data.answer`, `data.sources`, `meta.session_id`, `meta.retrieval`, `meta.evaluation`.

5. Khong goi Gemini/RAG cho direct intent ro rang.

   Direct intent phai nhanh, re, khong phu thuoc external service.

6. Log day du de phan tich ve sau.

   Direct intent van nen log vao `ai_chat_messages`, voi `retrieval_strategy = none`, `retrieval_matched = false`.

## 3. Intent taxonomy de bat dau

Phase 1 co the van dung guard hien co va xem cac intent sach la pass-through: neu la cau hoi ve sach thi khong short-circuit, de pipeline RAG/exact/follow-up hien tai xu ly.

Sang Phase 2, taxonomy nen tach toi thieu theo nhom sau:

| Intent | Y nghia | Xu ly |
| --- | --- | --- |
| `book.search` | Tim sach theo ten, tac gia, the loai, nha xuat ban, keyword | Di tiep pipeline RAG/exact |
| `book.detail` | Hoi thong tin sach cu the nhu gia, stock, review, tac gia, mo ta | Di tiep exact/follow-up/RAG |
| `book.recommendation` | Goi y sach theo nhu cau, muc tieu doc, so sanh lua chon | Di tiep RAG/follow-up |
| `small_talk.greeting` | Chao hoi ngan | Tra loi truc tiep |
| `small_talk.status_check` | Kiem tra bot co nghe/hoat dong khong | Tra loi truc tiep |
| `small_talk.thanks` | Cam on | Tra loi truc tiep |
| `small_talk.goodbye` | Tam biet | Tra loi truc tiep |
| `small_talk.capability` | Hoi bot lam duoc gi | Tra loi truc tiep, noi ro chi ho tro sach |
| `unsupported.order` | Don hang/huy don/trang thai don | Tra scope-limited response hien co |
| `unsupported.payment` | Thanh toan/giao dich/hoan tien neu chua can tach rieng | Tra scope-limited response hien co |
| `unsupported.account` | Mat khau/dia chi/thong tin ca nhan | Tra scope-limited response hien co |
| `unsupported.non_book_product` | Dien thoai/laptop/my pham... | Tra scope-limited response hien co |
| `unknown` | Khong du chac de route | MVP mac dinh di pipeline hien tai |

Ghi chu:

- Phase 1 co the khong can return `book.search`/`book.detail`/`book.recommendation`; chi can khong match direct/unsupported de `ChatbotService` chay luong cu.
- `unsupported.refund` hien co co the tiep tuc ton tai trong Phase 1 de tranh doi behavior bat ngo. Sang Phase 2, neu khong can analytics rieng cho refund, co the map vao `unsupported.payment`.
- `unknown` khong nen hoi lai qua som trong MVP. Neu hoi clarify manh khi classifier chua tot, chatbot de bi "nhat" va lam mat cac cau dang le RAG co the tim duoc.

## 4. Phase 1 - Sua loi hien tai va dat nen mong

Muc tieu: sua loi "alo, ban co nghe thay toi khong" voi thay doi nho, khong doi schema, khong them external call.

### 4.1. File du kien sua

- `backend/app/Services/Ai/OutOfScopeIntentGuard.php`
- `backend/app/Services/Ai/Dto/OutOfScopeIntentGuardResult.php` neu can them field nho
- `backend/app/Services/Ai/ChatbotService.php` neu can doi ten method/response meta
- `backend/tests/Feature/Ai/ChatTest.php`

### 4.2. Cach lam uu tien

Giu class hien tai de giam lan sua, nhung mo rong y nghia cua result:

- `matched = true` nghia la request duoc short-circuit truoc RAG.
- `category` co the la `small_talk.status_check` hoac `unsupported.order`.
- `response` la cau tra loi truc tiep.

Chua rename class trong phase 1. Viec rename sang `ChatIntentRouter` de phase 2 lam rieng.

### 4.3. Thu tu evaluate de tranh bat nham

1. Normalize text.
2. Neu rong: khong match.
3. Neu co book intent ro rang va cau khong phai small talk cuc ngan: khong match, de RAG xu ly.
4. Match small talk high-confidence.
5. Match unsupported intents hien co.
6. Neu khong match: de pipeline cu xu ly.

Voi Phase 1, `unknown` mac dinh phai di pipeline cu. Chua them clarify response cho `unknown` trong hotfix.

Book intent phrases nen gom it nhat:

- `sach`
- `cuon`
- `quyen`
- `doc`
- `tac gia`
- `the loai`
- `nha xuat ban`
- `gia`
- `con hang`
- `ton kho`
- `review`
- `danh gia`
- `goi y`
- `tu van`

Small talk chi short-circuit khi:

- cau rat ngan, hoac
- match pattern rat ro nhu "ban co nghe thay toi khong", "bot con hoat dong khong", "ban con do khong".

### 4.4. Response de them

Response nen ngan, tu nhien, va huong user ve nhiem vu sach:

| Category | Response |
| --- | --- |
| `small_talk.greeting` | `Chao ban, minh dang o day. Ban muon minh ho tro tim sach hay tu van sach nao khong?` |
| `small_talk.status_check` | `Co, minh nghe thay ban. Ban can minh ho tro tim sach hoac tu van noi dung sach nao khong?` |
| `small_talk.thanks` | `Rat vui duoc ho tro ban. Khi can tim sach hoac goi y sach, ban cu nhan minh nhe.` |
| `small_talk.goodbye` | `Tam biet ban. Khi can tim sach tren Bookify, minh luon san sang ho tro.` |
| `small_talk.capability` | `Minh co the ho tro tim sach, goi y sach theo nhu cau, tom tat thong tin sach va tra loi cau hoi lien quan den sach tren Bookify.` |

Ghi chu: code hien co dang co mot so chuoi tieng Viet bi mojibake trong file. Khi sua, nen giu ASCII khong dau trong code neu file dang khong on dinh encoding, hoac chuyen co chu dich neu sau nay co task rieng ve encoding.

### 4.5. Behavior mong doi

Case direct:

```text
Question: alo, ban co nghe thay toi khong
Answer: Co, minh nghe thay ban...
meta.model: null
meta.retrieval.strategy: none
meta.retrieval.matched: false
meta.evaluation: null
sources: []
Gemini: khong goi
RAG: khong goi
Redis history: giu hanh vi hien tai, khong append
DB log: co
```

Case khong bat nham:

```text
Question: alo, tim sach ky nang giao tiep giup toi
Behavior: khong short-circuit greeting, di tiep luong RAG hien tai
```

### 4.6. Redis history

Phase 1 khong append direct small talk vao Redis history, giong hanh vi out-of-scope hien tai.

Ly do:

- "alo" truoc cau "toi muon tim sach ve tai chinh ca nhan" khong giup RAG.
- "ban lam duoc gi" truoc cau "goi y sach hoc Laravel" cung khong can la context truy xuat.
- Giam thay doi hanh vi trong hotfix.

Van phai log DB de sau nay biet nguoi dung hay hoi nhom cau nao va cai thien routing.

### 4.7. Ke hoach trien khai chi tiet Phase 1

Muc tieu Phase 1 la sua dung loi UX hien tai voi thay doi nho, co test bao ve, khong doi API contract, khong doi schema, khong them external classifier.

#### Buoc 1 - Xac nhan diem chen

File can doc lai truoc khi sua:

- `backend/app/Services/Ai/ChatbotService.php`
- `backend/app/Services/Ai/OutOfScopeIntentGuard.php`
- `backend/app/Services/Ai/Dto/OutOfScopeIntentGuardResult.php`
- `backend/tests/Feature/Ai/ChatTest.php`
- `backend/tests/Feature/Ai/Support/AiChatTestHelpers.php`

Diem chen du kien:

- Giu `ChatbotService::handle()` goi `$this->outOfScopeIntentGuard->evaluate($question)` o dau pipeline.
- Mo rong `OutOfScopeIntentGuard::evaluate()` de short-circuit small talk high-confidence.
- Chi sua `ChatbotService` neu can doi ten method noi bo hoac bo sung meta noi bo; Phase 1 uu tien khong sua.

#### Buoc 2 - Mo rong phrase constants trong guard

Trong `OutOfScopeIntentGuard`, them cac nhom constant rieng, khong tron vao unsupported categories:

```php
private const BOOK_INTENT_PHRASES = [...];

private const SMALL_TALK_PHRASES = [
    'small_talk.status_check' => [...],
    'small_talk.greeting' => [...],
    'small_talk.thanks' => [...],
    'small_talk.goodbye' => [...],
    'small_talk.capability' => [...],
];

private const CATEGORY_PHRASES = [
    'order' => [...],
    ...
];
```

Ten category Phase 1 co the dung day du `small_talk.status_check` de sau nay migrate sang `ChatIntentRouter` de hon.

#### Buoc 3 - Cap nhat book intent override

Cap nhat `BOOK_INTENT_PHRASES` de bat duoc cac cau co y dinh sach ro rang:

- `sach`
- `cuon`
- `quyen`
- `doc`
- `tac gia`
- `the loai`
- `nha xuat ban`
- `gia`
- `con hang`
- `ton kho`
- `review`
- `danh gia`
- `goi y`
- `tu van`

Quy tac:

- Neu cau co book intent va khong phai small talk cuc ngan, guard khong short-circuit small talk.
- Vi du `alo, tim sach ky nang giao tiep giup toi` phai di pipeline cu.
- Vi du `ban co sach nao hay khong` phai di pipeline cu.

#### Buoc 4 - Them ham match small talk high-confidence

Them method rieng trong `OutOfScopeIntentGuard`, vi du:

```php
private function matchSmallTalk(string $normalizedQuestion): ?string
```

Y tuong:

1. Duyet `SMALL_TALK_PHRASES`.
2. Match bang exact phrase hoac `str_contains` cho pattern rat ro.
3. Tra category small talk neu match.
4. Neu khong match tra `null`.

Danh sach phrase Phase 1 nen it va chac:

`small_talk.status_check`:

- `ban co nghe thay toi khong`
- `co nghe thay toi khong`
- `ban con do khong`
- `bot con hoat dong khong`
- `ban co online khong`

`small_talk.greeting`:

- `alo`
- `chao`
- `chao ban`
- `hello`
- `hi`

`small_talk.thanks`:

- `cam on`
- `thanks`
- `thank you`

`small_talk.goodbye`:

- `tam biet`
- `bye`
- `hen gap lai`

`small_talk.capability`:

- `ban lam duoc gi`
- `ban ho tro gi`
- `chatbot nay lam duoc gi`
- `ban co the giup gi`

#### Buoc 5 - Thu tu evaluate moi

Trong `evaluate()`:

```text
normalize
if empty -> no match

smallTalkCategory = matchSmallTalk(normalized)
if smallTalkCategory != null and shouldShortCircuitSmallTalk(normalized, smallTalkCategory)
    -> matched true, response small talk

matchedUnsupported = matchCategory(normalized)
if unsupported matched and not valid book-intent override
    -> matched true, response unsupported

return no match
```

`shouldShortCircuitSmallTalk()` can dam bao khong bat nham:

- Neu category la `small_talk.status_check`, cho short-circuit khi pattern ro.
- Neu category la `small_talk.greeting`, chi short-circuit khi cau ngan hoac khong co book intent.
- Neu category la `small_talk.thanks`, khong short-circuit khi cau co `goi y`, `tim sach`, `sach`, `cuon`.
- Neu category la `small_talk.capability`, short-circuit duoc vi day la hoi kha nang bot, khong can RAG.

#### Buoc 6 - Them response fixed

Them response trong `responseForCategory()`:

```php
'small_talk.greeting' => 'Chao ban, minh dang o day. Ban muon minh ho tro tim sach hay tu van sach nao khong?',
'small_talk.status_check' => 'Co, minh nghe thay ban. Ban can minh ho tro tim sach hoac tu van noi dung sach nao khong?',
'small_talk.thanks' => 'Rat vui duoc ho tro ban. Khi can tim sach hoac goi y sach, ban cu nhan minh nhe.',
'small_talk.goodbye' => 'Tam biet ban. Khi can tim sach tren Bookify, minh luon san sang ho tro.',
'small_talk.capability' => 'Minh co the ho tro tim sach, goi y sach theo nhu cau, tom tat thong tin sach va tra loi cau hoi lien quan den sach tren Bookify.',
```

Phase 1 dung ASCII khong dau neu file hien tai dang mojibake. Sau khi hotfix pass, tao task rieng de chuan hoa UTF-8 va response co dau.

#### Buoc 7 - Giu behavior logging hien tai

Khong doi `buildScopeLimitedResponse()` trong Phase 1 neu khong can.

Ket qua mong doi:

- `message_id` co neu DB log thanh cong.
- `model` la `null`.
- `retrieval.strategy` la `none`.
- `retrieval.matched` la `false`.
- `evaluation` la `null`.
- `sources` rong.
- Khong append Redis history.
- Khong goi Gemini.

Neu muon doi ten method `buildScopeLimitedResponse()` thanh ten tong quat hon, de Phase 2. Phase 1 uu tien khong rename.

#### Buoc 8 - Them feature tests cho direct small talk

Them test moi trong `backend/tests/Feature/Ai/ChatTest.php`, co the dung dataset:

```php
test('small talk intents short-circuit without rag or gemini calls', function (string $question, string $expectedAnswerFragment): void {
    ...
})->with([
    'status check' => ['alo, ban co nghe thay toi khong', 'Co, minh nghe thay ban'],
    'still there' => ['ban con do khong', 'Co, minh nghe thay ban'],
    'greeting' => ['chao ban', 'Chao ban'],
    'thanks' => ['cam on', 'Rat vui duoc ho tro ban'],
    'capability' => ['ban lam duoc gi', 'Minh co the ho tro tim sach'],
]);
```

Assert:

- `assertOk()`
- `data.answer` contains expected fragment
- `data.sources = []`
- `meta.model = null`
- `meta.retrieval.strategy = none`
- `meta.retrieval.matched = false`
- `meta.evaluation = null`
- `Http::assertNothingSent()`
- `ChatHistoryStore::getAll($sessionId)` rong
- DB co message voi `retrieval_strategy = none`, `retrieval_matched = false`, `error_code = null`

#### Buoc 9 - Them regression tests chong bat nham

Them test rieng cho cac cau phai di pipeline cu:

1. `alo, tim sach ky nang giao tiep giup toi`
2. `cam on, goi y them sach tuong tu di`
3. `ban co sach nao hay khong`
4. `Bookify co ban sach ve dien thoai khong`

Voi cac case nay:

- fake Gemini chat success nhu test chat hien co.
- assert `meta.model = config('ai.gemini.chat_model')` neu pipeline goi Gemini.
- assert `Http::assertSent(...)` de chung minh khong bi short-circuit.
- Neu test co RAG mock default unmatched, co the `retrieval.strategy = none` nhung `model` van la Gemini model.

Rieng case `Bookify co ban sach ve dien thoai khong` can can than:

- Cau co `sach`, nen khong bi `unsupported.non_book_product` chan.
- Neu RAG khong match, bot co the tra no-context qua Gemini theo pipeline cu. Dieu quan trong cua regression la no khong bi direct guard chan.

#### Buoc 10 - Chay test muc tieu

Lenh chay trong backend:

```powershell
php artisan test tests/Feature/Ai/ChatTest.php
```

Neu pass, co the chay them:

```powershell
php artisan test tests/Feature/Ai/ChatRagTest.php
php artisan test tests/Feature/Ai/ChatFollowUpTest.php
```

Neu can confidence cao hon truoc khi release:

```powershell
php artisan test tests/Feature/Ai
```

#### Buoc 11 - Manual smoke test UX

Sau khi backend pass test, test qua UI hoac API:

| Cau hoi | Ky vong |
| --- | --- |
| `alo, ban co nghe thay toi khong` | Tra "Co, minh nghe thay ban..." |
| `chao ban` | Tra loi chao, moi tim/tu van sach |
| `ban lam duoc gi` | Noi ro kha nang ho tro sach |
| `alo, tim sach ky nang giao tiep giup toi` | Khong dung o chao hoi, van tim/tra loi theo pipeline sach |
| `ban co sach nao hay khong` | Van xu ly nhu cau hoi sach |

#### Buoc 12 - Tieu chi khong lam trong Phase 1

Khong lam cac viec sau trong hotfix:

- Khong rename `OutOfScopeIntentGuard`.
- Khong tao `ChatIntentRouter`.
- Khong them enum intent.
- Khong them migration/cot intent vao DB.
- Khong expose intent trong API response.
- Khong them Gemini/embedding classifier.
- Khong chuan hoa encoding toan bo PHP file.
- Khong doi frontend.

#### Buoc 13 - Tieu chi hoan thanh Phase 1

Phase 1 hoan thanh khi:

- Small talk/status high-confidence khong con di vao RAG/Gemini.
- Cau "alo, ban co nghe thay toi khong" tra loi tu nhien.
- Regression tests chung minh cau co intent sach khong bi bat nham.
- DB van log message.
- Redis history khong append direct small talk.
- API contract khong doi.
- `php artisan test tests/Feature/Ai/ChatTest.php` pass.

#### Buoc 14 - Duong lui neu co loi

Neu Phase 1 gay loi:

- Revert thay doi trong `OutOfScopeIntentGuard`.
- Giu lai tests neu can de mo ta loi mong muon.
- Khong can revert schema vi Phase 1 khong doi DB.
- Khong can clear config/cache vi Phase 1 khong them config.

## 5. Phase 2 - Tach router rieng

Muc tieu: tach trach nhiem ro rang, tranh ten `OutOfScopeIntentGuard` bi lech nghia khi no vua xu ly unsupported vua small talk.

### 5.1. File/class moi du kien

- `backend/app/Enums/Ai/ChatIntent.php`
- `backend/app/Services/Ai/ChatIntentRouter.php`
- `backend/app/Services/Ai/Dto/ChatIntentRouteResult.php`
- `backend/tests/Unit/Ai/ChatIntentRouterTest.php`

### 5.2. Vai tro class

`ChatIntentRouter`:

- normalize input
- chay high-confidence rules
- tra `ChatIntentRouteResult`
- khong goi external service
- khong biet database

`OutOfScopeIntentGuard`:

- co the bi thay the, hoac duoc goi ben trong router trong giai do chuyen tiep
- ve sau nen chi con la private logic cua router

`ChatbotService`:

- goi router dau tien
- neu `shouldShortCircuit = true` thi build direct response
- neu `shouldShortCircuit = false` thi chay pipeline cu

### 5.3. DTO goi y

```php
readonly class ChatIntentRouteResult
{
    public function __construct(
        public string $intent,
        public bool $shouldShortCircuit,
        public ?string $response,
        public float $confidence,
        public string $strategy,
    ) {}
}
```

`strategy` co the la:

- `rule`
- `classifier`
- `fallback`

Trong phase 2, chi can `rule`.

### 5.4. Logging/meta goi y

Phase 1 chua expose intent trong API meta de giu contract frontend on dinh.

Sang Phase 2, neu can debug/analytics, uu tien luu intent noi bo thay vi doi API response cho frontend. Vi du co the them vao DB log:

```text
intent = small_talk.status_check
intent_strategy = rule
intent_confidence = 1.0
```

Neu them cot DB, lam bang migration rieng va cap nhat `backend/docs/database_schema.md`. Khong nen tron thay doi schema/logging vao Phase 1.

Neu sau nay that su can frontend doc intent, khi do moi them vao API meta va phai co contract test.

### 5.5. Ke hoach trien khai chi tiet Phase 2

Muc tieu Phase 2 la refactor dung ten va dung ranh gioi trach nhiem, khong doi hanh vi nguoi dung da dat duoc o Phase 1.

Nguyen tac:

- Khong doi schema trong lat refactor dau tien.
- Khong expose `intent` trong API response.
- Khong them Gemini/embedding classifier.
- Khong doi response text.
- Test Phase 1 phai tiep tuc pass truoc va sau refactor.

#### Buoc 1 - Them enum `ChatIntent`

Tao file:

- `backend/app/Enums/Ai/ChatIntent.php`

Enum goi y:

```php
<?php

namespace App\Enums\Ai;

enum ChatIntent: string
{
    case BookSearch = 'book.search';
    case BookDetail = 'book.detail';
    case BookRecommendation = 'book.recommendation';

    case SmallTalkGreeting = 'small_talk.greeting';
    case SmallTalkStatusCheck = 'small_talk.status_check';
    case SmallTalkThanks = 'small_talk.thanks';
    case SmallTalkGoodbye = 'small_talk.goodbye';
    case SmallTalkCapability = 'small_talk.capability';

    case UnsupportedOrder = 'unsupported.order';
    case UnsupportedPayment = 'unsupported.payment';
    case UnsupportedAccount = 'unsupported.account';
    case UnsupportedNonBookProduct = 'unsupported.non_book_product';

    case Unknown = 'unknown';
}
```

Ghi chu:

- Phase 2 co the van map `refund` hien co vao `UnsupportedPayment`, hoac giu them `UnsupportedRefund` neu muon analytics rieng. Neu giu `refund`, can them vao taxonomy doc sau.
- `private_account` hien co nen map sang `unsupported.account` trong router, nhung response co the giu noi dung cu.

#### Buoc 2 - Them DTO `ChatIntentRouteResult`

Tao file:

- `backend/app/Services/Ai/Dto/ChatIntentRouteResult.php`

DTO goi y:

```php
<?php

namespace App\Services\Ai\Dto;

use App\Enums\Ai\ChatIntent;

readonly class ChatIntentRouteResult
{
    public function __construct(
        public ChatIntent $intent,
        public bool $shouldShortCircuit,
        public ?string $response,
        public float $confidence,
        public string $strategy,
    ) {}
}
```

Gia tri `strategy` trong Phase 2:

- `rule` khi match high-confidence rule.
- `fallback` khi khong match rule va tra `unknown`.

Chua can `classifier` cho Phase 2.

#### Buoc 3 - Tao `ChatIntentRouter`

Tao file:

- `backend/app/Services/Ai/ChatIntentRouter.php`

Chuyen logic sau tu `OutOfScopeIntentGuard` sang router:

- `BOOK_INTENT_PHRASES`
- `SMALL_TALK_PHRASES`
- `CATEGORY_PHRASES`
- `normalizeIntentText()`
- `matchSmallTalk()`
- `shouldShortCircuitSmallTalk()`
- `isStatusCheckOnlyUtterance()`
- `remainderIsOnlyGreetingPrefix()`
- `isShortSmallTalkUtterance()`
- `matchCategory()`
- `hasBookIntent()`
- `containsPhrase()`
- `responseForCategory()`

Public API moi:

```php
public function route(string $question): ChatIntentRouteResult
```

Behavior:

```text
normalize
if empty -> Unknown, shouldShortCircuit=false, strategy=fallback

if small talk high-confidence and should short-circuit
    -> small_talk.*, shouldShortCircuit=true, response fixed, confidence=1.0, strategy=rule

if unsupported matched and not book-intent override
    -> unsupported.*, shouldShortCircuit=true, response fixed, confidence=1.0, strategy=rule

if book intent matched
    -> book.search/detail/recommendation, shouldShortCircuit=false, response=null, confidence=0.8, strategy=rule

else
    -> Unknown, shouldShortCircuit=false, response=null, confidence=0.0, strategy=fallback
```

Phan loai book intent toi thieu:

| Intent | Rule goi y |
| --- | --- |
| `book.detail` | Co phrase gia/stock/detail nhu `gia sach`, `gia ban`, `gia bao nhieu`, `bao nhieu tien`, `con hang`, `ton kho`, `review`, `danh gia`, `tac gia`, `nha xuat ban` |
| `book.recommendation` | Co `goi y`, `tu van`, `nen doc`, `phu hop`, `tim sach ve` |
| `book.search` | Co `sach`, `cuon`, `quyen`, `the loai`, hoac book intent chung khong thuoc hai nhom tren |

Trong Phase 2, book intent chi dung de route noi bo/debug va khong short-circuit. Neu classification book chua chuan 100%, response user van khong bi anh huong vi pipeline cu xu ly tiep.

#### Buoc 4 - Chuyen `ChatbotService` sang dung router

Sua constructor:

```php
private readonly ChatIntentRouter $chatIntentRouter,
```

Thay dau `handle()`:

```php
$intentRoute = $this->chatIntentRouter->route($question);

if ($intentRoute->shouldShortCircuit) {
    return $this->buildIntentRouteResponse(..., intentRoute: $intentRoute);
}
```

Co hai cach lam:

1. Doi `buildScopeLimitedResponse()` thanh `buildIntentRouteResponse()`.
2. Giu method cu trong mot lat, nhung doi parameter tu `OutOfScopeIntentGuardResult` sang `ChatIntentRouteResult`.

Khuyen nghi: doi ten method noi bo sang `buildIntentRouteResponse()` trong Phase 2, vi ten cu da lech nghia. Day la private method, rui ro thap.

Ket qua response phai giu nhu Phase 1:

- `meta.model = null`
- `meta.retrieval.strategy = none`
- `meta.retrieval.matched = false`
- `meta.evaluation = null`
- `data.sources = []`
- khong append Redis history
- DB log van co

#### Buoc 5 - Xu ly `OutOfScopeIntentGuard`

Co 2 lua chon:

Lua chon A - Xoa class cu:

- Xoa `OutOfScopeIntentGuard.php`.
- Xoa `OutOfScopeIntentGuardResult.php`.
- Cap nhat constructor/tests neu co reference.

Lua chon B - Giu wrapper tam:

- `OutOfScopeIntentGuard` inject/goi `ChatIntentRouter`.
- Convert `ChatIntentRouteResult` ve `OutOfScopeIntentGuardResult`.
- Danh dau bang comment ngan la compatibility wrapper.

Khuyen nghi:

- Neu search `rg "OutOfScopeIntentGuard"` chi thay `ChatbotService` va tests lien quan, dung lua chon A cho sach.
- Neu co nhieu reference ngoai y muon, dung lua chon B va tao task cleanup sau.

#### Buoc 6 - Khong them intent vao API meta trong lat dau

Phase 2 chi refactor backend. Chua them:

```json
"intent": { ... }
```

Ly do:

- Giu contract frontend on dinh.
- Giam blast radius.
- Neu can debug intent, co the them log noi bo sau.

Neu muon quan sat tam thoi trong dev, dung `Log::debug()` co dieu kien local/test, khong them vao response production.

#### Buoc 7 - Unit test `ChatIntentRouter`

Tao file:

- `backend/tests/Unit/Ai/ChatIntentRouterTest.php`

Test nhom small talk:

| Question | Expected |
| --- | --- |
| `alo` | `small_talk.greeting`, short-circuit |
| `chao ban` | `small_talk.greeting`, short-circuit |
| `alo, ban co nghe thay toi khong` | `small_talk.status_check`, short-circuit |
| `ban con do khong` | `small_talk.status_check`, short-circuit |
| `cam on` | `small_talk.thanks`, short-circuit |
| `tam biet` | `small_talk.goodbye`, short-circuit |
| `ban lam duoc gi` | `small_talk.capability`, short-circuit |

Test book intent khong short-circuit:

| Question | Expected |
| --- | --- |
| `alo, tim sach ky nang giao tiep giup toi` | book intent, not short-circuit |
| `ban con do khong, goi y sach tai chinh cho toi` | `book.recommendation`, not short-circuit |
| `Dac Nhan Tam gia bao nhieu tien` | `book.detail`, not short-circuit |
| `ban co sach nao hay khong` | `book.search`, not short-circuit |

Test unsupported:

| Question | Expected |
| --- | --- |
| `Don hang cua toi dau roi` | `unsupported.order`, short-circuit |
| `Thanh toan VNPAY bi loi` | `unsupported.payment`, short-circuit |
| `Doi mat khau tai khoan the nao` | `unsupported.account`, short-circuit |
| `Bookify co ban dien thoai khong` | `unsupported.non_book_product`, short-circuit |
| `Bookify co ban dien thoai gia re khong` | `unsupported.non_book_product`, short-circuit |
| `Bookify co ban sach ve dien thoai khong` | book intent or unknown, not unsupported short-circuit |

Test phrase boundary:

- `khi nao co sach moi` khong bi match `hi` greeting.
- `dien thoai gia re` khong bi match book detail do `gia`.
- Tu don nhu `sach` chi match token rieng, khong match trong tu dai.

#### Buoc 8 - Giu/bo sung feature tests hien co

Feature tests trong `ChatTest.php` cua Phase 1 phai giu nguyen:

- small talk short-circuit
- book-related questions not short-circuited
- non-book product incidental `gia` token
- out-of-scope unsupported intents

Neu `ChatbotService` doi constructor dependency, cac feature tests nay se la bao hiem chinh cho API behavior.

#### Buoc 9 - Lenh test

Chay unit router truoc:

```powershell
php artisan test tests/Unit/Ai/ChatIntentRouterTest.php
```

Chay API chat contract/behavior:

```powershell
php artisan test tests/Feature/Ai/ChatTest.php
```

Chay RAG/follow-up lien quan:

```powershell
php artisan test tests/Feature/Ai/ChatRagTest.php
php artisan test tests/Feature/Ai/ChatFollowUpTest.php
```

Neu co thoi gian, chay toan bo AI:

```powershell
php artisan test --filter=Ai
```

Chay tuan tu, khong song song, vi testing DB co the va nhau khi `RefreshDatabase` migrate/drop bang.

#### Buoc 10 - Tieu chi nghiem thu Phase 2

Phase 2 hoan thanh khi:

- `ChatbotService` khong con inject truc tiep `OutOfScopeIntentGuard`.
- Intent routing nam trong `ChatIntentRouter`.
- `ChatIntent` enum co taxonomy toi thieu: `book.search`, `book.detail`, `book.recommendation`, `small_talk.*`, `unsupported.*`, `unknown`.
- `OutOfScopeIntentGuard` bi xoa hoac chi con wrapper tam co ly do ro.
- API response khong doi so voi Phase 1.
- Direct small talk van khong goi Gemini/RAG.
- Book intent lai small talk van di pipeline cu.
- Unsupported non-book van bi chan, truong hop `sach ve dien thoai` khong bi chan sai.
- Tests muc Buoc 9 pass.

#### Buoc 11 - Nhung viec khong lam trong Phase 2

Khong lam:

- Khong them classifier Gemini/embedding.
- Khong them config `.env`.
- Khong them cot DB intent.
- Khong expose intent trong API meta.
- Khong sua frontend.
- Khong chuan hoa mojibake response cu.
- Khong thay doi Redis history behavior.

Nhung viec nay de Phase 3 hoac task rieng.

#### Buoc 12 - Duong lui neu refactor loi

Neu Phase 2 co bug:

1. Revert dependency injection trong `ChatbotService` ve `OutOfScopeIntentGuard`.
2. Giu lai `ChatIntentRouterTest` neu no mo ta behavior mong muon.
3. Khong can rollback DB/config vi Phase 2 khong doi schema/config.
4. Neu da xoa class cu, co the khoi phuc tu Phase 1 diff nhanh vi API cu rat nho.

## 6. Phase 3 - Classifier mem tuy chon

Muc tieu: giam phu thuoc vao phrase hard-code cho cac case khong ro.

Chi lam phase nay neu sau phase 1/2 van co nhieu feedback sai vi bot route nham.

### 6.1. Lua chon classifier

Lua chon A - LLM intent classifier:

- Goi Gemini voi prompt rat ngan.
- Yeu cau tra JSON enum.
- Chi dung khi rule khong ket luan.
- Co timeout ngan hon chat answer.
- Co fallback neu loi: `unknown`.

Lua chon B - Embedding similarity:

- Tao tap example intent mau.
- Embed user question va compare voi example vectors.
- Re-use Gemini embedding nhung van ton external call.
- Can threshold va test ky de tranh bat nham.

Lua chon C - Khong dung external classifier:

- Giu rule high-confidence.
- Cac case khong ro di pipeline cu.
- Dung feedback/log de cai thien dan.

Khuyen nghi: Chua lam LLM classifier ngay. Phase 1/2 co the du cho MVP va it rui ro chi phi/latency.

### 6.2. Config neu lam classifier

Them config sau trong `config/ai.php`:

```php
'intent' => [
    'classifier_enabled' => (bool) env('AI_CHAT_INTENT_CLASSIFIER_ENABLED', false),
    'classifier_timeout_seconds' => (int) env('AI_CHAT_INTENT_CLASSIFIER_TIMEOUT_SECONDS', 3),
    'classifier_confidence_threshold' => (float) env('AI_CHAT_INTENT_CLASSIFIER_CONFIDENCE_THRESHOLD', 0.80),
],
```

Env:

```env
AI_CHAT_INTENT_CLASSIFIER_ENABLED=false
AI_CHAT_INTENT_CLASSIFIER_TIMEOUT_SECONDS=3
AI_CHAT_INTENT_CLASSIFIER_CONFIDENCE_THRESHOLD=0.80
```

Neu doi config tren moi truong da cache config:

```bash
php artisan config:clear
```

### 6.3. Ke hoach trien khai chi tiet Phase 3

Muc tieu Phase 3 la them classifier mem co kiem soat de xu ly cac cau rule router khong bat duoc, nhung khong lam tang rui ro latency/chi phi cho toan bo chat.

Khuyen nghi: Phase 3 khong nen lam ngay sau Phase 2 neu chua co du lieu route sai. Nen di theo thu tu:

1. Do luong va gom case that.
2. Chon classifier.
3. Them classifier sau feature flag, mac dinh tat.
4. Chi bat classifier cho `unknown` hoac case rule confidence thap.
5. Rollout noi bo truoc, production sau.

#### Buoc 0 - Dieu kien kich hoat Phase 3

Chi nen trien khai classifier neu co it nhat mot trong cac tin hieu sau:

- Nhieu feedback down voi `retrieval_matched=false` nhung user message that ra la small talk/unsupported.
- Nhieu message no-context cho cau khong phai cau hoi sach.
- Admin/tester thu cong ghi nhan nhieu bien the small talk ma rule khong bat.
- Ban muon co analytics intent noi bo tot hon de bao cao chatbot.

Khong nen lam Phase 3 chi vi "co the co nhieu cach noi", vi classifier them external call va co the lam chat cham hon.

#### Buoc 1 - Them intent audit truoc classifier

Truoc khi them classifier that, nen tao bao cao nho de doc cac case `unknown` / no-context / feedback down.

Lua chon khong doi schema:

- Dung `ai_chat_messages.question`, `retrieval_matched`, `retrieval_strategy`, `error_code`, `created_at`.
- Join voi `ai_chat_feedback` neu co rating down.
- Tao command read-only:
  - `php artisan ai:chatbot:intent-audit`

Output goi y:

- Top cau hoi co `retrieval_matched=false`.
- Top cau feedback down.
- Mau cau ngan duoi 8 token nhung di pipeline Gemini.
- Mau cau co `meta.model != null`, `sources=[]`, answer no-context.

File du kien:

- `backend/app/Console/Commands/Ai/ChatbotIntentAuditCommand.php`
- `backend/tests/Feature/Ai/ChatbotIntentAuditCommandTest.php`

Ghi chu: buoc audit co the la subphase 3A rieng. No huu ich ngay ca khi cuoi cung khong bat classifier.

#### Buoc 2 - Chot chien luoc classifier

Co 3 chien luoc:

| Chien luoc | Uu diem | Nhuoc diem | Khuyen nghi |
| --- | --- | --- | --- |
| Khong classifier, tiep tuc tune rules | Re, nhanh, on dinh | Van sot bien the | Mac dinh neu audit khong co loi dang ke |
| LLM intent classifier | Linh hoat, hieu paraphrase tot | Them latency/chi phi, can parse JSON chat | Phu hop neu small talk/unsupported miss nhieu |
| Embedding similarity classifier | On dinh hon LLM, co threshold | Van can embedding call/cache, kho tune example | Chi can neu muon it variability hon LLM |

Khuyen nghi cho Bookify MVP:

- Uu tien LLM classifier nho, chi chay khi `ChatIntentRouter` tra `Unknown`.
- Khong classifier cho message da match rule high-confidence.
- Khong classifier cho message qua dai neu co the la noi dung sach/phuc tap; de pipeline cu xu ly.

#### Buoc 3 - Mo rong config

Them config trong `config/ai.php`:

```php
'intent' => [
    'classifier_enabled' => (bool) env('AI_CHAT_INTENT_CLASSIFIER_ENABLED', false),
    'classifier_provider' => env('AI_CHAT_INTENT_CLASSIFIER_PROVIDER', 'gemini'),
    'classifier_timeout_seconds' => (int) env('AI_CHAT_INTENT_CLASSIFIER_TIMEOUT_SECONDS', 3),
    'classifier_retry_times' => (int) env('AI_CHAT_INTENT_CLASSIFIER_RETRY_TIMES', 0),
    'classifier_confidence_threshold' => (float) env('AI_CHAT_INTENT_CLASSIFIER_CONFIDENCE_THRESHOLD', 0.80),
    'classifier_max_question_length' => (int) env('AI_CHAT_INTENT_CLASSIFIER_MAX_QUESTION_LENGTH', 240),
    'classifier_cache_ttl_seconds' => (int) env('AI_CHAT_INTENT_CLASSIFIER_CACHE_TTL_SECONDS', 3600),
],
```

Env:

```env
AI_CHAT_INTENT_CLASSIFIER_ENABLED=false
AI_CHAT_INTENT_CLASSIFIER_PROVIDER=gemini
AI_CHAT_INTENT_CLASSIFIER_TIMEOUT_SECONDS=3
AI_CHAT_INTENT_CLASSIFIER_RETRY_TIMES=0
AI_CHAT_INTENT_CLASSIFIER_CONFIDENCE_THRESHOLD=0.80
AI_CHAT_INTENT_CLASSIFIER_MAX_QUESTION_LENGTH=240
AI_CHAT_INTENT_CLASSIFIER_CACHE_TTL_SECONDS=3600
```

Quy tac:

- Mac dinh `enabled=false`.
- Timeout ngan hon chat answer.
- Retry mac dinh `0` de tranh double cost khi classifier loi.
- Neu doi config tren moi truong cache config, chay `php artisan config:clear`.

#### Buoc 4 - Them DTO classifier

Tao file:

- `backend/app/Services/Ai/Dto/ChatIntentClassificationResult.php`

DTO goi y:

```php
<?php

namespace App\Services\Ai\Dto;

use App\Enums\Ai\ChatIntent;

readonly class ChatIntentClassificationResult
{
    public function __construct(
        public ChatIntent $intent,
        public float $confidence,
        public string $strategy,
        public ?string $reason = null,
    ) {}
}
```

`strategy`:

- `llm`
- `embedding`
- `fallback`

Khong dua `reason` ra API response; chi dung debug/log noi bo neu can.

#### Buoc 5 - Them classifier contract

Tao interface:

- `backend/app/Services/Ai/Contracts/ChatIntentClassifier.php`

```php
<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\ChatIntentClassificationResult;

interface ChatIntentClassifier
{
    public function classify(string $question): ChatIntentClassificationResult;
}
```

Dang ky binding trong service provider neu can:

- Neu `AI_CHAT_INTENT_CLASSIFIER_PROVIDER=gemini`, bind sang `GeminiChatIntentClassifier`.
- Neu disabled, router khong goi classifier nen binding co the van ton tai nhung khong dung.

#### Buoc 6 - Them `GeminiChatIntentClassifier`

Tao file:

- `backend/app/Services/Ai/GeminiChatIntentClassifier.php`

Trach nhiem:

- Goi Gemini voi prompt ngan chi de classify.
- Yeu cau JSON strict.
- Parse JSON.
- Validate enum intent.
- Validate confidence `0..1`.
- Neu loi/timeout/parse fail, return `Unknown`, confidence `0`, strategy `fallback`.

Prompt nguyen tac:

```text
Classify the user's Vietnamese ecommerce bookstore chatbot message.
Return only JSON:
{"intent":"...", "confidence":0.0}

Allowed intents:
book.search
book.detail
book.recommendation
small_talk.greeting
small_talk.status_check
small_talk.thanks
small_talk.goodbye
small_talk.capability
unsupported.order
unsupported.payment
unsupported.refund
unsupported.account
unsupported.non_book_product
unknown

Rules:
- Use book.* if the user asks about books, authors, genres, prices, stock, reviews, or recommendations.
- Use small_talk.* only for pure conversational messages.
- Use unsupported.* for order/payment/refund/account/non-book product requests.
- If unsure, use unknown.
```

Ghi chu:

- Khong dua chat history vao classifier trong Phase 3 dau tien.
- Khong dua retrieved context vao classifier.
- Khong cho classifier sinh user-facing answer.
- Khong thay the RAG/prompt; chi route.

#### Buoc 7 - Tich hop classifier vao `ChatIntentRouter`

Cap nhat `ChatIntentRouter`:

1. Chay rule nhu Phase 2.
2. Neu rule tra short-circuit -> return ngay.
3. Neu rule tra book intent -> return ngay, khong classifier.
4. Neu rule tra `Unknown`:
   - Neu classifier disabled -> return `Unknown`, not short-circuit.
   - Neu question dai hon `classifier_max_question_length` -> return `Unknown`, not short-circuit.
   - Neu enabled -> goi classifier.

Mapping classifier result:

| Classifier result | Confidence | Router behavior |
| --- | --- | --- |
| `small_talk.*` | >= threshold | short-circuit voi fixed response tu router |
| `unsupported.*` | >= threshold | short-circuit voi fixed response tu router |
| `book.*` | >= threshold | not short-circuit, pipeline cu |
| `unknown` | bat ky | not short-circuit, pipeline cu |
| any intent | < threshold | not short-circuit, pipeline cu |

Quan trong: confidence thap khong duoc hoi clarify trong Phase 3 dau tien. Giu fallback ve pipeline cu de chatbot khong bi "nhat".

#### Buoc 8 - Them cache cho classifier

De giam cost/latency, cache theo normalized question:

```text
ai:intent_classifier:{sha1(normalized_question)}
```

TTL:

- `AI_CHAT_INTENT_CLASSIFIER_CACHE_TTL_SECONDS`, mac dinh 3600.

Cache value:

```json
{
  "intent": "small_talk.status_check",
  "confidence": 0.93,
  "strategy": "llm"
}
```

Quy tac:

- Chi cache successful parsed classification.
- Khong cache exception/fallback parse fail neu muon retry sau.
- Neu cache store loi, log warning va degrade khong cache.

#### Buoc 9 - Logging noi bo

Phase 3 van khong doi API response.

Co 2 lua chon logging:

Lua chon A - Khong doi schema:

- Log debug/warning khi classifier loi.
- Dung `ai_chat_messages.retrieval_strategy` nhu hien tai, khong luu intent.
- Dung audit command de suy luan sau.

Lua chon B - Them schema rieng sau khi classifier on:

- Them cot nullable vao `ai_chat_messages`:
  - `intent`
  - `intent_strategy`
  - `intent_confidence`
- Cap nhat `backend/docs/database_schema.md`.

Khuyen nghi:

- Phase 3 dau tien dung Lua chon A de giam rui ro.
- Neu can report intent cho admin, lam migration trong subphase 3B rieng.

#### Buoc 10 - Tests cho classifier

Unit tests:

- `tests/Unit/Ai/GeminiChatIntentClassifierTest.php`
- `tests/Unit/Ai/ChatIntentRouterClassifierTest.php`

Test classifier:

| Case | Expected |
| --- | --- |
| Gemini returns valid JSON small talk | result intent + confidence |
| Gemini returns valid JSON unsupported | result intent + confidence |
| Gemini returns unknown enum | fallback unknown |
| Gemini returns invalid JSON | fallback unknown |
| Gemini timeout/exception | fallback unknown |
| confidence outside 0..1 | fallback unknown |

Test router integration:

| Case | Expected |
| --- | --- |
| Rule small talk matched | classifier not called |
| Rule book intent matched | classifier not called |
| Unknown + classifier disabled | pipeline unknown |
| Unknown + classifier small_talk high confidence | short-circuit |
| Unknown + classifier unsupported high confidence | short-circuit |
| Unknown + classifier book high confidence | pipeline |
| Unknown + classifier low confidence | pipeline |
| Question too long | classifier not called |
| Cached classification | no external call |

Feature tests:

- `ChatTest.php` them case unknown paraphrase small talk neu classifier enabled.
- Assert `Http::assertSent()` chi cho classifier call neu co fake route rieng.
- Assert user response contract khong doi.

Can can than: neu dung cung `GeminiClient` endpoint chat cho classifier va answer, tests phai fake dung pattern de phan biet classifier call voi answer call.

#### Buoc 11 - Manual smoke test

Voi classifier disabled:

- Toan bo behavior Phase 2 khong doi.

Voi classifier enabled trong local/staging:

| Cau hoi | Ky vong |
| --- | --- |
| `alo ban oi nghe duoc khong` | small talk/status neu rule khong bat |
| `bot oi minh hoi chut` | small talk/greeting hoac unknown tuy confidence |
| `minh can xem tinh trang giao hang` | unsupported.order neu confidence cao |
| `co truyen nao nhe nhang de doc cuoi tuan khong` | book.recommendation va di pipeline |
| `toi dang phan van` | unknown, di pipeline cu hoac no-context, khong short-circuit |

#### Buoc 12 - Rollout

1. Merge classifier code voi `AI_CHAT_INTENT_CLASSIFIER_ENABLED=false`.
2. Chay full tests.
3. Bat local/staging, manual smoke test.
4. Bat cho demo noi bo trong thoi gian ngan.
5. Doc latency/cost/log loi.
6. Neu on, moi can nhac bat production.

Dieu kien rollback:

- Tang latency chat ro ret.
- Gemini quota/cost tang khong chap nhan.
- Classifier short-circuit nham cau hoi sach.
- User feedback down tang.

Rollback:

```env
AI_CHAT_INTENT_CLASSIFIER_ENABLED=false
```

Sau do:

```bash
php artisan config:clear
```

#### Buoc 13 - Tieu chi nghiem thu Phase 3

Phase 3 hoan thanh khi:

- Classifier mac dinh tat bang config.
- Khi tat, behavior Phase 2 va tests Phase 2 khong doi.
- Khi bat, classifier chi chay cho `Unknown`/eligible messages.
- Classifier loi/timeout/invalid JSON khong lam API fail.
- Low confidence khong short-circuit.
- High-confidence small talk/unsupported co the short-circuit.
- High-confidence book intent van di pipeline cu.
- Khong expose intent trong API response.
- Tests unit/feature lien quan pass.

#### Buoc 14 - Nhung viec khong lam trong Phase 3 dau tien

Khong lam:

- Khong tool-calling/action qua chatbot.
- Khong cho classifier sinh answer.
- Khong dung history/context de classify.
- Khong bat classifier cho moi request.
- Khong clarify manh voi `unknown`.
- Khong them long-term memory.
- Khong thay RAG retriever bang classifier.

## 7. Test plan

### 7.1. Feature tests phase 1

Them vao `backend/tests/Feature/Ai/ChatTest.php`:

| Question | Expected |
| --- | --- |
| `alo, ban co nghe thay toi khong` | direct response, khong goi Gemini |
| `ban con do khong` | direct response, khong goi Gemini |
| `chao ban` | direct response, khong goi Gemini |
| `cam on` | direct response, khong goi Gemini |
| `ban lam duoc gi` | direct response, khong goi Gemini |

Assert chung:

- response OK
- `data.sources = []`
- `meta.model = null`
- `meta.retrieval.strategy = none`
- `meta.retrieval.matched = false`
- `meta.evaluation = null`
- `Http::assertNothingSent()`
- Redis history rong
- DB co `ai_chat_messages`
- `retrieval_strategy = none`
- `retrieval_matched = false`
- `error_code = null`

### 7.2. Regression tests chong bat nham

| Question | Expected |
| --- | --- |
| `alo, tim sach ky nang giao tiep giup toi` | di pipeline chat/RAG hien tai |
| `cam on, goi y them sach tuong tu di` | khong dung o thanks; di follow-up/RAG neu co context |
| `ban co sach nao hay khong` | book query, khong status_check |
| `Bookify co ban sach ve dien thoai khong` | co book intent, khong bi `non_book_product` chan sai |
| cau khong ro intent nhung co the la nhu cau doc | `unknown`, di pipeline cu trong MVP |

### 7.3. Unit tests phase 2

Neu tach `ChatIntentRouter`, them unit test rieng:

- normalize tieng Viet co dau/khong dau
- match greeting/status/thanks/goodbye/capability
- match unsupported order/payment/refund/private account/non-book
- book intent override small talk khi cau co yeu cau tim sach
- unknown khi cau khong thuoc nhom nao

## 8. Acceptance criteria

Phase 1 duoc xem la xong khi:

- "alo, ban co nghe thay toi khong" tra loi tu nhien.
- Khong goi Gemini/RAG cho direct small talk/status.
- Khong tra no-context message cho small talk/status.
- Case co intent sach van di pipeline cu.
- `php artisan test tests/Feature/Ai/ChatTest.php` pass.

Phase 2 duoc xem la xong khi:

- Intent routing co class/DTO/enum rieng.
- `ChatbotService` doc de hieu hon, khong con tron "scope guard" voi "small talk".
- Unit test router pass.
- Feature tests phase 1 van pass.

Phase 3 chi duoc xem xet khi:

- Co bang chung tu feedback/log rang rule router van route sai nhieu.
- Da uoc luong latency/chi phi external classifier.
- Co config tat/bat classifier.
- Co fallback `unknown` khi classifier loi.

## 9. Rui ro va cach giam

| Rui ro | Anh huong | Cach giam |
| --- | --- | --- |
| Hard-code qua nhieu phrase | Kho maintain, van sot case | Chi giu high-confidence; phase 2 tach router; phase 3 classifier tuy chon |
| Bat nham cau hoi sach thanh small talk | User khong nhan goi y sach | Book-intent override va regression tests |
| Them classifier lam tang latency/cost | Chat cham va ton API | Chua bat mac dinh; chi chay khi rule khong ro; timeout ngan |
| Doi API meta lam frontend lech | UI loi neu contract thay doi | Phase 1 khong doi contract; phase 2 neu them meta phai co contract test |
| Mojibake tieng Viet trong PHP file | Response hien thi loi dau tieng Viet | Phase 1 co the dung ASCII khong dau; task rieng neu can sua encoding toan bo |

## 10. Thu tu thuc hien de xuat

1. Phase 1: sua loi hien tai trong guard hien co.
2. Them regression tests chong bat nham intent sach.
3. Chay `php artisan test tests/Feature/Ai/ChatTest.php`.
4. Kiem tra UX voi cau "alo, ban co nghe thay toi khong".
5. Neu pass va UX dat yeu cau, release hotfix.
6. Phase 2: tach `ChatIntentRouter` rieng som trong mot PR/commit rieng.
7. Theo doi logs/feedback direct intent.
8. Chi can phase 3 neu co du bang chung rule khong du.

## 11. Cau hoi can chot truoc khi code

1. Direct small talk co nen append vao Redis history khong?

   De it thay doi hanh vi, de xuat khong append trong phase 1, giong luong out-of-scope hien tai.

2. Response co nen dung tieng Viet co dau trong PHP file khong?

   Neu file hien dang mojibake, de xuat dung ASCII khong dau trong Phase 1. Sau khi hotfix pass test, tao task rieng de chuan hoa UTF-8 va chuyen response sang tieng Viet co dau. Khong nen vua sua routing vua sua encoding trong cung mot lan.

3. Co can expose `intent` trong API meta khong?

   De xuat chua expose trong phase 1 de khong doi contract frontend. Neu can debug admin ve sau, them trong phase 2.
