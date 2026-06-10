# Bookify - Website Nhà Sách Tích Hợp Hệ Thống Đề Xuất và Trợ Lý Ảo

> Khóa luận tốt nghiệp - Trường Đại học Thăng Long  
> Đề tài: Website nhà sách tích hợp hệ thống đề xuất và trợ lý ảo (Bookify)

Ngôn ngữ / Language: [Tiếng Việt](#phần-1-tiếng-việt) | [English](#part-2-english)

---

# Phần 1. Tiếng Việt

## Mục lục

- [Giới thiệu](#giới-thiệu)
- [Live Demo](#live-demo)
- [Tính năng chính](#tính-năng-chính)
- [Công nghệ sử dụng](#công-nghệ-sử-dụng)
- [Kiến trúc hệ thống](#kiến-trúc-hệ-thống)
- [Yêu cầu cài đặt](#yêu-cầu-cài-đặt)
- [Cài đặt và cấu hình](#cài-đặt-và-cấu-hình)
- [Cấu hình dữ liệu, tìm kiếm và AI](#cấu-hình-dữ-liệu-tìm-kiếm-và-ai)
- [Chạy ứng dụng](#chạy-ứng-dụng)
- [Cấu trúc thư mục](#cấu-trúc-thư-mục)
- [Các lệnh Artisan hữu ích](#các-lệnh-artisan-hữu-ích)
- [Tài khoản demo](#tài-khoản-demo)
- [Kiểm thử và chất lượng mã nguồn](#kiểm-thử-và-chất-lượng-mã-nguồn)
- [Ghi chú bảo mật](#ghi-chú-bảo-mật)
- [Hướng phát triển](#hướng-phát-triển)
- [Thành viên thực hiện](#thành-viên-thực-hiện)
- [Giấy phép](#giấy-phép)

## Giới thiệu

Bookify là nền tảng thương mại điện tử chuyên về sách, được xây dựng theo mô hình tách biệt giữa Backend API và Frontend SPA. Hệ thống hỗ trợ quy trình mua sắm trực tuyến từ duyệt sách, tìm kiếm, giỏ hàng, thanh toán, quản lý đơn hàng đến đánh giá sản phẩm.

Dự án đồng thời tích hợp hệ thống đề xuất sách và trợ lý ảo AI sử dụng RAG nhằm hỗ trợ người dùng tìm kiếm, lựa chọn và khám phá sách phù hợp hơn.

## Live Demo

| Mục            | URL                                 |
| -------------- | ----------------------------------- |
| Website        | `https://bookify.io.vn/`            |
| Trang quản trị | `https://bookify.io.vn/admin/login` |
| API Base URL   | `https://bookify.io.vn/api/v1`      |

## Tính năng chính

### Người dùng

- Đăng ký, đăng nhập và xác thực bằng Laravel Sanctum.
- Duyệt danh mục sách, xem chi tiết sách, tìm kiếm và lọc sản phẩm.
- Quản lý giỏ hàng, wishlist và địa chỉ giao hàng.
- Đặt hàng, thanh toán VNPay và theo dõi trạng thái đơn hàng.
- Đánh giá sản phẩm sau khi mua hàng.
- Nhận gợi ý sách dựa trên hành vi và nội dung sách.
- Sử dụng chatbot AI để hỏi thông tin sách theo ngữ cảnh.

### Quản trị viên

- Quản lý sách, danh mục, tác giả, nhà xuất bản, nhà cung cấp và tồn kho.
- Quản lý đơn hàng, thanh toán, hoàn tiền thủ công và đánh giá.
- Quản lý khuyến mãi, banner và dữ liệu vận hành.
- Theo dõi thống kê tổng quan trong trang quản trị Filament.
- Chạy các tác vụ nền liên quan đến tìm kiếm, đề xuất, thanh toán và AI/RAG.

### AI, RAG và Recommendation

- Trợ lý ảo sử dụng Gemini API kết hợp Meilisearch để truy xuất dữ liệu sách.
- Đồng bộ tài liệu RAG và vector embedding cho sách.
- Hệ thống đề xuất theo hành vi người dùng.
- Content-based recommendation cho người dùng có lịch sử tương tác.
- Popular fallback cho khách hoặc người dùng cold-start.
- Audit/report các tương tác chatbot để hỗ trợ đánh giá chất lượng.

## Công nghệ sử dụng

### Frontend

| Công nghệ        | Phiên bản / Ghi chú |
| ---------------- | ------------------- |
| React            | `^18.3.1`           |
| Vite             | `^5.4.10`           |
| TailwindCSS      | `^4.2.2`            |
| React Router DOM | `^7.13.1`           |
| Axios            | `^1.16.1`           |
| React Toastify   | `^11.1.0`           |
| React Icons      | `^5.6.0`            |
| Swiper           | `^12.1.2`           |

### Backend

| Công nghệ          | Phiên bản / Ghi chú |
| ------------------ | ------------------- |
| PHP                | `^8.2`              |
| Laravel            | `^12.0`             |
| Laravel Sanctum    | `^4.0`              |
| Laravel Scout      | `^11.2`             |
| Filament           | `^4.0`              |
| Meilisearch PHP    | `^1.16`             |
| Predis             | `^3.4`              |
| DomPDF             | `^3.1`              |
| Cloudinary Laravel | `^3.0`              |
| Pest               | `^4.4`              |

### Database và Infrastructure

| Thành phần        | Vai trò                                                                       |
| ----------------- | ----------------------------------------------------------------------------- |
| MySQL / MariaDB   | Cơ sở dữ liệu chính                                                           |
| Redis             | Cache, queue, chatbot history, registration token                             |
| Meilisearch       | Search engine và vector index cho RAG                                         |
| Laravel Queue     | Xử lý job nền cho search, AI/RAG, recommendation và các tác vụ vận hành       |
| Laravel Scheduler | Lên lịch tự động cho promotion, thanh toán quá hạn, recommendation và AI sync |

### Third-party APIs

| Dịch vụ                  | Vai trò                               |
| ------------------------ | ------------------------------------- |
| Google Gemini API        | Chat model và embedding model         |
| VNPay Sandbox/Production | Thanh toán trực tuyến                 |
| Cloudinary               | Lưu trữ và phân phối ảnh              |
| Tỉnh Thành Phố API       | Dữ liệu địa giới hành chính           |
| VietQR Banks API         | Danh sách ngân hàng phục vụ hoàn tiền |

## Kiến trúc hệ thống

Bookify được xây dựng theo mô hình tách biệt giữa frontend và backend. Frontend sử dụng React/Vite để xây dựng giao diện SPA và giao tiếp với backend thông qua REST API. Backend sử dụng Laravel để xử lý nghiệp vụ, xác thực, đơn hàng, thanh toán, quản trị, tìm kiếm và tích hợp AI.

MySQL hoặc MariaDB được dùng làm cơ sở dữ liệu chính. Redis được sử dụng cho cache, queue và một số dữ liệu tạm. Meilisearch đảm nhiệm tìm kiếm nâng cao và lưu trữ vector phục vụ RAG. Gemini API được sử dụng cho chatbot và embedding dữ liệu sách. Cloudinary phục vụ lưu trữ ảnh, còn VNPay xử lý thanh toán trực tuyến.

```text
User Browser
    |
    v
React/Vite Frontend
    |
    v
Laravel REST API
    |
    +--> MySQL / MariaDB
    +--> Redis
    +--> Meilisearch
    +--> Gemini API
    +--> VNPay
    +--> Cloudinary
```

## Yêu cầu cài đặt

Cài đặt trước các phần mềm sau:

- PHP `8.2+`
- Composer `2+`
- Node.js `18+`
- npm `9+`
- MySQL `8+` hoặc MariaDB tương thích
- Redis Server
- Meilisearch Server, ưu tiên bản hỗ trợ vector search hoặc user-provided embedder
- Git

Một số PHP extensions thường cần bật:

```bash
pdo_mysql
mbstring
openssl
tokenizer
xml
ctype
json
curl
fileinfo
```

## Cài đặt và cấu hình

### 1. Clone project

```bash
git clone <repository-url>
cd book_ecommerce
```

### 2. Cài đặt backend

```bash
cd backend
composer install
```

Tạo file môi trường:

```bash
# Windows PowerShell
Copy-Item .env.example .env

# macOS/Linux
cp .env.example .env
```

Sinh application key:

```bash
php artisan key:generate
```

Cấu hình các biến quan trọng trong `backend/.env`:

```env
APP_NAME=Bookify
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

FRONTEND_URL=http://localhost:5173
CORS_ALLOWED_ORIGINS="${FRONTEND_URL}"
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,::1

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backend
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your_meilisearch_key

GEMINI_API_KEY=your_gemini_api_key
GEMINI_CHAT_MODEL=gemini-2.5-flash-lite
GEMINI_EMBEDDING_MODEL=gemini-embedding-2

VNP_TMN_CODE=your_vnpay_tmn_code
VNP_HASH_SECRET=your_vnpay_hash_secret
VNP_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNP_RETURN_URL="${APP_URL}/api/v1/payments/vnpay/return"
VNP_IPN_URL="${APP_URL}/api/v1/payments/vnpay/ipn"

CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

Nếu sử dụng local storage:

```bash
php artisan storage:link
```

### 3. Cài đặt frontend

```bash
cd frontend
npm install
```

Frontend mặc định gọi API qua Vite proxy:

```text
/api      -> http://127.0.0.1:8000
/sanctum  -> http://127.0.0.1:8000
```

Nếu cần override backend target khi chạy Vite:

```bash
# Windows PowerShell
$env:VITE_BACKEND_URL="http://127.0.0.1:8000"
npm run dev

# macOS/Linux
VITE_BACKEND_URL=http://127.0.0.1:8000 npm run dev
```

Các biến frontend có thể dùng khi deploy riêng domain:

```env
VITE_API_BASE_URL=/api
VITE_BACKEND_ORIGIN=http://127.0.0.1:8000
VITE_API_ORIGIN=http://127.0.0.1:8000
```

## Cấu hình dữ liệu, tìm kiếm và AI

### Cách 1: Khởi tạo schema sạch bằng migration và seeder

```bash
cd backend
php artisan migrate:fresh --seed
php artisan db:seed --class=ShippingMethodSeeder
```

Lệnh trên tạo:

- Tài khoản admin mặc định.
- Bảng dữ liệu hệ thống.
- Phương thức vận chuyển mặc định.

Lưu ý: catalog sách không được seed mặc định trong `DatabaseSeeder`. Để test recommendation hoặc RAG, cần có dữ liệu sách, danh mục, tác giả, tồn kho và quan hệ sách.

### Cách 2: Nạp dữ liệu demo từ backup có sẵn

Repo có file dữ liệu mẫu tại:

```text
backend/database/backup/data.sql
```

Có thể nạp sau khi migrate schema:

```bash
cd backend
php artisan migrate:fresh
mysql -u root -p backend < database/backup/data.sql
```

Sau khi nạp backup, có thể chạy lại command recommendation demo để chuẩn hóa tài khoản demo và làm nóng cache:

```bash
php artisan recommendations:seed-demo-data
```

### Meilisearch Index Setup

Đồng bộ index settings từ Laravel Scout:

```bash
cd backend
php artisan scout:sync-index-settings
```

Import toàn bộ sách active vào Meilisearch:

```bash
php artisan scout:import "App\Models\Book"
```

Cấu hình vector embedder cho RAG:

```bash
php artisan ai:meilisearch-configure
```

Có thể kiểm tra payload mà chưa gọi Meilisearch:

```bash
php artisan ai:meilisearch-configure --dry-run
```

Đồng bộ tài liệu RAG và vector embedding cho toàn bộ sách active:

```bash
php artisan ai:sync-book-rag-documents --all
```

Một số lệnh hữu ích khác:

```bash
php artisan ai:sync-book-rag-documents --all --missing-vectors
php artisan ai:sync-book-rag-documents --book-id=1
php artisan ai:sync-book-rag-documents --pending
php artisan ai:rag:coverage
```

### Recommendation Setup

Seed dữ liệu demo cho hệ thống đề xuất:

```bash
cd backend
php artisan recommendations:seed-demo-data
```

Mặc định command tạo 3 tài khoản demo với mật khẩu:

```text
Demo@123456
```

Có thể đổi mật khẩu demo:

```bash
php artisan recommendations:seed-demo-data --password=YourPassword123
```

Build recommendation cache:

```bash
php artisan recommendations:build-popular
php artisan recommendations:build-users
```

Build recommendation cho một tài khoản cụ thể:

```bash
php artisan recommendations:build-user {account_id}
```

## Chạy ứng dụng

Cần chạy song song nhiều terminal.

### Terminal 0: Redis

```bash
redis-server
```

### Terminal 0.1: Meilisearch

```bash
meilisearch --master-key=your_meilisearch_key
```

Đảm bảo `MEILISEARCH_KEY` trong `.env` trùng với `--master-key`.

### Terminal 1: Backend API và Filament Admin

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

- Backend API: `http://127.0.0.1:8000/api/v1`
- Filament Admin: `http://127.0.0.1:8000/admin`

### Terminal 2: Queue Worker

```bash
cd backend
php artisan queue:work redis --queue=default,ai-rag-sync --tries=3
```

Queue worker cần chạy để xử lý:

- Đồng bộ Meilisearch.
- Recommendation jobs.
- AI/RAG sync jobs.
- Các job nền khác của Laravel.

### Terminal 3: Frontend

```bash
cd frontend
npm run dev
```

Frontend mặc định chạy tại:

```text
http://localhost:5173
```

### Terminal 4: Scheduler tùy chọn

```bash
cd backend
php artisan schedule:work
```

Scheduler hiện cấu hình các tác vụ:

- `payments:expire-vnpay` mỗi 5 phút.
- `promotions:sync-status` mỗi phút.
- `orders:expire-manual-refunds` hằng ngày.
- `inventory:notify-low-stock` hằng ngày.
- `recommendations:build-popular` mỗi 6 giờ.
- `recommendations:build-users` hằng giờ.
- `recommendations:prune-interactions` hằng ngày.
- `ai:sync-book-rag-documents --pending` hằng ngày lúc 02:30.

## Cấu trúc thư mục

```text
book_ecommerce/
├── backend/
│   ├── app/
│   │   ├── Console/Commands/       # Artisan commands: AI, RAG, Recommendation, Payment, Inventory
│   │   ├── Enums/                  # Enum nghiệp vụ
│   │   ├── Filament/               # Admin panel resources, pages, widgets
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/ # REST API cho frontend
│   │   │   └── Resources/          # API response resources
│   │   ├── Jobs/                   # Queue jobs cho search, AI và recommendation
│   │   ├── Models/                 # Eloquent models
│   │   ├── Observers/              # Đồng bộ search/RAG khi dữ liệu thay đổi
│   │   ├── Policies/               # Authorization policies
│   │   ├── Services/               # Business logic
│   │   └── Support/                # Helper/domain support classes
│   ├── config/
│   │   ├── ai.php                  # Gemini, RAG, chatbot config
│   │   ├── scout.php               # Meilisearch/Laravel Scout config
│   │   └── queue.php               # Redis queue config
│   ├── database/
│   │   ├── migrations/             # Database schema
│   │   ├── seeders/                # Admin và dữ liệu vận chuyển
│   │   └── backup/data.sql         # Dữ liệu demo mẫu
│   └── routes/
│       ├── api.php                 # API v1 routes
│       └── console.php             # Scheduled commands
│
└── frontend/
    ├── src/
    │   ├── assets/                 # Logo, favicon, VNPay assets
    │   ├── components/             # UI components, layout, chatbot, product cards
    │   ├── context/                # Auth, Cart, Wishlist contexts
    │   ├── hooks/                  # Custom React hooks
    │   ├── pages/                  # Auth, Cart, Checkout, Product, Profile, Wishlist pages
    │   ├── routes/                 # App router
    │   ├── services/               # Axios client và API modules
    │   ├── styles/                 # Theme, component, vendor styles
    │   └── utils/                  # Formatter, media, validation helpers
    ├── vite.config.js              # Vite + React + Tailwind + API proxy
    └── package.json
```

## Các lệnh Artisan hữu ích

### Recommendation

```bash
php artisan recommendations:seed-demo-data
php artisan recommendations:seed-demo-data --password=Demo@123456
php artisan recommendations:build-popular
php artisan recommendations:build-users
php artisan recommendations:build-users --recent-days=30
php artisan recommendations:build-user {account_id}
php artisan recommendations:prune-interactions
```

### AI / RAG Chatbot

```bash
php artisan ai:meilisearch-configure
php artisan ai:meilisearch-configure --dry-run
php artisan ai:sync-book-rag-documents --all
php artisan ai:sync-book-rag-documents --pending
php artisan ai:sync-book-rag-documents --book-id=1
php artisan ai:sync-book-rag-documents --all --from-id=100 --limit=50
php artisan ai:rag:coverage
php artisan ai:rag:coverage --json
php artisan ai:chatbot:report
php artisan ai:chatbot:intent-audit --days=7 --limit=20
php artisan ai:chatbot:prune
```

### Operation

```bash
php artisan payments:expire-vnpay
php artisan orders:expire-manual-refunds
php artisan promotions:sync-status
php artisan inventory:notify-low-stock
php artisan locations:clear-cache
```

## Tài khoản demo

Các tài khoản bên dưới phục vụ mục đích kiểm thử tính năng đề xuất trên môi trường demo hoặc production demo.

| Persona         | Email                                  | Mật khẩu mặc định | Kỳ vọng đề xuất  |
| --------------- | -------------------------------------- | ----------------- | ---------------- |
| Demo Reader A   | `demo.recommendation.a@example.com`    | `Demo@123456`     | Content-based    |
| Demo Reader B   | `demo.recommendation.b@example.com`    | `Demo@123456`     | Content-based    |
| Demo Cold Start | `demo.recommendation.cold@example.com` | `Demo@123456`     | Popular fallback |

API test nhanh:

```bash
GET /api/v1/recommendations?limit=10
```

- Guest: trả về strategy `popular`.
- Demo A/B sau khi đăng nhập: trả về strategy `content_based`.
- Cold account sau khi đăng nhập: trả về strategy `popular`.

### Tài khoản admin mặc định cho môi trường local/demo

Tài khoản bên dưới được tạo từ dữ liệu mẫu hoặc seeder, dùng để giảng viên/người đánh giá đăng nhập trang quản trị khi chạy dự án ở môi trường local hoặc demo sau khi import database.

| Vai trò    | Email             | Mật khẩu mặc định |
| ---------- | ----------------- | ----------------- |
| Admin Demo | `admin@gmail.com` | `12345678`        |

Lưu ý: Đây không phải tài khoản admin ở môi trường production.

## Kiểm thử và chất lượng mã nguồn

Backend:

```bash
cd backend
php artisan test
./vendor/bin/pint
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

## Ghi chú bảo mật

- Không commit file `.env` hoặc các secret thật lên repository.
- Không công khai tài khoản quản trị production trong README.
- Tài khoản admin mặc định trong README chỉ dùng cho local/demo.
- Nên đổi mật khẩu admin mặc định sau khi import dữ liệu lên production.
- Các API key như Gemini, VNPay, Cloudinary và Meilisearch master key phải được lưu trong biến môi trường.
- Khi test VNPay IPN ở local, cần public URL như ngrok để VNPay gọi được IPN endpoint.
- Frontend sử dụng Sanctum cookie auth, vì vậy cần cấu hình đúng `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS` và `SANCTUM_STATEFUL_DOMAINS`.

## Hướng phát triển

- Bổ sung dashboard phân tích doanh thu, hành vi người dùng và hiệu quả recommendation.
- Nâng cấp recommendation bằng collaborative filtering hoặc hybrid recommendation.
- Cải thiện RAG chatbot bằng đánh giá tự động và dữ liệu mô tả sách đầy đủ hơn.
- Docker hóa toàn bộ hệ thống để triển khai nhất quán giữa local và production.
- Bổ sung CI/CD để tự động test, build và deploy.
- Tích hợp monitoring/logging cho queue, scheduler, API và chatbot.

## Thành viên thực hiện

| Họ tên         | Vai trò             |
| -------------- | ------------------- |
| Trịnh Tuấn Anh | Phát triển hệ thống |
| Lê Minh Quân   | Phát triển hệ thống |

Giảng viên hướng dẫn: ThS. Ngô Mạnh Cường

## Giấy phép

Dự án được xây dựng phục vụ mục đích học tập và nghiên cứu trong khuôn khổ khóa luận tốt nghiệp tại Trường Đại học Thăng Long.

---

# Part 2. English

## Table of Contents

- [Introduction](#introduction)
- [Live Demo](#live-demo-1)
- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [System Architecture](#system-architecture)
- [Prerequisites](#prerequisites)
- [Installation and Configuration](#installation-and-configuration)
- [Data, Search, and AI Setup](#data-search-and-ai-setup)
- [Running the Application](#running-the-application)
- [Project Structure](#project-structure)
- [Useful Artisan Commands](#useful-artisan-commands)
- [Demo Accounts](#demo-accounts)
- [Testing and Code Quality](#testing-and-code-quality)
- [Security Notes](#security-notes)
- [Future Improvements](#future-improvements)
- [Contributors](#contributors)
- [License](#license)

## Introduction

Bookify is an e-commerce platform for books, designed with a separated Backend API and Frontend SPA architecture. The system supports the full online shopping flow, including browsing books, searching, managing carts, checkout, payment, order tracking, and product reviews.

The project also integrates a book recommendation system and an AI virtual assistant powered by RAG to help users search for, select, and discover relevant books more effectively.

## Live Demo

| Item         | URL                              |
| ------------ | -------------------------------- |
| Website      | `https://your-domain.com`        |
| Admin Panel  | `https://your-domain.com/admin`  |
| API Base URL | `https://your-domain.com/api/v1` |

## Key Features

### Customer Features

- Register, log in, and authenticate using Laravel Sanctum.
- Browse categories, view book details, search, filter, and sort products.
- Manage shopping cart, wishlist, and shipping addresses.
- Place orders, pay via VNPay, and track order status.
- Review purchased products.
- Receive book recommendations based on behavior and book content.
- Use an AI chatbot to ask contextual questions about books.

### Admin Features

- Manage books, categories, authors, publishers, suppliers, and inventory.
- Manage orders, payments, manual refunds, and reviews.
- Manage promotions, banners, and operational data.
- Monitor overview statistics in the Filament admin panel.
- Run background tasks for search, recommendation, payment, and AI/RAG processing.

### AI, RAG, and Recommendation

- AI assistant powered by Gemini API and Meilisearch retrieval.
- RAG document and vector embedding synchronization for books.
- User behavior-based recommendation.
- Content-based recommendation for users with interaction history.
- Popular fallback strategy for guests and cold-start users.
- Chatbot audit/report commands for quality evaluation.

## Technology Stack

### Frontend

| Technology       | Version / Note |
| ---------------- | -------------- |
| React            | `^18.3.1`      |
| Vite             | `^5.4.10`      |
| TailwindCSS      | `^4.2.2`       |
| React Router DOM | `^7.13.1`      |
| Axios            | `^1.16.1`      |
| React Toastify   | `^11.1.0`      |
| React Icons      | `^5.6.0`       |
| Swiper           | `^12.1.2`      |

### Backend

| Technology         | Version / Note |
| ------------------ | -------------- |
| PHP                | `^8.2`         |
| Laravel            | `^12.0`        |
| Laravel Sanctum    | `^4.0`         |
| Laravel Scout      | `^11.2`        |
| Filament           | `^4.0`         |
| Meilisearch PHP    | `^1.16`        |
| Predis             | `^3.4`         |
| DomPDF             | `^3.1`         |
| Cloudinary Laravel | `^3.0`         |
| Pest               | `^4.4`         |

### Database and Infrastructure

| Component         | Purpose                                                                             |
| ----------------- | ----------------------------------------------------------------------------------- |
| MySQL / MariaDB   | Primary relational database                                                         |
| Redis             | Cache, queue, chatbot history, registration token                                   |
| Meilisearch       | Search engine and vector index for RAG                                              |
| Laravel Queue     | Background jobs for search, AI/RAG, recommendation, and operations                  |
| Laravel Scheduler | Automated scheduling for promotions, expired payments, recommendations, and AI sync |

### Third-party APIs

| Service                  | Purpose                                 |
| ------------------------ | --------------------------------------- |
| Google Gemini API        | Chat model and embedding model          |
| VNPay Sandbox/Production | Online payment                          |
| Cloudinary               | Image storage and delivery              |
| Tinh Thanh Pho API       | Vietnamese administrative location data |
| VietQR Banks API         | Bank list for refund support            |

## System Architecture

Bookify follows a separated frontend and backend architecture. The frontend is built with React/Vite as a SPA and communicates with the backend through REST APIs. The backend is built with Laravel and handles business logic, authentication, orders, payment, administration, search, and AI integration.

MySQL or MariaDB is used as the primary database. Redis is used for cache, queues, and temporary data. Meilisearch powers advanced search and vector storage for RAG. Gemini API is used for chatbot responses and book embeddings. Cloudinary handles image storage, while VNPay processes online payments.

```text
User Browser
    |
    v
React/Vite Frontend
    |
    v
Laravel REST API
    |
    +--> MySQL / MariaDB
    +--> Redis
    +--> Meilisearch
    +--> Gemini API
    +--> VNPay
    +--> Cloudinary
```

## Prerequisites

Install the following software before running the project:

- PHP `8.2+`
- Composer `2+`
- Node.js `18+`
- npm `9+`
- MySQL `8+` or compatible MariaDB version
- Redis Server
- Meilisearch Server, preferably a version supporting vector search or user-provided embedder
- Git

Common PHP extensions:

```bash
pdo_mysql
mbstring
openssl
tokenizer
xml
ctype
json
curl
fileinfo
```

## Installation and Configuration

### 1. Clone the project

```bash
git clone <repository-url>
cd book_ecommerce
```

### 2. Backend setup

```bash
cd backend
composer install
```

Create the environment file:

```bash
# Windows PowerShell
Copy-Item .env.example .env

# macOS/Linux
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure important variables in `backend/.env`:

```env
APP_NAME=Bookify
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

FRONTEND_URL=http://localhost:5173
CORS_ALLOWED_ORIGINS="${FRONTEND_URL}"
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,::1

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backend
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your_meilisearch_key

GEMINI_API_KEY=your_gemini_api_key
GEMINI_CHAT_MODEL=gemini-2.5-flash-lite
GEMINI_EMBEDDING_MODEL=gemini-embedding-2

VNP_TMN_CODE=your_vnpay_tmn_code
VNP_HASH_SECRET=your_vnpay_hash_secret
VNP_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNP_RETURN_URL="${APP_URL}/api/v1/payments/vnpay/return"
VNP_IPN_URL="${APP_URL}/api/v1/payments/vnpay/ipn"

CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
```

If local storage is used:

```bash
php artisan storage:link
```

### 3. Frontend setup

```bash
cd frontend
npm install
```

The frontend calls the API through Vite proxy by default:

```text
/api      -> http://127.0.0.1:8000
/sanctum  -> http://127.0.0.1:8000
```

To override the backend target while running Vite:

```bash
# Windows PowerShell
$env:VITE_BACKEND_URL="http://127.0.0.1:8000"
npm run dev

# macOS/Linux
VITE_BACKEND_URL=http://127.0.0.1:8000 npm run dev
```

Frontend variables for separate-domain deployment:

```env
VITE_API_BASE_URL=/api
VITE_BACKEND_ORIGIN=http://127.0.0.1:8000
VITE_API_ORIGIN=http://127.0.0.1:8000
```

## Data, Search, and AI Setup

### Option 1: Create a fresh schema with migrations and seeders

```bash
cd backend
php artisan migrate:fresh --seed
php artisan db:seed --class=ShippingMethodSeeder
```

This creates:

- Default admin account.
- System database tables.
- Default shipping methods.

Note: the book catalog is not seeded by default in `DatabaseSeeder`. To test recommendation or RAG, book, category, author, inventory, and relationship data are required.

### Option 2: Import demo data from backup

A sample data file is available at:

```text
backend/database/backup/data.sql
```

Import it after creating the schema:

```bash
cd backend
php artisan migrate:fresh
mysql -u root -p backend < database/backup/data.sql
```

After importing the backup, run the recommendation demo command to normalize demo accounts and warm up cache:

```bash
php artisan recommendations:seed-demo-data
```

### Meilisearch Index Setup

Sync index settings from Laravel Scout:

```bash
cd backend
php artisan scout:sync-index-settings
```

Import all active books into Meilisearch:

```bash
php artisan scout:import "App\Models\Book"
```

Configure vector embedder for RAG:

```bash
php artisan ai:meilisearch-configure
```

Preview the payload without calling Meilisearch:

```bash
php artisan ai:meilisearch-configure --dry-run
```

Synchronize RAG documents and vector embeddings for all active books:

```bash
php artisan ai:sync-book-rag-documents --all
```

Other useful commands:

```bash
php artisan ai:sync-book-rag-documents --all --missing-vectors
php artisan ai:sync-book-rag-documents --book-id=1
php artisan ai:sync-book-rag-documents --pending
php artisan ai:rag:coverage
```

### Recommendation Setup

Seed demo data for the recommendation system:

```bash
cd backend
php artisan recommendations:seed-demo-data
```

By default, this command creates 3 demo accounts with the password:

```text
Demo@123456
```

To change the demo password:

```bash
php artisan recommendations:seed-demo-data --password=YourPassword123
```

Build recommendation cache:

```bash
php artisan recommendations:build-popular
php artisan recommendations:build-users
```

Build recommendations for a specific account:

```bash
php artisan recommendations:build-user {account_id}
```

## Running the Application

Run the following processes in separate terminals.

### Terminal 0: Redis

```bash
redis-server
```

### Terminal 0.1: Meilisearch

```bash
meilisearch --master-key=your_meilisearch_key
```

Make sure `MEILISEARCH_KEY` in `.env` matches the `--master-key` value.

### Terminal 1: Backend API and Filament Admin

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

- Backend API: `http://127.0.0.1:8000/api/v1`
- Filament Admin: `http://127.0.0.1:8000/admin`

### Terminal 2: Queue Worker

```bash
cd backend
php artisan queue:work redis --queue=default,ai-rag-sync --tries=3
```

The queue worker is required for:

- Meilisearch synchronization.
- Recommendation jobs.
- AI/RAG sync jobs.
- Other Laravel background jobs.

### Terminal 3: Frontend

```bash
cd frontend
npm run dev
```

The frontend runs at:

```text
http://localhost:5173
```

### Terminal 4: Optional Scheduler

```bash
cd backend
php artisan schedule:work
```

Configured scheduled tasks:

- `payments:expire-vnpay` every 5 minutes.
- `promotions:sync-status` every minute.
- `orders:expire-manual-refunds` daily.
- `inventory:notify-low-stock` daily.
- `recommendations:build-popular` every 6 hours.
- `recommendations:build-users` hourly.
- `recommendations:prune-interactions` daily.
- `ai:sync-book-rag-documents --pending` daily at 02:30.

## Project Structure

```text
book_ecommerce/
├── backend/
│   ├── app/
│   │   ├── Console/Commands/       # Artisan commands: AI, RAG, Recommendation, Payment, Inventory
│   │   ├── Enums/                  # Business enums
│   │   ├── Filament/               # Admin panel resources, pages, widgets
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/ # REST API for frontend
│   │   │   └── Resources/          # API response resources
│   │   ├── Jobs/                   # Queue jobs for search, AI, and recommendation
│   │   ├── Models/                 # Eloquent models
│   │   ├── Observers/              # Search/RAG synchronization on data changes
│   │   ├── Policies/               # Authorization policies
│   │   ├── Services/               # Business logic
│   │   └── Support/                # Helper/domain support classes
│   ├── config/
│   │   ├── ai.php                  # Gemini, RAG, chatbot config
│   │   ├── scout.php               # Meilisearch/Laravel Scout config
│   │   └── queue.php               # Redis queue config
│   ├── database/
│   │   ├── migrations/             # Database schema
│   │   ├── seeders/                # Admin and shipping data
│   │   └── backup/data.sql         # Sample demo data
│   └── routes/
│       ├── api.php                 # API v1 routes
│       └── console.php             # Scheduled commands
│
└── frontend/
    ├── src/
    │   ├── assets/                 # Logo, favicon, VNPay assets
    │   ├── components/             # UI components, layout, chatbot, product cards
    │   ├── context/                # Auth, Cart, Wishlist contexts
    │   ├── hooks/                  # Custom React hooks
    │   ├── pages/                  # Auth, Cart, Checkout, Product, Profile, Wishlist pages
    │   ├── routes/                 # App router
    │   ├── services/               # Axios client and API modules
    │   ├── styles/                 # Theme, component, vendor styles
    │   └── utils/                  # Formatter, media, validation helpers
    ├── vite.config.js              # Vite + React + Tailwind + API proxy
    └── package.json
```

## Useful Artisan Commands

### Recommendation

```bash
php artisan recommendations:seed-demo-data
php artisan recommendations:seed-demo-data --password=Demo@123456
php artisan recommendations:build-popular
php artisan recommendations:build-users
php artisan recommendations:build-users --recent-days=30
php artisan recommendations:build-user {account_id}
php artisan recommendations:prune-interactions
```

### AI / RAG Chatbot

```bash
php artisan ai:meilisearch-configure
php artisan ai:meilisearch-configure --dry-run
php artisan ai:sync-book-rag-documents --all
php artisan ai:sync-book-rag-documents --pending
php artisan ai:sync-book-rag-documents --book-id=1
php artisan ai:sync-book-rag-documents --all --from-id=100 --limit=50
php artisan ai:rag:coverage
php artisan ai:rag:coverage --json
php artisan ai:chatbot:report
php artisan ai:chatbot:intent-audit --days=7 --limit=20
php artisan ai:chatbot:prune
```

### Operation

```bash
php artisan payments:expire-vnpay
php artisan orders:expire-manual-refunds
php artisan promotions:sync-status
php artisan inventory:notify-low-stock
php artisan locations:clear-cache
```

## Demo Accounts

The following accounts are intended for testing recommendation features in a demo or production-demo environment.

| Persona         | Email                                  | Default Password | Expected Recommendation |
| --------------- | -------------------------------------- | ---------------- | ----------------------- |
| Demo Reader A   | `demo.recommendation.a@example.com`    | `Demo@123456`    | Content-based           |
| Demo Reader B   | `demo.recommendation.b@example.com`    | `Demo@123456`    | Content-based           |
| Demo Cold Start | `demo.recommendation.cold@example.com` | `Demo@123456`    | Popular fallback        |

Quick API test:

```bash
GET /api/v1/recommendations?limit=10
```

- Guest: returns `popular` strategy.
- Demo A/B after login: returns `content_based` strategy.
- Cold account after login: returns `popular` strategy.

### Default Admin Account for Local/Demo Environment

The account below is created from the sample data or seeder, allowing instructors or reviewers to access the admin panel when running the project locally or in a demo environment after importing the database.

| Role       | Email             | Default Password |
| ---------- | ----------------- | ---------------- |
| Demo Admin | `admin@gmail.com` | `12345678`       |

Note: This is not admin account in production environment.

## Testing and Code Quality

Backend:

```bash
cd backend
php artisan test
./vendor/bin/pint
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

## Security Notes

- Do not commit `.env` or real secrets to the repository.
- Do not publish production admin credentials in the README.
- The default admin account in the README is intended for local/demo use only.
- Change the default admin password after importing local data into production.
- API keys such as Gemini, VNPay, Cloudinary, and Meilisearch master key must be stored in environment variables.
- When testing VNPay IPN locally, use a public URL such as ngrok so VNPay can call the IPN endpoint.
- The frontend uses Sanctum cookie authentication, so `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, and `SANCTUM_STATEFUL_DOMAINS` must be configured correctly.

## Future Improvements

- Add analytics dashboards for revenue, user behavior, and recommendation performance.
- Improve recommendation with collaborative filtering or hybrid recommendation.
- Improve the RAG chatbot with automated evaluation and more complete book descriptions.
- Dockerize the whole system for consistent local and production deployments.
- Add CI/CD for automated testing, building, and deployment.
- Integrate monitoring/logging for queue, scheduler, API, and chatbot services.

## Contributors

| Name           | Role               |
| -------------- | ------------------ |
| Trịnh Tuấn Anh | System development |
| Lê Minh Quân   | System development |

Supervisor: ThS. Ngô Mạnh Cường

## License

This project was developed for academic and research purposes as part of a graduation thesis at Thang Long University.
