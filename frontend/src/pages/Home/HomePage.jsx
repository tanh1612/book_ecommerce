// src/pages/Home/HomePage.jsx
import { Link } from "react-router-dom";
import { FiChevronRight, FiZap } from "react-icons/fi";
import BannerSlider from "../../components/Home/BannerSlider"; // (Nhớ sửa lại tên file BannerSlider nếu trước đó viết sai chính tả nhé)
import ProductSlider from "../../components/Product/ProductSlider";
import ProductCard from "../../components/Product/ProductCard";

// DỮ LIỆU MẪU ĐỂ HIỂN THỊ TRANG CHỦ
const HOME_MOCK_BOOKS = [
  { id: 1, name: "Túp lều bác Tom", author: "Harriet Beecher Stowe", slug: "tup-leu-bac-tom", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Tup+Leu", originalPrice: 150000, salePrice: 120000, inStock: 50 },
  { id: 2, name: "Ú òa", author: "Giuliano Ferri", slug: "sach-lat-u-oa", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=U+Oa", originalPrice: 85000, salePrice: 85000, inStock: 10 },
  { id: 3, name: "Chuyện con chó tên là trung thành", author: "Luis Sepúlveda", slug: "chuyen-con-cho", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Trung+Thanh", originalPrice: 90000, salePrice: 75000, inStock: 20 },
  { id: 4, name: "Tàn ngày để lại", author: "Kazuo Ishiguro", slug: "tan-ngay-de-lai", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Tan+Ngay", originalPrice: 120000, salePrice: 110000, inStock: 15 },
  { id: 5, name: "Kim các tự", author: "Mishima Yukio", slug: "kim-cac-tu", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Kim+Cac+Tu", originalPrice: 110000, salePrice: 95000, inStock: 5 },
  { id: 6, name: "Người tình Sputnik", author: "Haruki Murakami", slug: "nguoi-tinh-sputnik", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Sputnik", originalPrice: 96000, salePrice: 81600, inStock: 8 },
  { id: 7, name: "Rừng Na Uy", author: "Haruki Murakami", slug: "rung-na-uy", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Rung+Na+Uy", originalPrice: 150000, salePrice: 125000, inStock: 30 },
  { id: 8, name: "Nhà Giả Kim", author: "Paulo Coelho", slug: "nha-gia-kim", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Nha+Gia+Kim", originalPrice: 79000, salePrice: 65000, inStock: 100 },
  { id: 9, name: "Cây Cam Ngọt Của Tôi", author: "José Mauro", slug: "cay-cam-ngot", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Cay+Cam+Ngot", originalPrice: 108000, salePrice: 86000, inStock: 40 },
  { id: 10, name: "Hoàng Tử Bé", author: "Antoine de Saint", slug: "hoang-tu-be", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Hoang+Tu+Be", originalPrice: 75000, salePrice: 75000, inStock: 12 },
];

const HomePage = () => {
  // Lọc ra các sách có giảm giá (originalPrice > salePrice) để cho vào Flash Sale
  const flashSaleBooks = HOME_MOCK_BOOKS.filter(book => book.originalPrice > book.salePrice);
  
  // Lấy 8 cuốn đầu tiên làm sách mới
  const newBooks = HOME_MOCK_BOOKS.slice(0, 8);

  return (
    <div className="flex flex-col gap-10 pb-8">
      
      {/* KHU VỰC 1: BANNER SLIDER */}
      <section className="w-full">
        <BannerSlider />
      </section>

      {/* KHU VỰC 2: FLASH SALE */}
      <section className="bg-gradient-to-r from-red-500 to-red-600 rounded-xl p-4 md:p-6 shadow-md relative overflow-hidden">
        {/* Tiêu đề Flash Sale */}
        <div className="flex justify-between items-center mb-6 relative z-10">
          <div className="flex items-center gap-3">
            <div className="bg-white text-red-600 p-2 rounded-full animate-pulse">
              <FiZap size={24} className="fill-current" />
            </div>
            <h2 className="text-2xl md:text-3xl font-extrabold text-white italic">
              FLASH SALE
            </h2>
            {/* Giả lập đồng hồ đếm ngược */}
            <div className="hidden md:flex gap-2 ml-6">
              <span className="bg-black text-white px-2 py-1 rounded font-bold">02</span>:
              <span className="bg-black text-white px-2 py-1 rounded font-bold">45</span>:
              <span className="bg-black text-white px-2 py-1 rounded font-bold">12</span>
            </div>
          </div>
          <Link to="/sach-khuyen-mai" className="text-white hover:text-yellow-200 text-sm font-medium flex items-center gap-1 transition">
            Xem tất cả <FiChevronRight />
          </Link>
        </div>
        
        {/* Gọi component Slider ngang */}
        <div className="relative z-10 bg-white/10 p-2 rounded-lg backdrop-blur-sm">
          <ProductSlider products={flashSaleBooks} />
        </div>
      </section>

      {/* KHU VỰC 3: SÁCH MỚI PHÁT HÀNH */}
      <section className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div className="flex justify-between items-end mb-6 border-b border-gray-100 pb-4">
          <h2 className="text-2xl font-bold text-gray-800 uppercase flex items-center gap-2">
            📚 Sách Mới Phát Hành
          </h2>
          <Link to="/sach-moi" className="text-[#157a2c] hover:underline text-sm font-medium flex items-center">
            Xem thêm <FiChevronRight />
          </Link>
        </div>
        
        {/* Gọi component Slider ngang */}
        <ProductSlider products={newBooks} />
      </section>

      {/* KHU VỰC 4: GỢI Ý CHO BẠN (Dùng Grid) */}
      <section className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div className="flex justify-center mb-8">
          <h2 className="text-2xl font-bold text-[#157a2c] uppercase bg-green-50 px-6 py-2 rounded-full inline-block">
            ✨ Gợi Ý Dành Riêng Cho Bạn
          </h2>
        </div>
        
        {/* Dùng Grid hiển thị dạng lưới thay vì trượt ngang */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          {HOME_MOCK_BOOKS.map(book => (
            <ProductCard key={book.id} book={book} />
          ))}
        </div>

        {/* Nút tải thêm */}
        <div className="flex justify-center mt-8">
          <button className="border-2 border-[#157a2c] text-[#157a2c] px-10 py-2.5 rounded-full font-bold hover:bg-[#157a2c] hover:text-white transition-colors">
            Xem Thêm Gợi Ý
          </button>
        </div>
      </section>

    </div>
  );
};

export default HomePage;