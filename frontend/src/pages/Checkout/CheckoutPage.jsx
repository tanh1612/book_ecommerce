// src/pages/Checkout/CheckoutPage.jsx
import { useState, useEffect, useCallback, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiChevronRight, FiMapPin, FiTruck, FiCreditCard, FiAlertCircle } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { v4 as uuidv4 } from 'uuid';
import { formatCurrency } from '../../utils/formatters';
import { resolveMediaUrl } from '../../utils/media';
import { useCart } from '../../context/CartContext';
import addressApi from '../../services/addressApi';
import checkoutApi from '../../services/checkoutApi';
import cartApi from '../../services/cartApi';

const EXPRESS_SHIPPING_METHOD_ID = 2;
const METRO_PROVINCE_CODES = new Set(['01', '79']);
const SHIPPING_METHODS = [
  {
    id: 1,
    name: 'Tiết kiệm',
    description: 'Giao hàng tiêu chuẩn, áp dụng toàn quốc.',
  },
  {
    id: 2,
    name: 'Hỏa tốc',
    description: 'Chỉ áp dụng cho địa chỉ tại Hà Nội hoặc Hồ Chí Minh.',
  },
];

const normalizeProvinceCode = (value) => String(value || '').padStart(2, '0');

const normalizeProvinceName = (value) =>
  String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const getMethodId = (method) => Number(method?.id ?? method?.shipping_method_id ?? method?.value);
const getMethodName = (method) => method?.name || method?.label || method?.title || '';

const buildShippingQuotePayload = ({ addressId, provinceCode }, methodId) => ({
  shipping_method_id: Number(methodId),
  ...(addressId
    ? { address_id: Number(addressId) }
    : { province_code: normalizeProvinceCode(provinceCode) }),
});

const isExpressShippingMethod = (method) => {
  const methodId = getMethodId(method);
  const methodName = normalizeProvinceName(getMethodName(method));

  return methodId === EXPRESS_SHIPPING_METHOD_ID ||
    methodName.includes('hoa toc') ||
    methodName.includes('express');
};

const isExpressProvince = (provinceCode, provinceList = []) => {
  const normalizedCode = normalizeProvinceCode(provinceCode);
  if (METRO_PROVINCE_CODES.has(normalizedCode)) {
    return true;
  }

  const province = provinceList.find((item) => normalizeProvinceCode(item.code) === normalizedCode);
  const provinceName = normalizeProvinceName(province?.name);

  return provinceName.includes('ha noi') || provinceName.includes('ho chi minh');
};

const getAvailableShippingMethods = (methods, provinceCode, provinces) => {
  const canUseExpress = isExpressProvince(provinceCode, provinces);
  return methods.filter((method) => !isExpressShippingMethod(method) || canUseExpress);
};

const extractShippingFee = (response) =>
  Number(response?.data?.data?.shipping_fee ?? response?.data?.shipping_fee ?? 0);

