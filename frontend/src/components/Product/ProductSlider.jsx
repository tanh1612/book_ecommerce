// src/components/Product/ProductSlider.jsx
import { useRef, useState } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import { FiChevronLeft, FiChevronRight } from "react-icons/fi";

import "swiper/css";

import ProductCard from "./ProductCard";

const ProductSlider = ({ products, variant = "default" }) => {
  const swiperRef = useRef(null);
  const [canSlidePrev, setCanSlidePrev] = useState(false);
  const [canSlideNext, setCanSlideNext] = useState(false);
  const isFlashSale = variant === "flash-sale";
  const sliderClassName = `w-full relative bookify-product-slider ${
    isFlashSale ? "bookify-product-slider--flash-sale" : ""
  }`;

  const breakpoints = isFlashSale
    ? {
        640: { slidesPerView: 2, slidesPerGroup: 2, spaceBetween: 16 },
        768: { slidesPerView: 3, slidesPerGroup: 3, spaceBetween: 16 },
        1024: { slidesPerView: 4, slidesPerGroup: 4, spaceBetween: 20 },
      }
    : {
        640: { slidesPerView: 3, spaceBetween: 16 },
        768: { slidesPerView: 4, spaceBetween: 16 },
        1024: { slidesPerView: 5, spaceBetween: 16 },
      };

  const updateNavigationState = (swiper) => {
    if (!swiper) return;

    setCanSlidePrev(!swiper.isBeginning && !swiper.isLocked);
    setCanSlideNext(!swiper.isEnd && !swiper.isLocked);
  };

  const handleSlidePrev = () => {
    swiperRef.current?.slidePrev();
  };

  const handleSlideNext = () => {
    swiperRef.current?.slideNext();
  };

  return (
    <div className={sliderClassName}>
      {canSlidePrev && (
        <button
          type="button"
          className="absolute -left-5 top-1/2 z-30 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-primary shadow-md transition-colors hover:bg-gray-100"
          aria-label="Sản phẩm trước"
          onClick={handleSlidePrev}
        >
          <FiChevronLeft aria-hidden="true" className="h-7 w-7" />
        </button>
      )}

      {canSlideNext && (
        <button
          type="button"
          className="absolute -right-5 top-1/2 z-30 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-primary shadow-md transition-colors hover:bg-gray-100"
          aria-label="Sản phẩm tiếp theo"
          onClick={handleSlideNext}
        >
          <FiChevronRight aria-hidden="true" className="h-7 w-7" />
        </button>
      )}

      <Swiper
        slidesPerView={isFlashSale ? 1 : 2}
        slidesPerGroup={1}
        spaceBetween={isFlashSale ? 16 : 12}
        watchOverflow={true}
        breakpoints={breakpoints}
        onSwiper={(swiper) => {
          swiperRef.current = swiper;
          updateNavigationState(swiper);
        }}
        onAfterInit={updateNavigationState}
        onBreakpoint={updateNavigationState}
        onResize={updateNavigationState}
        onSlideChange={updateNavigationState}
        onSlidesLengthChange={updateNavigationState}
        onFromEdge={updateNavigationState}
        onReachBeginning={updateNavigationState}
        onReachEnd={updateNavigationState}
        className="!pb-6 !pt-2"
      >
        {products.map((book, index) => (
          <SwiperSlide
            key={book.promotion_item_id || book.id || book.book?.id || index}
            className="h-auto"
          >
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
