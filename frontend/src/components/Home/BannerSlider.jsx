// src/components/Home/BannerSlider.jsx
import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Pagination, Navigation } from "swiper/modules";

// Import CSS mặc định của Swiper
import "swiper/css";
import "swiper/css/pagination";
import "swiper/css/navigation";

// Dữ liệu tạm: Link ảnh các banner (Kích thước ngang tỷ lệ 840x320 giống Fahasa)
const bannerImages = [
  "https://cdn1.fahasa.com/media/wysiwyg/Thang-05-2026/Trangthang5_web.png",
  "https://cdn1.fahasa.com/media/wysiwyg/Thang-04-2026/thieu-nhi/Trangthieunhi_web.png",
  "https://cdn1.fahasa.com/media/wysiwyg/Thang-05-2026/Deli_KC_LDP_Mainbanner.png",
];

const BannerSlider = () => {
  return (
    <div className="w-full rounded-xl overflow-hidden shadow-md">
      <Swiper
        spaceBetween={0} // Khoảng cách giữa các slide
        slidesPerView={1} // Số slide hiển thị cùng lúc
        loop={true} // Trượt vòng lặp vô hạn
        autoplay={{
          delay: 3000, // Tự động trượt sau 3 giây (3000ms)
          disableOnInteraction: false, // Vẫn tự trượt sau khi người dùng click
        }}
        pagination={{ clickable: true }} // Dấu chấm tròn ở dưới
        navigation={true} // Mũi tên qua lại 2 bên
        modules={[Autoplay, Pagination, Navigation]}
        className="mySwiper"
      >
        {/* Lặp qua mảng bannerImages để in ra các ảnh */}
        {bannerImages.map((imageUrl, index) => (
          <SwiperSlide key={index}>
            <img
              src={imageUrl}
              alt={`Banner ${index + 1}`}
              className="w-full h-auto object-cover aspect-[21/8]"
            />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default BannerSlider;
