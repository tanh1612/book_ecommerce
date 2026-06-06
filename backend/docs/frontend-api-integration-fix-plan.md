# Ke hoach xu ly cac ton dong FE va lien ket API

Ngay tao: 2026-06-06

Tai lieu nay ghi lai cac van de con ton dong sau dot kiem tra muc do hoan thien FE va do lien ket voi backend qua API. Muc tieu la chia viec thanh tung hang muc ro rang de co the sua, test va nghiem thu theo thu tu uu tien.

## Tong quan hien trang

- Backend da co 48 route API `api/v1` cho cac luong khach hang chinh.
- FE da noi tot cac luong loi: auth, profile, address book, catalog, search/filter, banner, flash sale, cart, checkout, shipping quote, order, refund, wishlist, AI chat.
- FE build production da chay duoc voi `npm run build`.
- FE lint hien con fail vi `process` trong `vite.config.js`; ngoai ra co mot so warning hook/fast-refresh.
- Cac van de lon con lai chu yeu nam o contract field, route FE thieu, va mot so backend API da san sang nhung FE chua tieu thu.

## Uu tien P0 - Can sua truoc khi demo/thanh toan that

### 1. Them trang ket qua thanh toan VNPay

Trang thai: Da trien khai ngay 2026-06-06.

Hien trang:
- Backend `VnPayReturnController` redirect ve `FRONTEND_URL/payment-result?...`.
- FE chua khai bao route `/payment-result`, nen sau khi thanh toan co nguy co roi vao 404.

Pham vi sua:
- Them page moi: `frontend/src/pages/Payment/PaymentResultPage.jsx`.
- Them route trong `frontend/src/App.jsx`.
- Doc query params: `status`, `order_id`, `message`.
- Hien thi ket qua thanh toan thanh cong/that bai, nut ve don hang va nut tiep tuc mua sam.
- Neu `order_id` ton tai va user da dang nhap, co the goi `orderApi.getOrderDetail(order_id)` de hien tom tat.

File du kien:
- `frontend/src/App.jsx`
- `frontend/src/pages/Payment/PaymentResultPage.jsx`
- Co the dung lai `orderApi.js`.

Kiem thu:
- Truy cap thu `/payment-result?status=paid&order_id=1`.
- Truy cap thu `/payment-result?status=failed&message=...`.
- Chay `npm run build`.

Ghi chu trien khai:
- Da them `frontend/src/pages/Payment/PaymentResultPage.jsx`.
- Da them route `payment-result` trong `frontend/src/App.jsx`.
- Da cho `ProfilePage` doc query `tab=orders` de nut "Xem don hang" mo dung tab.

### 2. Sua mismatch field o trang chi tiet sach

Trang thai: Da trien khai ngay 2026-06-06.

Hien trang:
- Backend tra `detail.num_pages`, `detail.weight`, `images[].image_url`.
- FE dang doc `detail.page_count`, `detail.weight_grams`, `img.url`.
- Ket qua: so trang, khoi luong va gallery anh co the hien "Dang cap nhat" hoac khong doi anh.

Pham vi sua:
- Doi mapping trong `ProductDetailPage.jsx`:
  - `book.detail?.num_pages`
  - `book.detail?.weight`
  - `img.image_url`
- Dung `resolveMediaUrl` cho anh chinh va anh gallery.
- Fallback gallery nen dung `{ image_url: book.thumbnail_url }`.
- Uu tien hien `format_label` va `language_label` neu can hien label tieng Viet.

File du kien:
- `frontend/src/pages/ProductDetail/ProductDetailPage.jsx`

Kiem thu:
- Mo mot sach co nhieu anh.
- Hover/click thumbnail de doi anh.
- Kiem tra so trang va khoi luong hien dung theo API.
- Chay `npm run build`.

Ghi chu trien khai:
- Da doi mapping sang `detail.num_pages`, `detail.weight`, `images[].image_url`.
- Da dung `resolveMediaUrl` cho anh chinh va gallery.
- Da sua fallback gallery sang `image_url`.

## Uu tien P1 - Hoan thien trai nghiem mua hang

### 3. Hoan thien review UI va API

Trang thai: Da trien khai FE ngay 2026-06-06.

Hien trang:
- Backend da co:
  - `GET /api/v1/books/{slug}/reviews`
  - `GET /api/v1/books/{slug}/review-eligibility`
  - `POST /api/v1/account/order-items/{orderItem}/review`
- FE chua goi cac API nay.
- Nut "Danh gia" trong don hang hien chi toast tinh nang dang phat trien.

Pham vi sua:
- Them `reviewApi.js` hoac bo sung vao `bookApi.js`/`orderApi.js`.
- Trang chi tiet sach:
  - Load danh sach review da duyet.
  - Load eligibility neu user da dang nhap.
  - Hien rating summary, review list, empty state.
