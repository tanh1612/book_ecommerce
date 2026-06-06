# Kich ban demo he thong goi y sach

Tai lieu nay mo ta cach demo he thong recommendation voi du lieu demo hien tai.

## 1. Muc tieu demo

Chung minh he thong co the:

- Goi y sach pho bien cho khach chua dang nhap.
- Fallback ve goi y pho bien cho thanh vien chua du tin hieu ca nhan.
- Goi y ca nhan hoa cho thanh vien co hanh vi ro rang.
- Chi tra sach dang hoat dong va con ton kho kha dung.
- Doc ket qua tu cache/job precompute, khong tinh scoring nang trong request hien thi feed.

## 2. Du lieu demo hien tai

Tai khoan demo:

| Vai tro demo | Email | Mat khau | Ky vong feed |
| --- | --- | --- | --- |
| Thanh vien co so thich A | `demo.recommendation.a@example.com` | `Demo@123456` | `content_based` |
| Thanh vien co so thich B | `demo.recommendation.b@example.com` | `Demo@123456` | `content_based` |
| Thanh vien cold-start | `demo.recommendation.cold@example.com` | `Demo@123456` | `popular` fallback |

So lieu hien tai sau khi seed:

| Nhom du lieu | So luong |
| --- | ---: |
| Demo accounts | 3 |
| Addresses | 3 |
| Orders | 72 |
| Completed orders | 64 |
| Cancelled orders | 4 |
| Refund expired orders | 4 |
| Order items | 72 |
| Tong so sach trong completed orders | 112 |
| View events | 10 |
| Cart add events | 4 |
| Wishlist rows | 4 |
| Approved reviews | 2 |

Mot so sach co tin hieu mua pho bien cao:

| Book ID | Ten sach | So luong ban demo |
| ---: | --- | ---: |
| 1740 | Nguoi Vot Xac | 17 |
| 1756 | Danh Tac Viet Nam - Doi Mat - Nam Cao | 17 |
| 1755 | Danh Tac Viet Nam - Buoc Duong Cung - Nguyen Cong Hoan | 16 |
| 1754 | Cuoc Doi Va Su Nghiep Cua Dai Su Huyen Trang | 16 |
| 1753 | Ban Chat Cua Y Thuc | 12 |

Ghi chu: so lieu tren phan anh snapshot hien tai. Neu chay lai seed sau khi catalog thay doi, danh sach sach cu the co the thay doi, nhung kich ban demo van giu nguyen.

## 3. Chuan bi truoc khi demo

1. Dam bao backend dang chay.
2. Dam bao da chay seed demo theo dung command cua du an.
3. Dam bao cache recommendation da duoc warm sau seed hoac job build da chay.
4. Mo cong cu test API, vi du Postman, Insomnia, Thunder Client, hoac frontend neu da ket noi API.

Endpoint chinh:

```http
GET /api/v1/recommendations?limit=10
```

