# Ke hoach quan ly banner trang chinh

## Trang thai trien khai (2026-06-03)

**Backend: hoan tat** (migration, model, storage, Filament admin, API, cache, observer, tests, schema doc).

**Frontend: chua lam** (`bannerApi.js`, `HomePage.jsx`, `BannerSlider.jsx`).

Tham chieu van hanh:

| Hang muc | Gia tri |
|----------|---------|
| Public API | `GET /api/v1/banners` |
| Cloudinary folder | `book_ecommerce/banners/home` |
| Cache key | `content:banners:home:v1` (TTL 15 phut) |

## Muc tieu

Trang chinh hien dang dung banner hard-code trong frontend. Muc tieu la chuyen banner sang du lieu co the quan ly trong Filament admin va frontend lay qua API public.

## Phan 1: Pham vi giai doan dau

Lam trong giai doan dau:

1. Quan ly danh sach banner trong admin.
2. Upload anh banner.
3. Bat/tat banner.
4. Sap xep thu tu hien thi.
5. Frontend goi API de render slider trang chinh.

Khong lam trong giai doan dau:

1. Gan link dieu huong khi click banner.
2. Len lich hien thi theo thoi gian.
3. Thong ke luot click hoac luot hien thi.
4. A/B testing banner.
5. Phan banner theo nhom nguoi dung.
6. Banner rieng cho tung thiet bi desktop/mobile.

## Phan 2: Database

Tao bang `banners` toi gian cho slider banner trang chinh.

Cot de xuat:

```text
id
title
public_id nullable
image_url
sort_order default 0
is_active default true
created_at
updated_at
```

Index de xuat:

```text
is_active, sort_order
```

Quy tac du lieu:

1. `title` bat buoc de admin de nhan dien banner.
2. `image_url` bat buoc.
3. `is_active` quyet dinh banner co duoc hien thi tren trang chinh hay khong.

## Phan 3: Backend model

Tao `App\Models\Banner`.

Model can co:

1. `$fillable` cho cac cot duoc phep ghi.
2. Cast:
   - `is_active` => `boolean`
3. Scope:
   - `active()`
   - `ordered()`

Logic hien thi:

```text
is_active = true
ORDER BY sort_order ASC, id ASC
```

## Phan 4: Upload anh banner

Tao service rieng:

```text
App\Services\Media\BannerImageStorageService
```

Quy uoc Cloudinary:

```text
book_ecommerce/banners/home/home-banner-{ulid}
```

Service can co:

1. Tao folder banner trang chu.
2. Tao basename anh moi.
3. Tao delivery URL tu `public_id`.
4. Xoa asset cu khi admin thay anh hoac xoa banner.

Khong tron logic nay vao `BookImageStorageService` vi anh sach va anh banner la hai domain khac nhau.

## Phan 5: Filament admin

Tao `BannerResource`.

Nhom dieu huong de xuat:

```text
Noi dung
```

Ten resource:

```text
Banner trang chu
```

Form chia thanh cac section:

1. Noi dung:
   - `title`
2. Hinh anh:
   - `FileUpload public_id`
   - disk Cloudinary
   - image editor neu can
   - helper text ty le anh de xuat `21:8` hoac `840x320`
3. Hien thi:
   - `sort_order`
   - `is_active`

Table can co:

1. Preview anh.
2. Tieu de.
3. Trang thai bat/tat.
4. Thu tu.
5. Ngay cap nhat.

Filter can co:

1. Active / inactive.

Actions:

1. Tao moi.
2. Chinh sua.
3. Xoa.
4. Bat/tat nhanh neu can.

## Phan 6: Backend API

Tao controller:

```text
App\Http\Controllers\Api\V1\Content\BannerController
```

Endpoint de xuat:

```text
GET /api/v1/banners
```

Response:

```json
{
  "data": [
    {
      "id": 1,
      "title": "Flash Sale thang 6",
      "image_url": "https://..."
    }
  ]
}
```

Khong tra cac truong noi bo:

