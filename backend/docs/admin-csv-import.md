# CSV import (Filament admin)

## Filament runtime (queue & transaction)

- Filament 4 xử lý CSV theo chunk; mỗi chunk chạy trong job hàng đợi, nên cần worker `queue:work` / Horizon.
- Job `ImportCsv` bọc chunk trong `DB::transaction`: cập nhật `processed_rows` / `successful_rows` và ghi `failed_import_rows` khi chunk kết thúc.
- Lỗi validation / `RowImportFailedException` được ghi vào `failed_import_rows`; lỗi hệ thống không bắt được có thể làm fail job.
- Không cần khai báo `shouldQueue` trên class `Importer`; đây là hành vi mặc định của Filament import action.

## Upload ảnh sách

- `BookImporter` upload ảnh Cloudinary đồng bộ trong `afterSave()` để dòng import fail ngay nếu không upload được ảnh nào.
- `thumbnail_url` bắt buộc vì sách không có ảnh không đủ điều kiện đưa lên catalog.
- Cột `thumbnail_url` hỗ trợ một hoặc nhiều URL cách nhau bằng whitespace; importer bỏ URL trùng và chỉ lấy tối đa 5 URL.
- Nếu upload được ít nhất một ảnh, dòng import thành công; các URL ảnh lỗi còn lại được ghi log.
- Nếu tất cả URL ảnh đều lỗi, dòng import fail và được ghi vào `failed_import_rows`.
- Public id ảnh import dùng dạng ngắn: `book_ecommerce/books/{book_id}/img-{sort_order}-{ulid}`.

## Cột & quy tắc

| Importer | Ghi chú |
|----------|---------|
| **BookImporter** | `supplier` (tên) bắt buộc phải khớp NCC; `publisher` nếu có thì tên phải khớp NXB; `authors` từng tên phải tồn tại; `categories` breadcrumb `A > B` khớp danh mục trong DB sau chuẩn hóa nhẹ (lowercase, trim, gom whitespace, chuẩn dash `–/—/−` thành `-`, spacing quanh `>` và `-`); không fuzzy, không tự tạo category; nếu hai danh mục DB trùng khóa sau chuẩn hóa thì dòng import fail. `thumbnail_url` bắt buộc; dòng import thành công khi upload được ít nhất một ảnh; `sku` unique. |
| **InventoryImporter** | CSV gồm `book_sku`, `warehouse_id`, `quantity`, `location_code`; `book_sku` phải khớp sách hiện có; `warehouse_id` phải là kho đang hoạt động; `quantity` là số lượng nhập thêm; khi tạo mới thì `sold_quantity` / `reserved_quantity` mặc định `0`; khi đã có tồn kho thì không đổi `sold_quantity` / `reserved_quantity`; `last_restocked_at` lấy thời điểm xử lý dòng import. |
| **AuthorImporter** | `name` bắt buộc; `email` nullable nhưng nếu có phải unique. |
| **PublisherImporter** / **SupplierImporter** | `name` unique; `email` nullable nhưng nếu có phải unique. |

### Quy tắc match `categories`

- Mỗi giá trị trong cột `categories` vẫn được tách bằng dấu phẩy.
- Ưu tiên dùng breadcrumb đầy đủ, ví dụ `Sách Phát Triển Bản Thân > Tâm Lý - Kỹ Năng Sống`.
- Nếu CSV chỉ ghi tên danh mục con, ví dụ `Tâm Lý - Kỹ Năng Sống`, importer sẽ chấp nhận khi tên đó chỉ khớp đúng một danh mục trong hệ thống sau chuẩn hóa.
- Nếu nhiều danh mục ở các nhánh khác nhau có cùng tên sau chuẩn hóa, dòng import sẽ fail và yêu cầu dùng breadcrumb đầy đủ để tránh gán sai danh mục.

## Xem lỗi sau import

- Filament gửi database notification khi import xong; nếu có dòng lỗi, thông báo dùng màu cảnh báo và thường có hành động tải CSV dòng lỗi.
- Chi tiết từng dòng lỗi được lưu trong bảng `failed_import_rows`.
- Lỗi ảnh được log với context `import_id`, `book_id`, `sku`, URL và `sort_order`; nếu không có ảnh nào upload thành công, dòng import được ghi failed.