const CheckoutPage = () => {
  const navigate = useNavigate();
  const { cartItems, fetchCart } = useCart();

  const [provinces, setProvinces] = useState([]);
  const [wards, setWards] = useState([]);

  const [shippingFee, setShippingFee] = useState(null);
  const [shippingError, setShippingError] = useState(null);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isFetchingWards, setIsFetchingWards] = useState(false);
  const [isCalculatingFee, setIsCalculatingFee] = useState(false);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [appliedAddress, setAppliedAddress] = useState(null);
  const [addresses, setAddresses] = useState([]);

  const [cartInfo, setCartInfo] = useState(null);
  const [checkoutCartItems, setCheckoutCartItems] = useState([]);

  const [formData, setFormData] = useState({
    recipient_name: '',
    recipient_phone: '',
    province_code: '',
    ward_code: '',
    detail_address: '',
    note: '',
    shipping_method_id: '',
    payment_method: 'cod',
  });

  const selectedItems = useMemo(() => {
    const sourceItems = checkoutCartItems.length > 0 ? checkoutCartItems : cartItems;
    return sourceItems.filter((item) => item.selected);
  }, [cartItems, checkoutCartItems]);

  const subTotal = useMemo(() => {
    if (cartInfo?.selected_subtotal_after_discount !== undefined) {
      return Number(cartInfo.selected_subtotal_after_discount || 0);
    }

    return selectedItems.reduce((sum, item) => {
      const bookData = item.book || item;
      const itemPrice = Number(item.effective_unit_price ?? bookData.selling_price ?? bookData.price ?? 0);
      return sum + itemPrice * Number(item.quantity || 0);
    }, 0);
  }, [cartInfo, selectedItems]);

  const totalAmount = useMemo(() => subTotal + Number(shippingFee || 0), [shippingFee, subTotal]);
  const hasSavedAddresses = addresses.length > 0;
  const canUseExpress = isExpressProvince(formData.province_code, provinces);
  const availableShippingMethods = useMemo(
    () => getAvailableShippingMethods(SHIPPING_METHODS, formData.province_code, provinces),
    [formData.province_code, provinces],
  );
  const quoteTarget = useMemo(() => {
    if (!formData.province_code || !formData.ward_code) {
      return null;
    }

    if (selectedAddressId && appliedAddress) {
      return {
        addressId: selectedAddressId,
        provinceCode: formData.province_code,
      };
    }

    if (!formData.detail_address.trim()) {
      return null;
    }

    return { provinceCode: formData.province_code };
  }, [appliedAddress, formData.detail_address, formData.province_code, formData.ward_code, selectedAddressId]);
  const canChooseShippingMethod = Boolean(quoteTarget);
  const isSubmitDisabled =
    isSubmitting ||
    selectedItems.length === 0 ||
    isFetchingWards ||
    isCalculatingFee ||
    !quoteTarget ||
    !formData.shipping_method_id ||
    shippingFee === null ||
    shippingError !== null;

  const loadWards = useCallback(async (code) => {
    setIsFetchingWards(true);
    try {
      const res = await addressApi.getWards(code);
      setWards(res.data?.data || res.data || []);
    } finally {
      setIsFetchingWards(false);
    }
  }, []);

  const calculateShipping = useCallback(async ({ provinceCode, addressId }, methodId) => {
    if ((!provinceCode && !addressId) || !methodId) return;

    setIsCalculatingFee(true);
    setShippingError(null);
    setShippingFee(null);

    try {
      const payload = buildShippingQuotePayload({ provinceCode, addressId }, methodId);
      const res = await checkoutApi.getShippingQuote(payload, { skipGlobalErrorHandler: true });
      setShippingFee(extractShippingFee(res));
    } catch (error) {
      setShippingFee(null);
      if (error.response?.status === 422) {
        setShippingError('Nhà vận chuyển chưa thiết lập cước phí cho khu vực này.');
      } else {
        setShippingError('Không thể tính phí vận chuyển lúc này.');
      }
    } finally {
      setIsCalculatingFee(false);
    }
  }, []);

  useEffect(() => {
    if (!formData.shipping_method_id || !quoteTarget) {
      setIsCalculatingFee(false);
      setShippingFee(null);
      setShippingError(null);
      return;
    }

    const methodId = Number(formData.shipping_method_id);
    const methodStillAvailable = availableShippingMethods.some((method) => getMethodId(method) === methodId);

    if (!methodStillAvailable) {
      setFormData((prev) => ({ ...prev, shipping_method_id: '' }));
      setIsCalculatingFee(false);
      setShippingFee(null);
      setShippingError(null);
      return;
    }

    setShippingError(null);
    setShippingFee(null);
    calculateShipping(quoteTarget, methodId);
  }, [availableShippingMethods, calculateShipping, formData.shipping_method_id, quoteTarget]);

  useEffect(() => {
    const abortController = new AbortController();

    const initData = async () => {
      try {
        setShippingFee(null);
        setShippingError(null);

        const [provinceRes, addressRes, cartRes] = await Promise.all([
          addressApi.getProvinces(),
          addressApi.getAddresses(),
          cartApi.getCart(),
        ]);

        if (abortController.signal.aborted) return;

        const provinceList = provinceRes.data?.data || provinceRes.data || [];
        const cartPayload = cartRes.data?.data || cartRes.data || {};
        const cartPayloadItems = cartPayload.items || [];
        const selectedCartItems = cartPayloadItems.filter((item) => item.selected);

        if (selectedCartItems.length === 0) {
          toast.warning('Vui lòng chọn sản phẩm để thanh toán!');
          navigate('/cart');
          return;
        }

        setProvinces(provinceList);
        setCartInfo(cartPayload);
        setCheckoutCartItems(cartPayloadItems);

        const addressList = addressRes.data?.data || addressRes.data || [];
        const safeAddressList = Array.isArray(addressList) ? addressList : [];
        const defaultAddress = safeAddressList.find((addr) => addr.is_default === 1 || addr.is_default === true);
        const initialAddress = defaultAddress || safeAddressList[0] || null;
        setAddresses(safeAddressList);

        if (initialAddress) {
          setSelectedAddressId(initialAddress.id);
          setAppliedAddress(initialAddress);
          setFormData((prev) => ({
            ...prev,
            recipient_name: initialAddress.recipient_name || '',
            recipient_phone: initialAddress.recipient_phone || '',
            province_code: initialAddress.province_code || '',
            ward_code: initialAddress.ward_code || '',
            detail_address: initialAddress.detail_address || '',
            shipping_method_id: '',
          }));

          if (initialAddress.province_code) {
            await loadWards(initialAddress.province_code);
          }
        } else {
          setFormData((prev) => ({ ...prev, shipping_method_id: '' }));
        }
      } catch (err) {
        if (err.name !== 'CanceledError') {
          toast.error('Lỗi tải dữ liệu thanh toán');
        }
      } finally {
        if (!abortController.signal.aborted) setIsCalculatingFee(false);
      }
    };

    initData();

    return () => { abortController.abort(); };
  }, [loadWards, navigate]);

  const handleSelectAddress = (addressId) => {
    const nextAddress = addresses.find((address) => Number(address.id) === Number(addressId));
    if (!nextAddress) return;

    setSelectedAddressId(nextAddress.id);
    setAppliedAddress(nextAddress);
    setShippingFee(null);
    setShippingError(null);
    setFormData((prev) => ({
      ...prev,
      recipient_name: nextAddress.recipient_name || '',
      recipient_phone: nextAddress.recipient_phone || '',
      province_code: nextAddress.province_code || '',
      ward_code: nextAddress.ward_code || '',
      detail_address: nextAddress.detail_address || '',
      shipping_method_id: '',
    }));

    if (nextAddress.province_code) {
      loadWards(nextAddress.province_code);
    }
  };

  const handleInputChange = (event) => {
    const { name, value } = event.target;

    const manualAddressFields = ['recipient_name', 'recipient_phone', 'province_code', 'ward_code', 'detail_address'];
    if (manualAddressFields.includes(name)) {
      setSelectedAddressId(null);
      setAppliedAddress(null);
    }

    if (name === 'province_code') {
      setFormData((prev) => ({ ...prev, province_code: value, ward_code: '', shipping_method_id: '' }));
      setShippingFee(null);
      setShippingError(null);

      if (value) {
        loadWards(value);
      } else {
        setWards([]);
      }
      return;
    }

    if (name === 'ward_code' || name === 'detail_address') {
      setShippingFee(null);
      setShippingError(null);
    }

    if (name === 'shipping_method_id') {
      if (!quoteTarget) {
        toast.warning('Vui lòng nhập đầy đủ thông tin giao hàng trước khi chọn vận chuyển.');
        return;
      }

      setShippingFee(null);
      setShippingError(null);
    }

    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const validateCheckoutForm = () => {
    if (!formData.recipient_name || formData.recipient_name.trim().length < 2) {
      toast.error('Họ tên ít nhất 2 ký tự');
      return false;
    }

    const phoneRegex = /^0\d{9}$/;
    if (!formData.recipient_phone || !phoneRegex.test(formData.recipient_phone.replace(/\s/g, ''))) {
      toast.error('Số điện thoại phải là 10 chữ số (0xxxxxxxxx)');
      return false;
    }

    if (!formData.province_code || !formData.ward_code || !formData.detail_address) {
      toast.error('Vui lòng điền và chọn đầy đủ thông tin giao hàng!');
      return false;
    }

    if (!formData.shipping_method_id) {
      toast.error('Vui lòng chọn phương thức vận chuyển!');
      return false;
    }

    if (shippingError) {
      toast.error(shippingError);
      return false;
    }

    if (shippingFee === null) {
      toast.error('Vui lòng chờ hệ thống tính phí vận chuyển.');
      return false;
    }

    return true;
  };

  const isAppliedAddressUnchanged = () => {
    if (!selectedAddressId || !appliedAddress) return false;

    return (
      String(formData.recipient_name || '') === String(appliedAddress.recipient_name || '') &&
      String(formData.recipient_phone || '') === String(appliedAddress.recipient_phone || '') &&
      String(formData.province_code || '') === String(appliedAddress.province_code || '') &&
      String(formData.ward_code || '') === String(appliedAddress.ward_code || '') &&
      String(formData.detail_address || '') === String(appliedAddress.detail_address || '')
    );
  };

  const handleCheckout = async (event) => {
    event.preventDefault();

    if (!validateCheckoutForm()) return;

    setIsSubmitting(true);
    try {
      const useSavedAddress = isAppliedAddressUnchanged();
      const payload = {
        idempotency_key: uuidv4(),
        payment_method: formData.payment_method,
        shipping_method_id: Number(formData.shipping_method_id),
        shipping_fee: Number(shippingFee || 0),
        note: formData.note ? formData.note.trim() : null,
        pricing_expectations: cartInfo?.pricing_expectations,
      };

      if (useSavedAddress) {
        payload.address_id = Number(selectedAddressId);
      } else {
        payload.shipping = {
          recipient_name: formData.recipient_name.trim(),
          recipient_phone: formData.recipient_phone.trim(),
          province_code: String(formData.province_code).padStart(2, '0'),
          ward_code: String(formData.ward_code).padStart(5, '0'),
          detail_address: formData.detail_address.trim(),
        };
      }

      console.log('[Checkout] submit payload', payload);
      const res = await checkoutApi.submitOrder(payload);
      const responseData = res.data?.data || res.data;

      if (formData.payment_method === 'vnpay' && responseData?.payment) {
        const paymentUrl = responseData.payment.payment_url || responseData.payment;
        if (typeof paymentUrl === 'string' && paymentUrl.startsWith('http')) {
          window.location.href = paymentUrl;
          return;
        }
      }

      toast.success('Đặt hàng thành công!');
      fetchCart();
      navigate('/profile');
    } catch (error) {
      if (error.response?.status === 422) {
        const validationErrors = error.response.data?.errors;
        if (validationErrors) toast.error(validationErrors[Object.keys(validationErrors)[0]][0]);
        else toast.error(error.response.data?.message || 'Dữ liệu chưa hợp lệ!');
      } else if (error.response?.status === 401) {
        toast.error('Vui lòng đăng nhập để đặt hàng!');
      } else {
        toast.error(error.response?.data?.message || 'Lỗi hệ thống khi đặt hàng!');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-8 lg:px-10 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-primary">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <Link to="/cart" className="hover:text-primary">Giỏ hàng</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-primary font-medium">Thanh toán</span>
        </div>
      </div>

      <div className="container mx-auto px-8 lg:px-10">
        <form onSubmit={handleCheckout} className="flex flex-col lg:flex-row gap-8">
          <div className="lg:w-2/3 flex flex-col gap-6">
            <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
              <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <FiMapPin className="text-primary" /> Thông tin nhận hàng
              </h2>

              {hasSavedAddresses && (
                <div className="flex flex-col gap-3 mb-4">
                  {addresses.map((address) => (
                    <label
                      key={address.id}
                      className={`app-selectable-card p-4 rounded flex gap-3 cursor-pointer ${Number(selectedAddressId) === Number(address.id) ? 'is-selected' : ''}`}
                    >
                      <input
                        type="radio"
                        name="saved_address_id"
                        value={address.id}
                        checked={Number(selectedAddressId) === Number(address.id)}
                        onChange={() => handleSelectAddress(address.id)}
                        className="mt-1 w-4 h-4 text-primary"
                      />
                      <span className="flex flex-col gap-1 text-sm">
                        <span className="font-bold app-section-title">
                          {address.recipient_name} | {address.recipient_phone}
                          {address.is_default ? <span className="ml-2 app-primary-link text-xs">Mặc định</span> : null}
                        </span>
                        <span className="app-muted-text">
                          {address.detail_address}
                          {address.ward?.name ? `, ${address.ward.name}` : (address.ward_name ? `, ${address.ward_name}` : '')}
                          {address.province?.name ? `, ${address.province.name}` : (address.province_name ? `, ${address.province_name}` : '')}
                        </span>
                      </span>
                    </label>
                  ))}

                  <div>
                    <label className="block text-sm app-muted-text mb-1">Ghi chú đơn hàng</label>
                    <textarea
                      name="note"
                      value={formData.note}
                      onChange={handleInputChange}
                      rows="2"
                      className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none"
                    />
                  </div>
                </div>
              )}

              {!hasSavedAddresses && (
                <>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                      <label className="block text-sm text-gray-600 mb-1">Họ và tên *</label>
                      <input type="text" name="recipient_name" value={formData.recipient_name} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none" />
                    </div>
                    <div>
                      <label className="block text-sm text-gray-600 mb-1">Số điện thoại *</label>
                      <input type="tel" name="recipient_phone" value={formData.recipient_phone} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none" />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                      <label className="block text-sm text-gray-600 mb-1">Tỉnh/Thành phố *</label>
                      <select name="province_code" value={formData.province_code} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none bg-white">
                        <option value="">Chọn Tỉnh/Thành</option>
                        {provinces.map((province) => <option key={province.code} value={province.code}>{province.name}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm text-gray-600 mb-1">Phường/Xã *</label>
                      <select name="ward_code" value={formData.ward_code} onChange={handleInputChange} disabled={!formData.province_code || isFetchingWards} className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none bg-white">
                        <option value="">{isFetchingWards ? 'Đang tải dữ liệu...' : 'Chọn Phường/Xã'}</option>
                        {wards.map((ward) => <option key={ward.code} value={ward.code}>{ward.name}</option>)}
                      </select>
                    </div>
                  </div>

                  <div className="mb-4">
                    <label className="block text-sm text-gray-600 mb-1">Địa chỉ chi tiết *</label>
                    <input type="text" name="detail_address" value={formData.detail_address} onChange={handleInputChange} required className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none" />
                  </div>

                  <div>
                    <label className="block text-sm text-gray-600 mb-1">Ghi chú đơn hàng</label>
                    <textarea name="note" value={formData.note} onChange={handleInputChange} rows="2" className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary outline-none" />
                  </div>
                </>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                  <FiTruck className="text-primary" /> Vận chuyển
                </h2>

                {shippingError && formData.province_code !== '' && (
                  <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded flex items-start gap-2 text-red-600 text-sm">
                    <FiAlertCircle className="mt-0.5 flex-shrink-0" />
                    <span>{shippingError}</span>
                  </div>
                )}

                <div className="flex flex-col gap-3">
                  {!canChooseShippingMethod && (
                    <p className="app-muted-text text-sm">
                      Chọn địa chỉ giao hàng hoặc nhập đầy đủ tỉnh/thành, phường/xã và địa chỉ chi tiết trước khi chọn vận chuyển.
                    </p>
                  )}

                  {availableShippingMethods.map((method) => {
                    const methodId = getMethodId(method);
                    const isSelectedMethod = Number(formData.shipping_method_id) === methodId;

                    return (
                      <label
                        key={methodId}
                        className={`app-selectable-card p-3 rounded flex gap-3 ${canChooseShippingMethod ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed'} ${isSelectedMethod ? 'is-selected' : ''}`}
                      >
                        <input
                          type="radio"
                          name="shipping_method_id"
                          value={methodId}
                          checked={isSelectedMethod}
                          onChange={handleInputChange}
                          disabled={!canChooseShippingMethod || isCalculatingFee}
                          className="mt-1 w-4 h-4 text-primary"
                        />
                        <span className="flex flex-col flex-grow">
                          <span className="font-medium app-section-title text-sm flex items-center justify-between gap-3">
                            <span>{getMethodName(method)}</span>
                            <span className="font-bold text-primary whitespace-nowrap">
                              {isSelectedMethod && shippingFee !== null
                                ? formatCurrency(shippingFee)
                                : 'Chọn để tính phí'}
                            </span>
                          </span>
                          {method.description && <span className="app-muted-text text-xs">{method.description}</span>}
                        </span>
                      </label>
                    );
                  })}

                  {!canUseExpress && formData.province_code && (
                    <p className="app-muted-text text-xs">
                      Hỏa tốc chỉ hiển thị cho địa chỉ tại Hà Nội hoặc Hồ Chí Minh.
                    </p>
                  )}
                </div>
              </div>

              <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                  <FiCreditCard className="text-primary" /> Thanh toán
                </h2>
                <div className="flex flex-col gap-3">
                  <label className={`border p-3 rounded flex items-center gap-3 cursor-pointer transition ${formData.payment_method === 'cod' ? 'border-primary bg-green-50' : 'border-gray-200'}`}>
                    <input type="radio" name="payment_method" value="cod" checked={formData.payment_method === 'cod'} onChange={handleInputChange} className="w-4 h-4 text-primary" />
                    <span className="font-medium text-sm">Nhận hàng (COD)</span>
                  </label>
                  <label className={`border p-3 rounded flex items-center gap-3 cursor-pointer transition ${formData.payment_method === 'vnpay' ? 'border-primary bg-green-50' : 'border-gray-200'}`}>
                    <input type="radio" name="payment_method" value="vnpay" checked={formData.payment_method === 'vnpay'} onChange={handleInputChange} className="w-4 h-4 text-primary" />
                    <span className="font-medium text-sm text-blue-700">Chuyển khoản VNPay</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div className="lg:w-1/3">
            <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-100 sticky top-4">
              <h2 className="text-lg font-bold text-gray-800 mb-4 border-b pb-4">Tóm tắt đơn hàng</h2>
              <div className="flex flex-col gap-4 mb-4 max-h-72 overflow-y-auto pr-2">
                {selectedItems.map((item) => {
                  const bookData = item.book || item;
                  const itemPrice = Number(item.effective_unit_price ?? bookData.selling_price ?? bookData.price ?? 0);

                  return (
                    <div key={item.id} className="flex gap-3 text-sm">
                      <div className="relative flex-shrink-0">
                        <img
                          src={resolveMediaUrl(bookData.thumbnail_url || bookData.thumbnail, 'https://placehold.co/48x64?text=No+Image')}
                          alt={bookData.name || bookData.title || 'Book'}
                          className="w-12 h-16 object-cover border rounded"
                        />
                        <span className="absolute -top-2 -right-2 bg-gray-500 text-white text-[10px] w-5 h-5 flex items-center justify-center rounded-full">{item.quantity}</span>
                      </div>
                      <div className="flex flex-col justify-between flex-grow">
                        <span className="font-medium text-gray-800 line-clamp-2">{bookData.name || bookData.title}</span>
                        <span className="font-bold text-primary">{formatCurrency(itemPrice * item.quantity)}</span>
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="flex flex-col gap-2 mb-4 text-sm text-gray-600 border-b border-gray-100 pb-4 mt-4">
                <div className="flex justify-between">
                  <span>Tạm tính:</span>
                  <span className="font-medium text-gray-800">{formatCurrency(subTotal)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Phí vận chuyển:</span>
                  <span className="font-medium text-gray-800">
                    {isCalculatingFee
                      ? <span className="animate-pulse text-gray-400">Đang tính...</span>
                      : (shippingFee !== null ? formatCurrency(shippingFee) : '--')}
                  </span>
                </div>
              </div>

              <div className="flex justify-between items-center mb-6">
                <span className="text-gray-800 font-bold">Tổng cộng:</span>
                <span className="text-2xl font-bold text-danger">{formatCurrency(totalAmount)}</span>
              </div>

              <button
                type="submit"
                disabled={isSubmitDisabled}
                className="w-full bg-primary text-white py-3 rounded-md font-bold text-lg transition shadow-sm flex justify-center items-center gap-2 disabled:opacity-50 disabled:bg-gray-400 disabled:cursor-not-allowed"
              >
                {isSubmitting ? (
                  <>
                    <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Xử lý...
                  </>
                ) : (formData.payment_method === 'vnpay' ? 'ĐẶT HÀNG QUA VNPAY' : 'HOÀN TẤT ĐẶT HÀNG')}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CheckoutPage;
