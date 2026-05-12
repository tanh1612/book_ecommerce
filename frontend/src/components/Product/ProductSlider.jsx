// src/components/Product/ProductSlider.jsx
import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Autoplay } from 'swiper/modules';

// Import CSS của Swiper
import 'swiper/css';
import 'swiper/css/navigation';

import ProductCard from './ProductCard';

const ProductSlider = ({ products }) => {
  return (
    <div className="w-full relative px-2">
      <Swiper
        slidesPerView={2} // Mặc định trên mobile hiển thị 2 cột
        spaceBetween={12} // Khoảng cách giữa các thẻ
        navigation={true} // Bật mũi tên chuyển slide
        modules={[Navigation, Autoplay]}
        autoplay={{
          delay: 4000,
          disableOnInteraction: false,
        }}
        breakpoints={{
          // Cấu hình responsive cho các màn hình lớn hơn
          640: { slidesPerView: 3, spaceBetween: 16 },
          768: { slidesPerView: 4, spaceBetween: 16 },
          1024: { slidesPerView: 5, spaceBetween: 16 },
        }}
        className="!pb-6 !pt-2" // Padding để không bị lẹm hiệu ứng shadow khi hover thẻ
      >
        {products.map((book) => (
          // Đảm bảo chiều cao các thẻ bằng nhau với h-auto và !h-full
          <SwiperSlide key={book.id} className="h-auto">
            <div className="h-full">
              <ProductCard book={book} />
            </div>
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default ProductSlider;