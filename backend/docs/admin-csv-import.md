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
| **InventoryImporter** | CSV gồm `book_sku`, `warehouse_id`, `quantity`, `location_code`; `book_sku` phải khớp sách hiện có; `warehouse_id` phải là kho đang hoạt động; `quantity` là số lượng nhập thêm; khi tạo mới thì `sold_quantity` / `reserved_quantity` mặc định `0`; khi đã có tồn kho thì không đổi `sold_quantity` / `reserved_quantity`; `last_restocked_at` tự lấy thời điểm xử lý dòng import. |
| **AuthorImporter** | `name` bắt buộc; `email` nullable nhưng nếu có phải unique. |
| **PublisherImporter** / **SupplierImporter** | `name` unique; `email` nullable nhưng nếu có phải unique. |

## Xem lỗi sau import

- Filament gửi **database notification** khi import xong; nếu có dòng lỗi, thông báo dùng màu cảnh báo (và thường có hành động tải CSV dòng lỗi nếu cấu hình mặc định).
- Chi tiết từng dòng lỗi vẫn được lưu trong bảng `failed_import_rows` (có thể tra cứu qua DB / công cụ nội bộ nếu cần).