Endpoint dang nhap:

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "demo.recommendation.a@example.com",
  "password": "Demo@123456"
}
```

## 4. Luong demo 1: Khach chua dang nhap

Muc tieu: chung minh cold-start cho guest.

Thao tac:

1. Khong dang nhap.
2. Goi API:

```http
GET /api/v1/recommendations?limit=10
```

Ket qua ky vong:

- HTTP 200.
- `data` co danh sach sach.
- `meta.feed = for_you`.
- `meta.strategy = popular`.

Loi thuyet trinh:

> Khi nguoi dung chua dang nhap, he thong khong co lich su ca nhan. Vi vay feed dung chien luoc popular, duoc build san tu don hang completed, rating, review count va recency. Request chi doc cache va loc lai ton kho, nen toc do nhanh.

## 5. Luong demo 2: Thanh vien cold-start

Muc tieu: chung minh thanh vien dang nhap nhung chua du tin hieu se fallback popular.

Thao tac:

1. Dang nhap bang:

```json
{
  "email": "demo.recommendation.cold@example.com",
  "password": "Demo@123456"
}
```

2. Goi:

```http
GET /api/v1/recommendations?limit=10
```

Ket qua ky vong:

- HTTP 200.
- `meta.strategy = popular`.

Loi thuyet trinh:

> Tai khoan nay co don hang trong du lieu demo, nhung khong co tap tin hieu ca nhan manh nhu view/cart/wishlist/review duoc thiet ke cho personalized feed. Khi user cache khong du dieu kien, service fallback ve popular de feed khong bi rong.

## 6. Luong demo 3: Thanh vien co ca nhan hoa

Muc tieu: chung minh personalized recommendation.

Thao tac voi user A:

1. Dang nhap bang:

```json
{
  "email": "demo.recommendation.a@example.com",
  "password": "Demo@123456"
}
```

2. Goi:

```http
GET /api/v1/recommendations?limit=10
```

Ket qua ky vong:

- HTTP 200.
- `meta.strategy = content_based`.
- Danh sach sach khac voi guest/cold-start o mot so vi tri.

Thao tac voi user B:

1. Dang nhap bang:

```json
{
  "email": "demo.recommendation.b@example.com",
  "password": "Demo@123456"
}
```

2. Goi cung endpoint recommendation.

Ket qua ky vong:

- `meta.strategy = content_based`.
- Feed co the khac user A vi seed chon nhom category/persona khac.

Loi thuyet trinh:

> Voi user A va B, seed tao du tin hieu: view, cart_add, wishlist, purchase va approved review. Job nen da build cache `reco:user:{account_id}` theo content-based. He thong lay cac sach co cung category/author voi sach user da tuong tac, sau do blend nhe voi popularity de tranh ket qua qua heo hut.

## 7. Luong demo 4: Loc ton kho va trang thai sach

Muc tieu: giai thich recommendation khong chi dua vao diem, ma con loc dieu kien kinh doanh.

Noi dung can noi:

- Feed chi tra `books.is_active = true`.
- Feed chi tra sach co ton kho kha dung:

```text
available_stock = SUM(GREATEST(quantity - reserved_quantity, 0))
```

- Sau seed, cac don `completed` da tru `inventories.quantity` va tang `inventories.sold_quantity`.
- Don `cancelled` va `refund_expired` khong tinh la ban thanh cong nen khong tru ton kho cuoi cung.

Loi thuyet trinh:

> Diem recommendation chi la buoc dau. Truoc khi tra ve API, service van load sach hien tai va loc lai active/stock. Cach nay tranh viec cache cu de xuat sach da het hang hoac da bi an khoi catalog.

## 8. Luong demo 5: Giai thich vi sao popular co ket qua

Muc tieu: lien he du lieu don hang voi feed popular.

Bang chung du lieu:

- Co 64 don completed.
- Tong so sach trong completed orders la 112.
- Cac sach co so luong mua demo cao gom:
  - `1740 - Nguoi Vot Xac`: 17
  - `1756 - Danh Tac Viet Nam - Doi Mat - Nam Cao`: 17
  - `1755 - Danh Tac Viet Nam - Buoc Duong Cung - Nguyen Cong Hoan`: 16
  - `1754 - Cuoc Doi Va Su Nghiep Cua Dai Su Huyen Trang`: 16
  - `1753 - Ban Chat Cua Y Thuc`: 12

Loi thuyet trinh:

> Popular feed khong phai random. No lay tin hieu tu don completed, ket hop rating/review count va yeu to moi cua sach. Cac sach co nhieu don completed se co diem cao hon, nhung van bi loc bo neu het hang hoac inactive.

## 9. Luong demo 6: Giai thich vi sao content-based co ket qua

Muc tieu: lien he hanh vi user voi feed ca nhan hoa.

User A va B deu co:

- 5 view events.
- 2 cart_add events.
- 2 wishlist rows.
- 1 approved review.
- Lich su mua completed.

Loi thuyet trinh:

> Moi loai tin hieu co trong so khac nhau. Wishlist, purchase va review la strong signals, view/cart_add la tin hieu nhe hon. He thong dung cac sach da tuong tac lam seed, sau do tim sach cung category/author. Sach user vua mua gan day bi loai de tranh de xuat lap lai.

## 10. Cau hoi thuong gap khi demo

### Vi sao khong dung collaborative filtering?

Du lieu that cua do an chua du day dac de tao ma tran user-item chat luong. Collaborative filtering de bi sparse va kho giai thich. MVP dung weighted content-based vi:

- Phu hop khi du lieu tuong tac con it.
- De giai thich trong bao cao.
- Co the fallback popular an toan.
- Khong can tinh nang trong request.

### Vi sao can cache/job?

Tinh diem recommendation co join orders, reviews, interactions, category, author. Neu tinh truc tiep moi request se cham va kho scale. He thong build san bang job:

- `reco:popular`
- `reco:user:{account_id}`

Request API chi doc cache, load book hien tai, loc stock/active va tra resource.

### Neu user moi hoan toan thi sao?

User moi khong du tin hieu se thay popular feed. Day la hanh vi mong muon, vi feed khong bi rong.

### Neu cache user co sach het hang thi sao?

Read path van loc stock lan cuoi. Neu personalized feed bi thieu sau loc, service top-up tu popular cache va chong trung book id.

## 11. Checklist demo nhanh

1. Goi recommendations khi chua dang nhap, chi ra `strategy = popular`.
2. Dang nhap cold-start account, chi ra van la `popular`.
3. Dang nhap user A, chi ra `strategy = content_based`.
4. Dang nhap user B, chi ra `strategy = content_based` va ket qua co the khac user A.
5. Mo DB/admin order, chi ra 64 completed orders la nguon du lieu popular.
6. Mo interactions/wishlist/review cua user A/B, chi ra nguon du lieu personalized.
7. Mo inventory cua mot sach popular, chi ra completed orders da lam giam `quantity` va tang `sold_quantity`.
8. Ket luan: he thong co fallback, ca nhan hoa, loc ton kho va precompute bang job/cache.

## 12. Loi ket luan khi thuyet trinh

> He thong recommendation trong do an tap trung vao tinh on dinh va kha nang giai thich. Khach va user moi se thay popular feed; user co du hanh vi se thay content-based feed. Moi ket qua deu duoc tinh truoc bang job va cache, request chi doc ket qua va loc dieu kien kinh doanh nhu active/ton kho. Cach tiep can nay phu hop voi du lieu hien tai cua website ban sach va co the mo rong bang viec them tin hieu hoac dieu chinh trong so sau nay.
