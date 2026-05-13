// src/components/Layout/Footer.jsx
import { Link } from 'react-router-dom';
import { FiMapPin, FiPhone, FiMail } from 'react-icons/fi';

const Footer = () => {
  return (
    <footer className="bg-[#157a2c] text-white pt-12 pb-6 mt-12">
      <div className="container mx-auto px-4">
        
        {/* Phần Logo và Slogan */}
        <div className="mb-10 flex items-end gap-4 border-b border-green-700 pb-6">
          <div className="font-extrabold text-4xl tracking-tighter">Bookify</div>
        </div>

        {/* 3 Cột Nội Dung (Đã bỏ cột Phương thức thanh toán) */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
          
          {/* CỘT 1: LIÊN HỆ */}
          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">Liên hệ</h3>
            <ul className="flex flex-col gap-4 text-[15px] text-green-50">
              <li className="flex items-start gap-3">
                <FiMapPin className="mt-1 flex-shrink-0" size={18} />
                <span>Đường Nghiêm Xuân Yêm, Phường Định Công, Thành phố Hà Nội</span>
              </li>
              <li className="flex items-start gap-3">
                <FiPhone className="mt-1 flex-shrink-0" size={18} />
                <span className="font-bold text-white text-base">0337706769</span>
              </li>
              <li className="flex items-start gap-3">
                <FiMail className="mt-1 flex-shrink-0" size={18} />
                <div>
                  <a href="mailto:info@bookify.vn" className="hover:text-white transition">info@bookify.vn</a>
                  <div className="text-sm text-green-200">(Liên hệ hợp tác, đối tác, truyền thông)</div>
                </div>
              </li>
              <li className="flex items-start gap-3">
                <FiMail className="mt-1 flex-shrink-0" size={18} />
                <div>
                  <a href="mailto:cskh@bookify.vn" className="hover:text-white transition">cskh@bookify.vn</a>
                  <div className="text-sm text-green-200">(Hỗ trợ khách hàng, phản hồi dịch vụ)</div>
                </div>
              </li>
            </ul>
          </div>

          {/* CỘT 2: GIỚI THIỆU */}
          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">Giới thiệu</h3>
            <ul className="flex flex-col gap-3 text-[15px] text-green-50">
              <li><Link to="#" className="hover:text-white transition">Về Bookify</Link></li>
              <li><Link to="#" className="hover:text-white transition">Hệ thống hiệu sách</Link></li>
            </ul>
          </div>

          {/* CỘT 3: CHÍNH SÁCH */}
          <div>
            <h3 className="text-lg font-bold mb-5 uppercase tracking-wide">Chính sách</h3>
            <ul className="flex flex-col gap-3 text-[15px] text-green-50">
              <li><Link to="#" className="hover:text-white transition">Chính sách bảo mật</Link></li>
              <li><Link to="#" className="hover:text-white transition">Chính sách đổi trả/hoàn tiền</Link></li>
              <li><Link to="#" className="hover:text-white transition">Chính sách thanh toán/ vận chuyển</Link></li>
            </ul>
          </div>

        </div>

        {/* BOTTOM COPYRIGHT */}
        <div className="border-t border-green-700 mt-8 pt-6 flex flex-col md:flex-row justify-between items-center text-sm text-green-100">
          <p>Copyright © 2021 Bookify. All rights reserved.</p>
        </div>

      </div>
    </footer>
  );
};

export default Footer;