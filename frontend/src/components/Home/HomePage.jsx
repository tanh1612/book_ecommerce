// src/pages/Home/HomePage.jsx
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { FiChevronRight, FiZap } from "react-icons/fi";
import BannerSlider from "../../components/Home/BannerSlider";
import ProductSlider from "../../components/Product/ProductSlider";
import ProductCard from "../../components/Product/ProductCard";
import FlashSaleTimer from "../../components/Home/FlashSaleTimer"; 
import bookApi from "../../services/bookApi";

const HomePage = () => {
  const [books, setBooks] = useState([]);
  const [flashSaleBooks, setFlashSaleBooks] = useState([]); // 🔥 State mới lưu sách Flash Sale thật
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchAllData = async () => {
      try {
        // 1. Gọi API lấy sách bình thường (Cho mục Sách Mới & Gợi Ý)
        const resBooks = await bookApi.getBooks({ per_page: 20 });
        let booksArray = [];
        if (Array.isArray(resBooks.data)) booksArray = resBooks.data;
        else if (resBooks.data?.data && Array.isArray(resBooks.data.data)) booksArray = resBooks.data.data;
        else if (resBooks.data?.data?.data && Array.isArray(resBooks.data.data.data)) booksArray = resBooks.data.data.data;
        setBooks(booksArray);

        // 2. 🔥 Gọi API lấy sách Flash Sale xịn từ Backend
        try {
          const resFlashSale = await bookApi.getActiveFlashSale();
          const fsData = resFlashSale.data?.data || resFlashSale.data;
          
          // API Flash Sale trả về cấu trúc { id, start_at, items: [...] }
          if (fsData && Array.isArray(fsData.items)) {
            setFlashSaleBooks(fsData.items);
          }
        } catch (fsError) {
          console.log("Hiện không có Flash Sale nào đang chạy", fsError);
        }

      } catch (error) {
        console.error("Lỗi tải trang chủ", error);
        setBooks([]); 
      } finally {
        setIsLoading(false);
      }
    };
    fetchAllData();
  }, []);

  const safeBooks = Array.isArray(books) ? books : [];
  
  const newBooks = safeBooks.slice(0, 8);
  const suggestBooks = safeBooks.slice(0, 10);

  if (isLoading) {
    return <div className="min-h-screen flex items-center justify-center text-[#157a2c] font-bold">Đang tải dữ liệu...</div>;
  }

  return (
    <div className="flex flex-col gap-10 pb-12 bg-gray-50">
      <section className="w-full"><BannerSlider /></section>

      {/* SECTION: FLASH SALE */}
      {flashSaleBooks.length > 0 && (
        <section className="container mx-auto px-4">
          <div className="bg-gradient-to-r from-[#0a4d1a] via-[#157a2c] to-[#0e5c1f] rounded-2xl p-5 md:p-8 shadow-xl relative overflow-hidden">
            <FiZap className="absolute -right-10 -top-10 text-white/5 w-64 h-64 rotate-12" />
            
            <div className="flex flex-col md:flex-row justify-between items-center mb-8 relative z-10 gap-4">
              <div className="flex items-center gap-4">
                <div className="bg-yellow-400 text-white p-3 rounded-2xl shadow-lg animate-bounce">
                  <FiZap size={28} className="fill-current" />
                </div>
                <div className="flex flex-col">
                  <h2 className="text-2xl md:text-4xl font-black text-white italic tracking-tighter">FLASH SALE</h2>
                  <p className="text-green-100 text-xs font-medium uppercase tracking-widest opacity-80">Ưu đãi giới hạn mỗi ngày</p>
                </div>
              </div>

              <div className="flex items-center gap-6 bg-black/20 px-5 py-3 rounded-2xl backdrop-blur-md border border-white/10">
                <FlashSaleTimer />
                <Link to="/sach-khuyen-mai" className="text-white hover:text-yellow-400 text-sm font-bold flex items-center gap-1 transition-all group">
                  Xem tất cả 
                  <FiChevronRight className="group-hover:translate-x-1 transition-transform" />
                </Link>
              </div>
            </div>

            <div className="relative z-10">
              <ProductSlider products={flashSaleBooks} />
            </div>
          </div>
        </section>
      )}

      {/* SÁCH MỚI PHÁT HÀNH */}
      <section className="container mx-auto px-4 bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
        <div className="flex justify-between items-end mb-8 border-b border-gray-100 pb-5">
          <div className="flex flex-col gap-1">
            <h2 className="text-2xl font-bold text-gray-800 uppercase">Sách Mới Phát Hành</h2>
            <div className="h-1.5 w-20 bg-[#157a2c] rounded-full"></div>
          </div>
          <Link to="/catalog?sort=newest" className="text-[#157a2c] hover:text-green-800 text-sm font-bold flex items-center gap-1 transition">
            Khám phá ngay <FiChevronRight />
          </Link>
        </div>
        <ProductSlider products={newBooks} />
      </section>

      {/* GỢI Ý CHO BẠN */}
      <section className="container mx-auto px-4">
        <div className="text-center mb-10">
          <h2 className="text-3xl font-bold text-gray-800 uppercase mb-2">Gợi Ý Cho Bạn</h2>
          <p className="text-gray-500 text-sm">Dựa trên sở thích đọc sách của bạn</p>
        </div>
        
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
          {suggestBooks.map(book => (
            <ProductCard key={book.id} book={book} />
          ))}
        </div>

        <div className="flex justify-center mt-12">
          <Link to="/catalog" className="px-10 py-3 border-2 border-[#157a2c] text-[#157a2c] font-bold rounded-xl hover:bg-[#157a2c] hover:text-white transition-all duration-300 shadow-sm">
            XEM THÊM SẢN PHẨM
          </Link>
        </div>
      </section>
    </div>
  );
};

export default HomePage;