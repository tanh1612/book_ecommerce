// src/components/Home/BannerSlider.jsx
import { useEffect, useState } from 'react';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Autoplay, Pagination, Navigation } from 'swiper/modules';
import contentApi from '../../services/contentApi';
import { resolveMediaUrl } from '../../utils/media';

import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/navigation';

const BannerSlider = () => {
  const [banners, setBanners] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let ignore = false;

    const fetchBanners = async () => {
      try {
        const res = await contentApi.getHomeBanners();
        const data = res.data?.data || res.data || [];

        if (!ignore) {
          setBanners(Array.isArray(data) ? data.filter((banner) => banner.image_url) : []);
        }
      } catch (error) {
        console.error('Failed to load home banners:', error);
        if (!ignore) setBanners([]);
      } finally {
        if (!ignore) setIsLoading(false);
      }
    };

    fetchBanners();

    return () => {
      ignore = true;
    };
  }, []);

  if (isLoading) {
    return (
      <div className="w-full aspect-[21/8] bg-gray-100 animate-pulse" />
    );
  }

  if (banners.length === 0) {
    return null;
  }

  return (
    <div className="w-full overflow-hidden bg-gray-100">
      <Swiper
        spaceBetween={0}
        slidesPerView={1}
        loop={banners.length > 1}
        autoplay={banners.length > 1 ? { delay: 3500, disableOnInteraction: false } : false}
        pagination={banners.length > 1 ? { clickable: true } : false}
        navigation={banners.length > 1}
        modules={[Autoplay, Pagination, Navigation]}
        className="bookify-home-banners"
      >
        {banners.map((banner) => (
          <SwiperSlide key={banner.id}>
            <img
              src={resolveMediaUrl(banner.image_url)}
              alt={banner.title || 'Bookify banner'}
              className="w-full aspect-[21/8] object-cover"
              loading="eager"
              onError={(event) => {
                event.currentTarget.style.display = 'none';
              }}
            />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default BannerSlider;
