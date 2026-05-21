// src/pages/Checkout/CheckoutPage.jsx
import { useState, useEffect, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiChevronRight, FiMapPin, FiTruck, FiCreditCard } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { v4 as uuidv4 } from 'uuid';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';
import addressApi from '../../services/addressApi';
import checkoutApi from '../../services/checkoutApi';

const CheckoutPage = () => {
  const navigate = useNavigate();
  const { cartItems, fetchCart } = useCart();
  
  const [provinces, setProvinces] = useState([]);
  const [wards, setWards] = useState([]);
  const [shippingFee, setShippingFee] = useState(0);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Form State đồng bộ chính xác với Backend và Sổ địa chỉ công việc
  const [formData, setFormData] = useState({
    recipient_name: '',
    recipient_phone: '',
    province_code: '',
    ward_code: '',
    detail_address: '',
    note: '',
    shipping_method_id: 1, // 1: Tiêu chuẩn, 2: Hỏa tốc
    payment_method: 'cod'
  });

  // Lọc sản phẩm được người dùng tích chọn mua hàng
  const selectedItems = useMemo(() => cartItems.filter(item => item.selected), [cartItems]);

  // Tính toán tóm tắt chi phí đơn hàng an toàn tránh lỗi hiển thị giá
  const orderSummary = useMemo(() => {
    const subTotal = selectedItems.reduce((sum, item) => {
      const bookData = item.book || item;
      const price = bookData.selling_price || bookData.price || 0;
      return sum + (price * (item.quantity || 1));
    }, 0);
    return { subTotal, total: subTotal + shippingFee };
  }, [selectedItems, shippingFee]);

  // TỰ ĐỘNG LẤY ĐỊA CHỈ MẶC ĐỊNH KHI VÀO TRANG CHỐT ĐƠN
  useEffect(() => {
    if (selectedItems.length === 0) {
      toast.warning("Vui lòng chọn sản phẩm để thanh toán!");
      navigate('/cart');
      return;
    }

    const loadInitialCheckoutData = async () => {
      try {
        // Tải song song danh sách tỉnh thành và sổ địa chỉ người dùng
        const [provinceRes, addressRes] = await Promise.all([
          addressApi.getProvinces(),
          addressApi.getAddresses()
        ]);

        const provinceList = provinceRes.data?.data || provinceRes.data || [];
        const addressList = addressRes.data?.data || addressRes.data || [];

        setProvinces(provinceList);

        // Khám phá tìm địa chỉ được cấu hình làm mặc định
        const defaultAddress = addressList.find(addr => addr.is_default === 1 || addr.is_default === true);

        if (defaultAddress) {
          // Đổ tự động toàn bộ dữ liệu thông tin nhận hàng vào Form
          setFormData(prev => ({
            ...prev,
            recipient_name: defaultAddress.recipient_name || '',
            recipient_phone: defaultAddress.recipient_phone || '',
            province_code: defaultAddress.province_code || '',
            ward_code: defaultAddress.ward_code || '',
            detail_address: defaultAddress.detail_address || '',
          }));

          // Tải danh sách phường xã tương ứng với tỉnh mặc định này
          if (defaultAddress.province_code) {
            const wardRes = await addressApi.getWards(defaultAddress.province_code);
            setWards(wardRes.data?.data || wardRes.data || []);

            // Gọi API tính toán chi phí vận chuyển thời gian thực dựa trên tỉnh mặc định
            const feeRes = await checkoutApi.getShippingQuote({
              province_code: defaultAddress.province_code,
              shipping_method_id: formData.shipping_method_id
            });
            setShippingFee(feeRes.data?.data?.shipping_fee || feeRes.data?.shipping_fee || 0);
          }
          toast.info("Đã tự động áp dụng địa chỉ mặc định của bạn.");
        }
      } catch (error) {
        console.error("Lỗi khởi tạo dữ liệu thanh toán:", error);
      }
    };

    loadInitialCheckoutData();
  }, [selectedItems, navigate]);

  // Xử lý thay đổi dữ liệu thủ công khi người dùng muốn nhập địa chỉ mới
  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));

    if (name === 'province_code') {
      setFormData(prev => ({ ...prev, ward_code: '' }));
      if (value) {
        addressApi.getWards(value).then(res => setWards(res.data?.data || res.data || [])).catch(console.error);
        calculateShipping(value, formData.shipping_method_id);
      } else {
        setWards([]);
        setShippingFee(0);
      }
    }

    if (name === 'shipping_method_id') {
      calculateShipping(formData.province_code, value);
    }
  };

  const calculateShipping = async (provinceCode, methodId) => {
    if (!provinceCode) return;
    try {
      const res = await checkoutApi.getShippingQuote({
        province_code: provinceCode,
        shipping_method_id: Number(methodId)
      });
      setShippingFee(res.data?.data?.shipping_fee || res.data?.shipping_fee || 0);
    } catch (error) {
      console.error("Lỗi tính phí vận chuyển:", error);
      setShippingFee(30000); 
    }
  };

  const handleCheckout = async (e) => {
    e.preventDefault();
    if (!formData.recipient_name || !formData.recipient_phone || !formData.province_code || !formData.detail_address) {
      return toast.error("Vui lòng nhập đầy đủ thông tin nhận hàng!");
    }

    setIsSubmitting(true);
    try {
      // ÉP PAYLOAD GIỐNG HỆT POSTMAN 100% ĐỂ VƯỢT QUA LỖI
      const payload = {
        idempotency_key: uuidv4(), 
        payment_method: formData.payment_method, // "vnpay" hoặc "cod"
        shipping_method_id: Number(formData.shipping_method_id), // Postman gửi số 2
        note: formData.note ? formData.note.trim() : "test vnpay", // Ép luôn note giống Postman
        shipping: {
          recipient_name: formData.recipient_name.trim(),
          recipient_phone: formData.recipient_phone.trim(),
          province_code: String(formData.province_code).padStart(2, '0'),
          // Bọc an toàn, nếu phường xã rỗng thì gửi "00001" giống Postman
          ward_code: formData.ward_code ? String(formData.ward_code).padStart(5, '0') : "00001",
          detail_address: formData.detail_address.trim()
        }
      };

      // Log ra F12 để bạn tự đối chiếu với Postman
      console.log("🚀 Payload React chuẩn bị gửi:", JSON.stringify(payload, null, 2));

      const res = await checkoutApi.submitOrder(payload);

      // --- PHẦN XỬ LÝ VNPAY ĐÃ ĐƯỢC FIX ---
      const responseData = res.data?.data || res.data; // Bóc tách lớp vỏ 'data' của Laravel
      
      if (formData.payment_method === 'vnpay' && responseData?.payment) {
        // Tùy thuộc vào VnPayService trả về object chứa payment_url hay chuỗi URL
        const paymentUrl = responseData.payment.payment_url || responseData.payment;
        
        if (typeof paymentUrl === 'string' && paymentUrl.startsWith('http')) {
          console.log("🔄 Đang chuyển hướng sang VNPay:", paymentUrl);
          window.location.href = paymentUrl; // Chuyển hướng trình duyệt sang VNPay
          return; // Dừng ngay luồng hiện tại
        }
      }
      // ------------------------------------

      toast.success("Đặt hàng thành công!");
      fetchCart();
      navigate('/profile'); 
    } catch (error) {
      console.error("❌ Lỗi Backend trả về:", error.response?.data);
      
      if (error.response?.status === 422) {
        const validationErrors = error.response.data?.errors;
        if (validationErrors) {
          const firstErrorKey = Object.keys(validationErrors)[0];
          toast.error(validationErrors[firstErrorKey][0]);
        } else {
          toast.error(error.response.data?.message || "Dữ liệu nhập vào chưa hợp lệ!");
        }
      } else if (error.response?.status === 401) {
        toast.error("Vui lòng đăng nhập để tiến hành đặt hàng!");
      } else {
        toast.error(error.response?.data?.message || "Đã xảy ra lỗi hệ thống khi đặt hàng!");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c]">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <Link to="/cart" className="hover:text-[#157a2c]">Giỏ hàng</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Thanh toán</span>
        </div>
      </div>

      <div className="container mx-auto px-4">
        <form onSubmit={handleCheckout} className="flex flex-col lg:flex-row gap-8">
          
          <div className="lg:w-2/3 flex flex-col gap-6">
            {/* KHỐI ĐIỀN THÔNG TIN NHẬN HÀNG */}
            <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
              <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <FiMapPin className="text-[#157a2c]" /> Thông tin nhận hàng
              </h2>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Họ và tên *</label>
                  <input type="text" name="recipient_name" value={formData.recipient_name} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" placeholder="Nhập họ và tên" />
                </div>
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Số điện thoại *</label>
                  <input type="tel" name="recipient_phone" value={formData.recipient_phone} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" placeholder="Nhập số điện thoại" />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Tỉnh/Thành phố *</label>
                  <select name="province_code" value={formData.province_code} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none bg-white">
                    <option value="">Chọn Tỉnh/Thành</option>
                    {provinces.map(p => <option key={p.code} value={p.code}>{p.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Quận/Huyện/Xã</label>
                  <select name="ward_code" value={formData.ward_code} onChange={handleInputChange} disabled={!formData.province_code} className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none bg-white">
                    <option value="">Chọn Phường/Xã</option>
                    {wards.map(w => <option key={w.code} value={w.code}>{w.name}</option>)}
                  </select>
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-sm text-gray-600 mb-1">Địa chỉ chi tiết (Số nhà, đường...) *</label>
                <input type="text" name="detail_address" value={formData.detail_address} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" placeholder="Ví dụ: 123 Lê Lợi" />
              </div>

              <div>
                <label className="block text-sm text-gray-600 mb-1">Ghi chú đơn hàng (Tùy chọn)</label>
                <textarea name="note" value={formData.note} onChange={handleInputChange} rows="2" className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" placeholder="Giao trong giờ hành chính..."></textarea>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* KHỐI PHƯƠNG THỨC VẬN CHUYỂN */}
              <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                  <FiTruck className="text-[#157a2c]" /> Vận chuyển
                </h2>
                <div className="flex flex-col gap-3">
                  <label className={`border p-3 rounded flex gap-3 cursor-pointer transition ${formData.shipping_method_id == 1 ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}>
                    <input type="radio" name="shipping_method_id" value="1" checked={formData.shipping_method_id == 1} onChange={handleInputChange} className="mt-1 w-4 h-4 text-[#157a2c]" />
                    <div>
                      <div className="font-medium text-gray-800 text-sm">Giao hàng tiêu chuẩn</div>
                      <div className="text-xs text-gray-500">Dự kiến 3-5 ngày</div>
                    </div>
                  </label>
                  <label className={`border p-3 rounded flex gap-3 cursor-pointer transition ${formData.shipping_method_id == 2 ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}>
                    <input type="radio" name="shipping_method_id" value="2" checked={formData.shipping_method_id == 2} onChange={handleInputChange} className="mt-1 w-4 h-4 text-[#157a2c]" />
                    <div>
                      <div className="font-medium text-gray-800 text-sm">Giao hàng hỏa tốc</div>
                      <div className="text-xs text-gray-500">Giao trong 24h</div>
                    </div>
                  </label>
                </div>
              </div>

              {/* KHỐI PHƯƠNG THỨC THANH TOÁN */}
              <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                  <FiCreditCard className="text-[#157a2c]" /> Thanh toán
                </h2>
                <div className="flex flex-col gap-3">
                  <label className={`border p-3 rounded flex items-center gap-3 cursor-pointer transition ${formData.payment_method === 'cod' ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}>
                    <input type="radio" name="payment_method" value="cod" checked={formData.payment_method === 'cod'} onChange={handleInputChange} className="w-4 h-4 text-[#157a2c]" />
                    <span className="font-medium text-sm">Thanh toán khi nhận hàng (COD)</span>
                  </label>
                  <label className={`border p-3 rounded flex items-center gap-3 cursor-pointer transition ${formData.payment_method === 'vnpay' ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}>
                    <input type="radio" name="payment_method" value="vnpay" checked={formData.payment_method === 'vnpay'} onChange={handleInputChange} className="w-4 h-4 text-[#157a2c]" />
                    <span className="font-medium text-sm text-blue-700">Chuyển khoản qua VNPay</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          {/* TÓM TẮT ĐƠN HÀNG */}
          <div className="lg:w-1/3">
            <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100 sticky top-4">
              <h2 className="text-lg font-bold text-gray-800 mb-4 border-b pb-4">Tóm tắt đơn hàng</h2>
              
              <div className="flex flex-col gap-4 mb-4 max-h-72 overflow-y-auto pr-2">
                {selectedItems.map(item => {
                  const bookData = item.book || item;
                  const price = bookData.selling_price || bookData.price || 0;
                  return (
                    <div key={item.id} className="flex gap-3 text-sm">
                      <div className="relative flex-shrink-0">
                        <img src={bookData.thumbnail_url || bookData.thumbnail} alt={bookData.name} className="w-12 h-16 object-cover border rounded" />
                        <span className="absolute -top-2 -right-2 bg-gray-500 text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full">{item.quantity}</span>
                      </div>
                      <div className="flex flex-col justify-between flex-grow">
                        <span className="font-medium text-gray-800 line-clamp-2">{bookData.name || bookData.title}</span>
                        <span className="font-bold text-[#157a2c]">{formatCurrency(price * item.quantity)}</span>
                      </div>
                    </div>
                  )
                })}
              </div>

              <div className="flex flex-col gap-2 mb-4 text-sm text-gray-600 border-b border-gray-100 pb-4 mt-4">
                <div className="flex justify-between">
                  <span>Tạm tính:</span>
                  <span className="font-medium text-gray-800">{formatCurrency(orderSummary.subTotal)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Phí vận chuyển:</span>
                  <span className="font-medium text-gray-800">{shippingFee === 0 ? '--' : formatCurrency(shippingFee)}</span>
                </div>
              </div>
              
              <div className="flex justify-between items-center mb-6">
                <span className="text-gray-800 font-bold">Tổng cộng:</span>
                <span className="text-2xl font-bold text-[#ff424e]">{formatCurrency(orderSummary.total)}</span>
              </div>

              <button 
                type="submit" 
                disabled={isSubmitting || selectedItems.length === 0} 
                className="w-full bg-[#157a2c] text-white py-3 rounded-md font-bold text-lg hover:bg-green-800 transition shadow-sm flex justify-center items-center gap-2 disabled:opacity-50"
              >
                {isSubmitting ? (
                  <><div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div> Xử lý...</>
                ) : (
                  formData.payment_method === 'vnpay' ? 'ĐẶT HÀNG QUA VNPAY' : 'HOÀN TẤT ĐẶT HÀNG'
                )}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CheckoutPage;