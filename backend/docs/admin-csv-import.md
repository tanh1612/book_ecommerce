# CSV import (Filament admin)

## Filament runtime (queue & transaction)

- Filament 4 xử lý CSV theo **chunk** (mặc định 100 dòng/chunk); mỗi chunk chạy trong **job hàng đợi** (cần worker `queue:work` / Horizon).
- Job `ImportCsv` bọc toàn bộ chunk trong **một `DB::transaction`**: cập nhật `processed_rows` / `successful_rows` và ghi `failed_import_rows` cùng lúc khi chunk kết thúc.
- Dòng lỗi validation / `RowImportFailedException` được bắt trong job và ghi vào bảng `failed_import_rows` (không làm fail cả chunk trừ khi có lỗi không bắt được).
- Không cần khai báo `shouldQueue` trên class `Importer`: hành vi queue là mặc định của action import. Có thể tùy biến `chunkSize` / `maxRows` trên `ImportAction` và `getJobQueue()` trên importer nếu muốn queue riêng.

## Cột & quy tắc (tóm tắt)

| Importer | Ghi chú |
|----------|---------|
| **BookImporter** | `supplier` (tên) bắt buộc phải khớp NCC; `publisher` nếu có cột thì tên phải khớp NXB; `authors` từng tên phải tồn tại (không gán nếu thiếu); `categories` breadcrumb `A > B` khớp chuỗi breadcrumb trong DB; `sku` unique; URL ảnh nếu có mà upload Cloudinary lỗi → dòng import **thất bại**. |
| **AuthorImporter** | `name` bắt buộc; `email` nullable nhưng nếu có phải unique. |
| **PublisherImporter** / **SupplierImporter** | `name` unique; `email` nullable nhưng nếu có phải unique. |

## Xem lỗi sau import

- Vào **Hệ thống → Lịch sử nhập CSV**, mở một phiên import → tab **Dòng lỗi** (payload JSON + `validation_error`).
- Filament cũng gửi **database notification** khi import xong; nếu có dòng lỗi, thông báo dùng màu cảnh báo.
