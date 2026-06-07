// src/components/Layout/Footer.jsx
import { Link } from "react-router-dom";
import { FiMapPin, FiPhone, FiMail } from "react-icons/fi";
import vnpayLogo from "../../assets/Logo-VNPAY-QR-1.png";

const Footer = () => {
  return (
    <footer className="bookify-footer pt-12 pb-6">
      <div className="container mx-auto px-8 lg:px-10">
        {/* 3 Cột Nội Dung (Đã bỏ cột Phương thức thanh toán) */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-14 mb-8">
          {/* CỘT 1: LIÊN HỆ */}
          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">
              Liên hệ
            </h3>
            <ul className="bookify-footer-list flex flex-col gap-4 text-[15px]">
              <li className="flex items-start gap-3">
                <FiMapPin className="mt-1 flex-shrink-0" size={18} />
                <span>
                  Đường Nghiêm Xuân Yêm, Phường Định Công, Thành phố Hà Nội
                </span>
              </li>
              <li className="flex items-start gap-3">
                <FiPhone className="mt-1 flex-shrink-0" size={18} />
                <span className="font-bold text-base">0337706769</span>
              </li>
              <li className="flex items-start gap-3">
                <FiMail className="mt-1 flex-shrink-0" size={18} />
                <div>
                  <a
                    href="mailto:info@bookify.vn"
                    className="bookify-footer-link"
                  >
                    info@bookify.vn
                  </a>
                  <div className="text-sm">
                    (Liên hệ hợp tác, đối tác, truyền thông)
                  </div>
                </div>
              </li>
              <li className="flex items-start gap-3">
                <FiMail className="mt-1 flex-shrink-0" size={18} />
                <div>
                  <a
                    href="mailto:cskh@bookify.vn"
                    className="bookify-footer-link"
                  >
                    cskh@bookify.vn
                  </a>
                  <div className="text-sm">
                    (Hỗ trợ khách hàng, phản hồi dịch vụ)
                  </div>
                </div>
              </li>
            </ul>
          </div>

          {/* CỘT 2: GIỚI THIỆU */}
          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">
              Giới thiệu
            </h3>
            <ul className="bookify-footer-list flex flex-col gap-3 text-[15px]">
              <li>
                <Link to="#" className="bookify-footer-link">
                  Về Bookify
                </Link>
              </li>
              <li>
                <Link to="#" className="bookify-footer-link">
                  Hệ thống hiệu sách
                </Link>
              </li>
              <li>
                <Link to="#" className="bookify-footer-link">
                  Hệ thống phát hành
                </Link>
              </li>
              <li>
                <Link to="#" className="bookify-footer-link">
                  Tuyển dụng
                </Link>
              </li>
              <li>
                <Link to="#" className="bookify-footer-link">
                  Liên hệ với chúng tôi
                </Link>
              </li>
            </ul>
          </div>

          {/* CỘT 3: CHÍNH SÁCH */}
          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">
              Chính sách
            </h3>
            <ul className="bookify-footer-list flex flex-col gap-3 text-[15px]">
              <li>
                <Link to="#" className="bookify-footer-link">
                  Chính sách bảo mật
                </Link>
              </li>
              <li>
                <Link to="#" className="bookify-footer-link">
                  Chính sách đổi trả/hoàn tiền
                </Link>
              </li>
              <li>
                <Link to="#" className="bookify-footer-link">
                  Chính sách thanh toán/ vận chuyển
                </Link>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">
              Phương thức thanh toán
            </h3>
            <div className="bookify-payment-logo inline-flex rounded-md p-3">
              <img
                src={vnpayLogo}
                alt="VNPAY QR"
                className="h-12 w-auto object-contain"
              />
            </div>
          </div>
        </div>

        <div className="bookify-footer-copy bookify-footer-divider mt-8 pt-6 text-center text-sm leading-6">
          <p>
            Đây là dự án cá nhân được xây dựng với mục đích học tập và thử
            nghiệm công nghệ. Tất cả hình ảnh, logo và sản phẩm thuộc về bản
            quyền của chủ sở hữu hợp pháp. Website này không có mục đích thương
            mại và không phát sinh giao dịch thực tế.
          </p>
        </div>

        {/* BOTTOM COPYRIGHT */}
        <div className="bookify-footer-copy bookify-footer-divider mt-8 pt-6 text-center text-sm">
          <p>Bookify Copyright © 2026. All rights reserved.</p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