```text
public_id
is_active
sort_order
created_at
updated_at
```

## Phan 7: Service va cache

Tao service:

```text
App\Services\Content\BannerCatalogService
```

Nhiem vu:

1. Query banner dang bat.
2. Tra collection da sap xep.
3. Cache payload public.

Cache key co dinh:

```text
content:banners:home:v1
```

TTL:

```text
15 phut
```

Invalidation:

1. Khi tao banner.
2. Khi sua banner.
3. Khi xoa banner.
4. Khi bat/tat banner.

Co the xu ly bang observer:

```text
App\Observers\BannerObserver
```

Events:

```text
created
updated
deleted
```

Moi event xoa cache banner trang chu.

## Phan 8: Frontend service

Tao file:

```text
frontend/src/services/bannerApi.js
```

Noi dung de xuat:

```js
import axiosClient from "./axiosClient";

const bannerApi = {
  getBanners() {
    return axiosClient.get("/v1/banners");
  },
};

export default bannerApi;
```

## Phan 9: Frontend BannerSlider

Sua `frontend/src/components/Home/BannerSlider.jsx`.

Thay mang hard-code:

```js
const bannerImages = [...]
```

bang props:

```js
<BannerSlider banners={banners} />
```

Quy tac render:

1. Khong co banner thi return `null`.
2. `alt` lay tu `title`.
3. Giu Swiper hien tai.
4. Neu chi co 1 banner, co the render anh don hoac van dung Swiper.
5. Khong gan link click vao banner trong giai doan dau.

## Phan 10: Frontend HomePage

Trong `HomePage.jsx`, them state:

```js
const [banners, setBanners] = useState([]);
```

Trong ham fetch du lieu, goi them:

```js
bannerApi.getBanners()
```

Nen dung `Promise.allSettled` de loi banner khong lam hong toan bo trang chu.

Ket qua mong muon:

1. Banner loi: trang van hien thi sach.
2. Khong co banner: khong render slider.
3. Flash sale loi: bo qua nhu hien tai.

## Phan 11: Tests backend

Tao test API:

```text
backend/tests/Feature/Content/BannerApiTest.php
```

Case can co:

1. Tra danh sach banner active.
2. Khong tra banner inactive.
3. Sort dung theo `sort_order`.
4. Response khong lo `public_id`.

Tao test Filament:

```text
backend/tests/Feature/Filament/BannerManagementTest.php
```

Case can co:

1. Admin tao banner thanh cong.
2. Admin sua banner thanh cong.
3. Admin bat/tat banner.
4. Validate thieu anh.

## Phan 12: Cap nhat tai lieu

Cap nhat:

```text
backend/docs/database_schema.md
```

Them bang `banners`.

Co the them ghi chu ngan ve:

1. Endpoint public.
2. Quy tac hien thi.
3. Quy uoc Cloudinary folder.

## Phan 13: Thu tu trien khai

Thu tu nen lam:

1. [x] Migration `banners`.
2. [x] Model `Banner`.
3. [x] Service luu/xoa anh banner.
4. [x] Filament `BannerResource`.
5. [x] API resource/controller/service.
6. [x] Route API.
7. [x] Cache + observer invalidation.
8. [x] Backend tests (API, unit storage, Filament admin, observer).
9. [ ] Frontend `bannerApi`.
10. [ ] Sua `HomePage.jsx`.
11. [ ] Sua `BannerSlider.jsx`.
12. [ ] Frontend smoke test.
13. [x] Cap nhat `database_schema.md` (bang `banners`; re-export MySQL khi co `mysqldump` tren may dev).

## Phan 14: Rui ro can kiem soat

1. Anh upload len sai folder Cloudinary.
   - Can dung dung `directory` va `getUploadedFileNameForStorageUsing`.
2. Cache lam admin sua roi frontend chua thay ngay.
   - Can observer xoa cache khi banner thay doi.
3. Frontend crash khi API banner loi.
   - Dung fallback mang rong.
4. Payload API lo truong noi bo.
   - Dung JsonResource rieng cho banner public.