- Trang don hang:
  - Khi don `completed` va item `can_review`, mo modal danh gia.
  - Submit `rating`, `comment` qua API.
  - Sau khi submit thanh cong, refresh order detail/list.

File du kien:
- `frontend/src/services/reviewApi.js`
- `frontend/src/pages/ProductDetail/ProductDetailPage.jsx`
- `frontend/src/pages/Profile/MyOrders.jsx`
- Co the them component: `frontend/src/components/Review/ReviewList.jsx`, `ReviewFormModal.jsx`.

Kiem thu:
- Sach chua co review hien empty state.
- Sach co review hien danh sach dung.
- Order completed co item `can_review` hien nut danh gia.
- Submit review thanh cong thi nut bien mat hoac item cap nhat trang thai.
- Submit lan hai phai bi chan bang UI hoac 422 tu backend.

Ghi chu trien khai:
- Da them `frontend/src/services/reviewApi.js`.
- Da them review list va eligibility notice vao `ProductDetailPage`.
- Da thay nut review gia trong `MyOrders` bang modal submit review that qua `POST /api/v1/account/order-items/{orderItem}/review`.
- Da sua `AccountOrderItemReviewTest` dung guard `web`, khop voi middleware account routes hien tai.
- Da reset `backend_testing` va xac minh `BookReviewsListTest`, `AccountOrderItemReviewTest` pass khi chay tuan tu.

### 4. Dung recommendation API that tren trang chu

Trang thai: Da trien khai FE ngay 2026-06-06.

Hien trang:
- Backend co `GET /api/v1/recommendations`.
- FE trang chu dang lay `bookApi.getBooks({ per_page: 20 })` roi cat `slice(0, 10)` lam "Goi y cho ban".
- Backend co tracking `POST /api/v1/recommendations/interactions/books/{book}/view`, FE chua goi.

Pham vi sua:
- Them `recommendationApi.js`:
  - `getRecommendations({ limit })`
  - `trackBookView(bookId, source)`
- Trang chu:
  - Dung API recommendation cho section "Goi y cho ban".
  - Fallback ve catalog neu recommendation loi hoac rong.
- Product detail:
  - Khi load sach thanh cong va user dang nhap, goi track view.
  - Can debounce/deduplicate theo `book.id` trong session de tranh spam API.

File du kien:
- `frontend/src/services/recommendationApi.js`
- `frontend/src/components/Home/HomePage.jsx`
- `frontend/src/pages/ProductDetail/ProductDetailPage.jsx`

Kiem thu:
- Guest nhan feed popular recommendation.
- Member nhan feed personalized neu backend co data.
- Mo product detail tao interaction view.
- Neu API recommendation loi, trang chu van hien sach fallback.

Ghi chu trien khai:
- Da them `frontend/src/services/recommendationApi.js`.
- Trang chu goi `GET /api/v1/recommendations?limit=10` cho section "Goi y cho ban".
- Neu API loi/rong, FE fallback ve catalog list nhu cu.
- Product detail track view cho user dang nhap, co dedupe theo `sessionStorage`.

### 5. Hoan thien wishlist payload hoac mapping FE

Trang thai: Da trien khai backend + FE ngay 2026-06-06.

Hien trang:
- `WishlistController@index` dang tra `BookSuggestionResource`, chi co `id`, `name`, `slug`, `thumbnail_url`.
- FE wishlist page can `selling_price`, `original_price`, `authors`.
- Ket qua: wishlist co the hien gia `0` hoac "Dang cap nhat".
- `WishlistController@store` chi tra `message`, FE dang co fallback append local book nen tam chay nhung contract khong dep.

Huong sua khuyen nghi:
- Backend nen doi wishlist list sang resource day du hon, vi day la man hinh danh sach san pham.
- Tao hoac dung `BookSummaryResource` cho wishlist, eager load `authors`, `images`, `inventories` neu can stock.
- `store` nen tra lai item vua them hoac toan bo list sau khi them de FE update chac chan.

File du kien backend:
- `backend/app/Http/Controllers/Api/V1/Account/WishlistController.php`
- `backend/app/Services/Account/WishlistService.php`
- Co the dung `BookSummaryResource`.

File du kien frontend:
- `frontend/src/context/WishlistContext.jsx`
- `frontend/src/Wishlist/WishlistPage.jsx`

Kiem thu:
- Wishlist list hien dung anh, ten, gia, tac gia.
- Them vao wishlist cap nhat state dung sau response.
- Xoa wishlist update state va count tren header.
- Chay feature test wishlist backend neu co: `php artisan test --filter=WishlistTest`.

