// src/pages/Home/HomePage.jsx
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { FiChevronRight, FiZap } from "react-icons/fi";
import BannerSlider from "../../components/Home/BannerSlider";
import ProductSlider from "../../components/Product/ProductSlider";
import ProductCard from "../../components/Product/ProductCard";
import bookApi from "../../services/bookApi";

const HomePage = () => {
  const [books, setBooks] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchBooks = async () => {
      try {
        // Lấy danh sách sách mới nhất từ Backend
        const res = await bookApi.getBooks({ per_page: 20 });
        setBooks(res.data.data || res.data || []);
      } catch (error) {
        console.error("Lỗi tải sách trang chủ", error);
      } finally {
        setIsLoading(false);
      }
    };
    fetchBooks();
  }, []);

  // Bóc tách sách Flash Sale (Có giảm giá) và Sách mới
  const flashSaleBooks = books.filter(b => b.original_price > b.price || b.discount_percent > 0);
  const newBooks = books.slice(0, 8);
  const suggestBooks = books.slice(0, 10); // Lấy 10 cuốn gợi ý

  if (isLoading) {
    return <div className="min-h-screen flex items-center justify-center">Đang tải dữ liệu...</div>;
  }

  return (
    <div className="flex flex-col gap-10 pb-8">
      <section className="w-full"><BannerSlider /></section>

      {/* FLASH SALE */}
      {flashSaleBooks.length > 0 && (
        <section className="bg-gradient-to-r from-[#157a2c] to-[#0e5c1f] rounded-xl p-4 md:p-6 shadow-md relative overflow-hidden">
          <div className="flex justify-between items-center mb-6 relative z-10">
            <div className="flex items-center gap-3">
              <div className="bg-white text-[#157a2c] p-2 rounded-full animate-pulse">
                <FiZap size={24} className="fill-current" />
              </div>
              <h2 className="text-2xl md:text-3xl font-extrabold text-white italic">FLASH SALE</h2>
            </div>
            <Link to="/sach-khuyen-mai" className="text-white hover:text-green-200 text-sm font-medium flex items-center gap-1 transition">
              Xem tất cả <FiChevronRight />
            </Link>
          </div>
          <div className="relative z-10 bg-white/10 p-2 rounded-lg backdrop-blur-sm">
            <ProductSlider products={flashSaleBooks} />
          </div>
        </section>
      )}

      {/* SÁCH MỚI PHÁT HÀNH */}
      <section className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div className="flex justify-between items-end mb-6 border-b border-gray-100 pb-4">
          <h2 className="text-2xl font-bold text-[#157a2c] uppercase flex items-center gap-2">Sách Mới Phát Hành</h2>
          <Link to="/sach-moi" className="text-[#157a2c] hover:underline text-sm font-medium flex items-center">
            Xem thêm <FiChevronRight />
          </Link>
        </div>
        <ProductSlider products={newBooks} />
      </section>

      {/* GỢI Ý CHO BẠN */}
      <section className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div className="flex justify-center mb-8">
          <h2 className="text-2xl font-bold text-[#157a2c] uppercase bg-green-50 px-6 py-2 rounded-full inline-block">Gợi Ý Dành Riêng Cho Bạn</h2>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          {suggestBooks.map(book => (
            <ProductCard key={book.id} book={book} />
          ))}
        </div>
      </section>
    </div>
  );
};

export default HomePage;