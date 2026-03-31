// src/pages/Home/HomePage.jsx
import BannerSlider from "../../components/Home/BannerSlider";

const HomePage = () => {
  return (
    <div className="flex flex-col gap-8">
      {/* KHU VỰC 1: BANNER SLIDER */}
      <section className="w-full">
        <BannerSlider />
      </section>

      {/* KHU VỰC 2: FLASH SALE (Sẽ làm sau) */}
      <section className="bg-red-50 p-4 rounded-xl">
        <h2 className="text-2xl font-bold text-[#C92127] mb-4 flex items-center gap-2">
          ⚡ SÁCH ĐANG KHUYẾN MÃI
        </h2>
        <p className="text-gray-500">
          Khu vực chứa các sách Flash Sale sẽ nằm ở đây...
        </p>
      </section>

      {/* KHU VỰC 3: SÁCH MỚI PHÁT HÀNH (Sẽ làm sau) */}
      <section>
        <h2 className="text-2xl font-bold text-gray-800 mb-4">
          📚 Sách Mới Phát Hành
        </h2>
        <p className="text-gray-500">Lưới sản phẩm (Grid) sẽ nằm ở đây...</p>
      </section>
      {/* ... (Các khu vực Banner, Flash Sale, Sách mới như cũ) ... */}

      {/* KHU VỰC 4: GỢI Ý CHO BẠN */}
      <section className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mt-8">
        <h2 className="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
          ✨ Gợi Ý Dành Riêng Cho Bạn
        </h2>
        <div className="text-gray-500 italic">
          (Sau này ta sẽ gọi API truyền lên lịch sử xem hàng của user để lấy
          danh sách sách gợi ý hiển thị ở đây...)
        </div>
      </section>
    </div>
  );
};

export default HomePage;
