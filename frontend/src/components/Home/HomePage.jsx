// src/pages/Home/HomePage.jsx
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { FiChevronRight, FiZap } from "react-icons/fi";
import BannerSlider from "../../components/Home/BannerSlider";
import ProductSlider from "../../components/Product/ProductSlider";
import ProductCard from "../../components/Product/ProductCard";
import FlashSaleTimer from "../../components/Home/FlashSaleTimer";
import bookApi from "../../services/bookApi";
import recommendationApi from "../../services/recommendationApi";
import { RiTimerFlashFill } from "react-icons/ri";

const HomePage = () => {
  const [books, setBooks] = useState([]);
  const [flashSaleBooks, setFlashSaleBooks] = useState([]);
  const [flashSaleEndsAt, setFlashSaleEndsAt] = useState(null);
  const [recommendedBooks, setRecommendedBooks] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchAllData = async () => {
      try {
        // 1. Gọi API lấy sách bình thường (Cho mục Sách Mới & Gợi Ý)
        const resBooks = await bookApi.getBooks({
          per_page: 20,
          sort: "newest",
        });
        let booksArray = [];
        if (Array.isArray(resBooks.data)) booksArray = resBooks.data;
        else if (resBooks.data?.data && Array.isArray(resBooks.data.data))
          booksArray = resBooks.data.data;
        else if (
          resBooks.data?.data?.data &&
          Array.isArray(resBooks.data.data.data)
        )
          booksArray = resBooks.data.data.data;
        setBooks(booksArray);

        try {
          const resRecommendations = await recommendationApi.getRecommendations(
            { limit: 10 },
          );
          const recommendationData =
            resRecommendations.data?.data || resRecommendations.data || [];
          setRecommendedBooks(
            Array.isArray(recommendationData) ? recommendationData : [],
          );
        } catch (recommendationError) {
          console.log(
            "Không thể tải gợi ý cá nhân, dùng danh sách fallback",
            recommendationError,
          );
          setRecommendedBooks([]);
        }

        // 2. 🔥 Gọi API lấy sách Flash Sale xịn từ Backend
        try {
          const resFlashSale = await bookApi.getActiveFlashSale();
          const fsData = resFlashSale.data?.data || resFlashSale.data;

          // API Flash Sale trả về cấu trúc { id, start_at, items: [...] }
          if (fsData && Array.isArray(fsData.items)) {
            setFlashSaleBooks(fsData.items);
            setFlashSaleEndsAt(fsData.end_at || null);
          } else {
            setFlashSaleBooks([]);
            setFlashSaleEndsAt(null);
          }
        } catch (fsError) {
          console.log("Hiện không có Flash Sale nào đang chạy", fsError);
          setFlashSaleBooks([]);
          setFlashSaleEndsAt(null);
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
  const suggestBooks =
    recommendedBooks.length > 0 ? recommendedBooks : safeBooks.slice(0, 10);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center text-primary font-bold">
        Đang tải dữ liệu...
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-10 pb-12">
      <section className="w-full">
        <BannerSlider />
      </section>

      {/* SECTION: FLASH SALE */}
      {flashSaleBooks.length > 0 && (
        <section className="container mx-auto px-8 lg:px-10">
          <div className="home-flash-sale rounded-2xl p-5 md:p-8 relative overflow-hidden">
            <FiZap className="home-flash-sale-mark absolute -right-10 -top-10 w-64 h-64 rotate-12" />

            <div className="flex flex-col md:flex-row justify-between items-center mb-8 relative z-10 gap-4">
              <div className="flex items-center gap-4">
                <div className="home-flash-sale-icon p-3 rounded-2xl shadow-lg animate-bounce">
                  <RiTimerFlashFill size={28} className="fill-current" />
                </div>
                <div className="flex flex-col">
                  <h2 className="text-2xl md:text-4xl font-black text-white italic tracking-tighter">
                    FLASH SALE
                  </h2>
                  <p className="home-flash-sale-copy text-xs font-medium uppercase tracking-widest">
                    Ưu đãi giới hạn mỗi ngày
                  </p>
                </div>
              </div>

              <div className="home-flash-sale-panel flex items-center gap-6 px-5 py-3 rounded-2xl backdrop-blur-md">
                <FlashSaleTimer endAt={flashSaleEndsAt} />
              </div>
            </div>

            <div className="relative z-10">
              <ProductSlider products={flashSaleBooks} variant="flash-sale" />
            </div>
          </div>
        </section>
      )}

      {/* SÁCH MỚI PHÁT HÀNH */}
      <section className="container mx-auto px-8 lg:px-10">
        <div className="app-section-card p-8">
          <div className="app-section-divider flex justify-between items-end mb-8 pb-5">
            <div className="flex flex-col gap-1">
              <h2 className="app-section-title text-2xl font-bold uppercase">
                Sách Mới Phát Hành
              </h2>
              <div className="app-section-kicker h-1.5 w-20 rounded-full"></div>
            </div>
            <Link
              to="/catalog?sort=newest"
              className="app-primary-link text-sm font-bold flex items-center gap-1"
            >
              Khám phá ngay <FiChevronRight />
            </Link>
          </div>
          <ProductSlider products={newBooks} />
        </div>
      </section>

      {/* GỢI Ý CHO BẠN */}
      <section className="container mx-auto px-8 lg:px-10">
        <div className="app-section-card p-8">
          <div className="text-center mb-10">
            <h2 className="app-section-title text-3xl font-bold uppercase mb-2">
              Gợi Ý Cho Bạn
            </h2>
            <p className="app-muted-text text-sm">
              Dựa trên sở thích đọc sách của bạn
            </p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
            {suggestBooks.map((book) => (
              <ProductCard key={book.id} book={book} />
            ))}
          </div>

          <div className="flex justify-center mt-12">
            <Link
              to="/catalog"
              className="app-outline-button px-10 py-3 border-2 font-bold rounded-xl transition-all duration-300 shadow-sm"
            >
              XEM THÊM SẢN PHẨM
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
};

export default HomePage;
