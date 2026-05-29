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
import bookApi from '../../services/bookApi'; // Thêm API đối chiếu Flash Sale

const CheckoutPage = () => {
  const navigate = useNavigate();
  const { cartItems, fetchCart } = useCart();
  
  const [provinces, setProvinces] = useState([]);
  const [wards, setWards] = useState([]);
  const [shippingFee, setShippingFee] = useState(0);
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  // Kho chứa % giảm giá để cross-check
  const [flashSales, setFlashSales] = useState({});

  const [formData, setFormData] = useState({
    recipient_name: '', recipient_phone: '', province_code: '', ward_code: '',
    detail_address: '', note: '', shipping_method_id: 1, payment_method: 'cod'
  });

  const selectedItems = useMemo(() => cartItems.filter(item => item.selected), [cartItems]);

  // 🌟 ĐỒNG BỘ THUẬT TOÁN TÍNH TỔNG TIỀN THANH TOÁN
  const orderSummary = useMemo(() => {
    const subTotal = selectedItems.reduce((sum, item) => {
      const bookData = item.book || item;
      
      let original = Number(bookData.original_price || bookData.price || 0);
      let price = Number(bookData.selling_price || original);
      
      // Ép giá giảm từ bảng flashSales đối chiếu
      let discount = Number(flashSales[bookData.id] || bookData.discount_percent || 0);
      if (discount > 0) price = original - (original * (discount / 100));

      return sum + (price * (item.quantity || 1));
    }, 0);
    return { subTotal, total: subTotal + shippingFee };
  }, [selectedItems, shippingFee, flashSales]);

  useEffect(() => {
    if (selectedItems.length === 0) {
      toast.warning("Vui lòng chọn sản phẩm để thanh toán!");
      navigate('/cart');
      return;
    }

    const loadInitialCheckoutData = async () => {
      try {
        // Tải song song: Tỉnh thành, Sổ địa chỉ, và Bảng Flash Sale
        const [provinceRes, addressRes, fsRes] = await Promise.all([
          addressApi.getProvinces(),
          addressApi.getAddresses(),
          bookApi.getActiveFlashSale().catch(() => null)
        ]);

        // Cập nhật kho Flash Sale
        if (fsRes) {
          const fsData = fsRes.data?.data || fsRes.data;
          if (fsData && Array.isArray(fsData.items)) {
            const fsMap = {};
            fsData.items.forEach(item => fsMap[item.book.id] = Number(item.discount_percent || item.discount_value || 0));
            setFlashSales(fsMap);
          }
        }

        const provinceList = provinceRes.data?.data || provinceRes.data || [];
        const addressList = addressRes.data?.data || addressRes.data || [];

        setProvinces(provinceList);
        const defaultAddress = addressList.find(addr => addr.is_default === 1 || addr.is_default === true);

        if (defaultAddress) {
          setFormData(prev => ({
            ...prev,
            recipient_name: defaultAddress.recipient_name || '',
            recipient_phone: defaultAddress.recipient_phone || '',
            province_code: defaultAddress.province_code || '',
            ward_code: defaultAddress.ward_code || '',
            detail_address: defaultAddress.detail_address || '',
          }));

          if (defaultAddress.province_code) {
            const wardRes = await addressApi.getWards(defaultAddress.province_code);
            setWards(wardRes.data?.data || wardRes.data || []);

            const feeRes = await checkoutApi.getShippingQuote({
              province_code: defaultAddress.province_code, shipping_method_id: formData.shipping_method_id
            });
            setShippingFee(feeRes.data?.data?.shipping_fee || feeRes.data?.shipping_fee || 0);
          }
          toast.info("Đã áp dụng địa chỉ mặc định của bạn.");
        }
      } catch (error) {
        console.error("Lỗi khởi tạo:", error);
      }
    };

    loadInitialCheckoutData();
  }, [selectedItems, navigate]);

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
    if (name === 'shipping_method_id') calculateShipping(formData.province_code, value);
  };

  const calculateShipping = async (provinceCode, methodId) => {
    if (!provinceCode) return;
    try {
      const res = await checkoutApi.getShippingQuote({ province_code: provinceCode, shipping_method_id: Number(methodId) });
      setShippingFee(res.data?.data?.shipping_fee || res.data?.shipping_fee || 0);
    } catch (error) {
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
      const payload = {
        idempotency_key: uuidv4(), payment_method: formData.payment_method, shipping_method_id: Number(formData.shipping_method_id),
        note: formData.note ? formData.note.trim() : "test vnpay",
        shipping: {
          recipient_name: formData.recipient_name.trim(), recipient_phone: formData.recipient_phone.trim(),
          province_code: String(formData.province_code).padStart(2, '0'), ward_code: formData.ward_code ? String(formData.ward_code).padStart(5, '0') : "00001",
          detail_address: formData.detail_address.trim()
        }
      };
      const res = await checkoutApi.submitOrder(payload);
      const responseData = res.data?.data || res.data; 
      
      if (formData.payment_method === 'vnpay' && responseData?.payment) {
        const paymentUrl = responseData.payment.payment_url || responseData.payment;
        if (typeof paymentUrl === 'string' && paymentUrl.startsWith('http')) {
          window.location.href = paymentUrl; return; 
        }
      }
      toast.success("Đặt hàng thành công!");
      fetchCart();
      navigate('/profile'); 
    } catch (error) {
      if (error.response?.status === 422) {
        const validationErrors = error.response.data?.errors;
        if (validationErrors) toast.error(validationErrors[Object.keys(validationErrors)[0]][0]);
        else toast.error(error.response.data?.message || "Dữ liệu chưa hợp lệ!");
      } else if (error.response?.status === 401) {
        toast.error("Vui lòng đăng nhập để đặt hàng!");
      } else toast.error(error.response?.data?.message || "Lỗi hệ thống khi đặt hàng!");
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
            <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
              <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><FiMapPin className="text-[#157a2c]" /> Thông tin nhận hàng</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label className="block text-sm text-gray-600 mb-1">Họ và tên *</label><input type="text" name="recipient_name" value={formData.recipient_name} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" /></div>
                <div><label className="block text-sm text-gray-600 mb-1">Số điện thoại *</label><input type="tel" name="recipient_phone" value={formData.recipient_phone} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" /></div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Tỉnh/Thành phố *</label>
                  <select name="province_code" value={formData.province_code} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none bg-white">
                    <option value="">Chọn Tỉnh/Thành</option>{provinces.map(p => <option key={p.code} value={p.code}>{p.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Quận/Huyện/Xã</label>
                  <select name="ward_code" value={formData.ward_code} onChange={handleInputChange} disabled={!formData.province_code} className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none bg-white">
                    <option value="">Chọn Phường/Xã</option>{wards.map(w => <option key={w.code} value={w.code}>{w.name}</option>)}
                  </select>
                </div>
              </div>
              <div className="mb-4"><label className="block text-sm text-gray-600 mb-1">Địa chỉ chi tiết *</label><input type="text" name="detail_address" value={formData.detail_address} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none" /></div>
              <div><label className="block text-sm text-gray-600 mb-1">Ghi chú đơn hàng</label><textarea name="note" value={formData.note} onChange={handleInputChange} rows="2" className="w-full border border-gray-300 rounded px-3 py-2 focus:border-[#157a2c] outline-none"></textarea></div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><FiTruck className="text-[#157a2c]" /> Vận chuyển</h2>
                <div className="flex flex-col gap-3">
                  <label className={`border p-3 rounded flex gap-3 cursor-pointer transition ${formData.shipping_method_id == 1 ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}><input type="radio" name="shipping_method_id" value="1" checked={formData.shipping_method_id == 1} onChange={handleInputChange} className="mt-1 w-4 h-4 text-[#157a2c]" /><div><div className="font-medium text-gray-800 text-sm">Giao hàng tiêu chuẩn</div></div></label>
                  <label className={`border p-3 rounded flex gap-3 cursor-pointer transition ${formData.shipping_method_id == 2 ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}><input type="radio" name="shipping_method_id" value="2" checked={formData.shipping_method_id == 2} onChange={handleInputChange} className="mt-1 w-4 h-4 text-[#157a2c]" /><div><div className="font-medium text-gray-800 text-sm">Giao hàng hỏa tốc</div></div></label>
                </div>
              </div>

              <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><FiCreditCard className="text-[#157a2c]" /> Thanh toán</h2>
                <div className="flex flex-col gap-3">
                  <label className={`border p-3 rounded flex items-center gap-3 cursor-pointer transition ${formData.payment_method === 'cod' ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}><input type="radio" name="payment_method" value="cod" checked={formData.payment_method === 'cod'} onChange={handleInputChange} className="w-4 h-4 text-[#157a2c]" /><span className="font-medium text-sm">Nhận hàng (COD)</span></label>
                  <label className={`border p-3 rounded flex items-center gap-3 cursor-pointer transition ${formData.payment_method === 'vnpay' ? 'border-[#157a2c] bg-green-50' : 'border-gray-200'}`}><input type="radio" name="payment_method" value="vnpay" checked={formData.payment_method === 'vnpay'} onChange={handleInputChange} className="w-4 h-4 text-[#157a2c]" /><span className="font-medium text-sm text-blue-700">Chuyển khoản VNPay</span></label>
                </div>
              </div>
            </div>
          </div>

          <div className="lg:w-1/3">
            <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100 sticky top-4">
              <h2 className="text-lg font-bold text-gray-800 mb-4 border-b pb-4">Tóm tắt đơn hàng</h2>
              <div className="flex flex-col gap-4 mb-4 max-h-72 overflow-y-auto pr-2">
                {selectedItems.map(item => {
                  const bookData = item.book || item;
                  
                  // 🌟 ĐỒNG BỘ THUẬT TOÁN Ở KHUNG BÊN PHẢI
                  let original = Number(bookData.original_price || bookData.price || 0);
                  let price = Number(bookData.selling_price || original);
                  let discount = Number(flashSales[bookData.id] || bookData.discount_percent || 0);
                  if (discount > 0) price = original - (original * (discount / 100));

                  return (
                    <div key={item.id} className="flex gap-3 text-sm">
                      <div className="relative flex-shrink-0">
                        <img src={bookData.thumbnail_url || bookData.thumbnail} className="w-12 h-16 object-cover border rounded" />
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
                <div className="flex justify-between"><span>Tạm tính:</span><span className="font-medium text-gray-800">{formatCurrency(orderSummary.subTotal)}</span></div>
                <div className="flex justify-between"><span>Phí vận chuyển:</span><span className="font-medium text-gray-800">{shippingFee === 0 ? '--' : formatCurrency(shippingFee)}</span></div>
              </div>
              
              <div className="flex justify-between items-center mb-6">
                <span className="text-gray-800 font-bold">Tổng cộng:</span><span className="text-2xl font-bold text-[#ff424e]">{formatCurrency(orderSummary.total)}</span>
              </div>

              <button type="submit" disabled={isSubmitting || selectedItems.length === 0} className="w-full bg-[#157a2c] text-white py-3 rounded-md font-bold text-lg hover:bg-green-800 transition shadow-sm flex justify-center items-center gap-2 disabled:opacity-50">
                {isSubmitting ? <><div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div> Xử lý...</> : (formData.payment_method === 'vnpay' ? 'ĐẶT HÀNG QUA VNPAY' : 'HOÀN TẤT ĐẶT HÀNG')}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CheckoutPage;