Ghi chu trien khai:
- `WishlistController@index` da doi sang `BookSummaryResource::collection`.
- `WishlistController@store` da tra ve `data` la sach vua them, kem `message` de giu contract cu.
- `WishlistService` da eager load `authors`, `images`, `inventories`.
- `WishlistContext` da append item tu response `data` neu backend tra ve object.
- `WishlistPage` da dung `resolveMediaUrl` cho anh.
- `php -l` pass cho controller/service wishlist.
- `WishlistTest` pass sau khi reset `backend_testing`.

## Uu tien P2 - Dieu huong, chat luong code va van hanh

### 6. Flash Sale tren HomePage

Hien trang:
- Trang chu hien Flash Sale nhu mot section trong `HomePage`.
- Backend hien co `GET /api/v1/flash-sales/active` tra ve campaign va `items`.

Pham vi da chot:
- Khong tao route/page Flash Sale rieng.
- Khong hien link/nut "Xem tat ca".
- Section an neu khong co campaign active hoac `items` rong.
- Header section giu logic `FLASH SALE` + countdown, danh sach ben duoi dung lai `ProductSlider`/`ProductCard`.
- Countdown dung `end_at` tu campaign; neu thieu `end_at` moi fallback ve cuoi ngay.
- UI chi chinh cau truc/hanh vi, giu palette, spacing, radius, shadow va typography theo frontend hien tai.

File:
- `frontend/src/components/Home/HomePage.jsx`
- `frontend/src/components/Home/FlashSaleTimer.jsx`
- `frontend/src/components/Product/ProductSlider.jsx`

Kiem thu:
- Home co campaign active thi hien section Flash Sale.
- Home khong co campaign active hoac `items` rong thi an section.
- Khong con link `/sach-khuyen-mai`/nut "Xem tat ca" trong Flash Sale section.
- UI van theo style tong the hien tai.

### 7. Sua ESLint fail do `process` trong `vite.config.js`

Hien trang:
- `npm run lint` fail tai `frontend/vite.config.js` vi ESLint dang dung browser globals, khong co `process`.

Phuong an:
- Them config override Node cho `vite.config.js` trong `eslint.config.js`:
  - `files: ['vite.config.js']`
  - `globals: globals.node`
- Hoac doi `process.env.VITE_BACKEND_URL` sang cach Vite-friendly neu phu hop.

File du kien:
- `frontend/eslint.config.js`
- Co the can `frontend/vite.config.js`.

Kiem thu:
- Chay `npm run lint`.
- Warning hook co the xu ly sau, nhung lint khong nen fail.

Ghi chu trien khai:
- Da them Node globals override cho `vite.config.js`.

### 8. Xu ly cac warning React Hooks va Fast Refresh

Hien trang:
- Co warning missing dependency trong `Header`, `WishlistContext`, `RegisterPage`, `ForgotPasswordPage`, `CartPage`, `CheckoutPage`.
- Co warning `react-refresh/only-export-components` trong cac context.

Pham vi sua:
- Boc callback bang `useCallback` tai cac noi dependency that su can on dinh.
- Dung override hep cho context files de xu ly warning fast-refresh, tranh refactor lon.
- Kiem tra ky de tranh tao loop fetch moi.

File du kien:
- `frontend/src/components/Layout/Header.jsx`
- `frontend/src/context/AuthContext.jsx`
- `frontend/src/context/CartContext.jsx`
- `frontend/src/context/WishlistContext.jsx`
- `frontend/src/pages/Auth/RegisterPage.jsx`
- `frontend/src/pages/Auth/ForgotPasswordPage.jsx`
- `frontend/src/pages/Cart/CartPage.jsx`
- `frontend/src/pages/Checkout/CheckoutPage.jsx`

Kiem thu:
- Chay `npm run lint`.
- Test lai login/logout, cart, wishlist, checkout de dam bao khong tao fetch loop.

Ghi chu trien khai:
- Da on dinh callback/effect trong Header, Cart/Wishlist context, Cart, Checkout, Register va Forgot Password.
- Context fast-refresh warning duoc tat bang override hep `src/context/*Context.jsx`.

### 9. Lam ro cau hinh API base URL cho production

Hien trang:
- `axiosClient` dung `baseURL: '/api'`.
- Dev co proxy `/api` va `/sanctum` ve backend.
- Production can reverse proxy tu FE host sang Laravel, neu khong `/api` se tro ve host FE.

Phuong an:
- Neu deploy FE cung domain/reverse proxy: ghi ro cau hinh Nginx/Apache trong README/deploy docs.
- Neu deploy FE khac domain: cho phep `VITE_API_BASE_URL` va `VITE_BACKEND_ORIGIN`:
  - API base co the la `https://api.example.com/api`.
  - CSRF call can dung backend origin `https://api.example.com/sanctum/csrf-cookie`.
- Dam bao CORS `supports_credentials: true`, Sanctum stateful domains va session domain khop.

File du kien:
- `frontend/src/services/axiosClient.js`
- `frontend/src/utils/media.js`
- `frontend/vite.config.js`
- Tai lieu deploy neu co.
- Backend `.env`/config chi cap nhat khi trien khai, khong hardcode.

Kiem thu:
- Dev local van chay qua proxy.
- Production/staging call duoc CSRF, login, account profile.
- Cookie session duoc gui kem request.

Ghi chu trien khai:
- `axiosClient` dung `VITE_API_BASE_URL`, default `/api`.
- CSRF dung `VITE_BACKEND_ORIGIN` hoac `VITE_API_ORIGIN`; neu khong set env thi dung `/sanctum/csrf-cookie` qua dev proxy.
- `utils/media.js` dong bo origin theo `VITE_BACKEND_ORIGIN`/`VITE_API_ORIGIN`, default `http://127.0.0.1:8000`.

### 10. Loai bo hoac thay the `locationApi.js` goi API ben ngoai truc tiep

Hien trang:
- `frontend/src/services/locationApi.js` chua duoc import trong FE hien tai.
- File nay hardcode token API tinh/thanh pho o frontend, khong nen giu neu khong dung.
- FE hien da dung `addressApi` qua backend proxy `/api/v1/locations`.

Pham vi sua:
- Neu khong dung: xoa file sau khi xac nhan khong co import.
- Neu con can: doi sang backend proxy va khong dua token ra FE.

File du kien:
- `frontend/src/services/locationApi.js`
- `frontend/src/services/addressApi.js`

Kiem thu:
- `rg "locationApi" frontend/src` khong con import.
- Address book va checkout van load provinces/wards dung.

Ghi chu trien khai:
- Da xoa `frontend/src/services/locationApi.js`; `addressApi` tiep tuc di qua backend proxy `/api/v1/locations`.

## De xuat thu tu thuc hien

1. P0-1: `/payment-result`.
2. P0-2: field mapping product detail.
3. P2-7: sua lint fail nhanh de co baseline.
4. P1-5: wishlist payload.
5. P1-3: review UI/API.
6. P1-4: recommendation feed va tracking.
7. P2-6: flash sale route/link.
8. P2-8: cleanup warning hooks.
9. P2-9: production API config.
10. P2-10: cleanup `locationApi.js`.

## Checklist nghiem thu tong

- `npm run build` pass.
- `npm run lint` khong fail.
- `php artisan route:list --path=api/v1` van hien day du route.
- Cac feature test backend lien quan pass:
  - `php artisan test --filter=CartTest`
  - `php artisan test --filter=CheckoutTest`
  - `php artisan test --filter=WishlistTest`
  - `php artisan test --filter=RecommendationFeedTest`
  - `php artisan test --filter=BookReviewsListTest`
  - `php artisan test --filter=AccountOrderItemReviewTest`
  - `php artisan test --filter=VnPayPaymentTest`
- Manual smoke test FE:
  - Guest xem catalog, search, detail, add cart.
  - Member login, wishlist, checkout COD.
  - Member checkout VNPay va quay ve `/payment-result`.
  - Member xem order, huy order pending, gui thong tin refund khi du dieu kien.
- Member review order item completed.
- AI chat gui cau hoi va feedback.

## Ghi chu test DB sau khi bo migration bang thua

- Cac migration cache/personal_access_tokens da bi xoa; `failed_jobs` va `job_batches` van duoc giu qua migration jobs hien tai. Test nen dung `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `QUEUE_FAILED_DRIVER=database-uuids`, `SESSION_DRIVER=array`.
- Neu chay nhieu nhom feature test Laravel song song tren cung MySQL database `backend_testing`, `RefreshDatabase` co the tranh nhau drop/migrate va gay loi kieu `migrations table doesn't exist`, `table already exists`, hoac `unknown table ...`. Chay cac nhom test lien quan theo thu tu hoac tach database/worker khi parallel.
- Neu database test da bi trang thai loi, reset bang `php artisan migrate:fresh --env=testing` truoc khi chay lai test.

## Ghi chu

- Khi sua cac endpoint lien quan `accounts`, `orders`, `order_items`, `reviews`, `wishlists`, `book_interaction_events`, can doi chieu `backend/docs/database_schema.md` truoc khi them query/migration.
- Uu tien giu contract API bang `JsonResource`; neu FE can them field, nen cap nhat resource ro rang thay vi chen logic doan chuoi trong FE.
- Khong dua API token ben thu ba vao frontend. Cac proxy co key nen nam o backend.
