// src/pages/Profile/MyOrders.jsx
import { useState, useEffect } from 'react';
import { FiX, FiAlertCircle, FiClock, FiPackage, FiStar } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { formatCurrency } from '../../utils/formatters';
import { resolveMediaUrl } from '../../utils/media';
import orderApi from '../../services/orderApi';
import reviewApi from '../../services/reviewApi';

const ORDER_STATUSES = [
  { label: 'Tất cả', value: null, color: 'text-gray-800 bg-gray-100' },
  { label: 'Chờ xử lý', value: 'pending', color: 'text-yellow-600 bg-yellow-100' },
  { label: 'Đã xác nhận', value: 'confirmed', color: 'text-blue-600 bg-blue-100' },
  { label: 'Đang xử lý', value: 'processing', color: 'text-indigo-600 bg-indigo-100' },
  { label: 'Đang giao hàng', value: 'shipping', color: 'text-gray-600 bg-gray-200' },
  { label: 'Hoàn tất', value: 'completed', color: 'text-green-600 bg-green-100' },
  { label: 'Đã hủy', value: 'cancelled', color: 'text-red-600 bg-red-100' },
  { label: 'Đang hoàn tiền', value: 'refunding', color: 'text-blue-600 bg-blue-100' },
  { label: 'Đã hoàn trả tiền', value: 'refunded', color: 'text-green-600 bg-green-100' },
  { label: 'Không hoàn tiền', value: 'refund_expired', color: 'text-gray-500 bg-gray-100' },
];

const PAYMENT_STATUS_UI = {
  paid: { label: 'Đã TT VNPay', color: 'text-green-700 bg-green-100' },
  pending: { label: 'Chưa TT VNPay', color: 'text-orange-700 bg-orange-100' },
  refunding: { label: 'Đang hoàn tiền', color: 'text-blue-700 bg-blue-100' },
  refunded: { label: 'Đã hoàn trả tiền', color: 'text-green-700 bg-green-100' },
  refund_expired: { label: 'Không hoàn tiền', color: 'text-gray-600 bg-gray-100' },
  failed: { label: 'Thanh toán thất bại', color: 'text-red-700 bg-red-100' },
  cancelled: { label: 'Thanh toán đã hủy', color: 'text-gray-600 bg-gray-100' },
};

const getOrderItemId = (item) => item.id || item.review_target_id;
const getEnumValue = (value) => (typeof value === 'object' ? value?.value : value);
const getPaymentStatusUi = (status) =>
  PAYMENT_STATUS_UI[status] || { label: status || 'VNPay', color: 'text-orange-700 bg-orange-100' };

const REFUND_PAYMENT_STATUSES = new Set(['refund_expired', 'refunding', 'refunded']);

const getOrderFilterParams = (status) => {
  if (!status) return {};
  if (REFUND_PAYMENT_STATUSES.has(status)) {
    return { status: 'cancelled' };
  }
  return { status };
};

const filterOrdersByUiStatus = (orders, status) => {
  if (!REFUND_PAYMENT_STATUSES.has(status)) return orders;

  return orders.filter((order) => getEnumValue(order.payment_status) === status);
};

const mergeOrderSummaryAndDetail = (summary, detail) => {
  if (!detail) return summary;

  const detailItemsById = new Map(
    (detail.items || []).map((item) => [getOrderItemId(item), item])
  );

  const mergedItems = (summary.items || detail.items || []).map((item) => {
    const detailItem = detailItemsById.get(getOrderItemId(item));
    return detailItem ? { ...detailItem, ...item, ...detailItem } : item;
  });

  return {
    ...summary,
    ...detail,
    items: mergedItems,
  };
};

const MyOrders = () => {
  const [orders, setOrders] = useState([]);
  const [filterStatus, setFilterStatus] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  const [selectedOrder, setSelectedOrder] = useState(null);
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [isRefundModalOpen, setIsRefundModalOpen] = useState(false);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);
  const [selectedReviewItem, setSelectedReviewItem] = useState(null);
  const [reviewData, setReviewData] = useState({ rating: 5, comment: '' });

  const [refundBanks, setRefundBanks] = useState([]);
  const [refundData, setRefundData] = useState({ bank_code: '', account_number: '', account_holder: '' });
  const [isRefundSubmitting, setIsRefundSubmitting] = useState(false);
  const [submittedRefundOrderIds, setSubmittedRefundOrderIds] = useState([]);

  const fetchOrders = async (status = null) => {
    setIsLoading(true);
    try {
      const res = await orderApi.getOrders(getOrderFilterParams(status));
      const orderList = filterOrdersByUiStatus(res.data?.data || [], status);
      setOrders(orderList);

      const enrichedOrders = await Promise.all(
        orderList.map(async (order) => {
          try {
            const detailRes = await orderApi.getOrderDetail(order.id);
            return mergeOrderSummaryAndDetail(order, detailRes.data?.data || detailRes.data);
          } catch {
            return order;
          }
        })
      );

      setOrders(filterOrdersByUiStatus(enrichedOrders, status));
    } catch {
      toast.error('Không thể tải danh sách đơn hàng!');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders(filterStatus);
  }, [filterStatus]);

  const handleCancelOrder = async () => {
    if (!selectedOrder) return;

    try {
      await orderApi.cancelOrder(selectedOrder.id);
      toast.success('Hủy đơn hàng thành công!');
      setIsCancelModalOpen(false);
      fetchOrders(filterStatus);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Lỗi khi hủy đơn hàng!');
    }
  };

  const handleRetryPayment = async (orderId) => {
    try {
      toast.info('Đang tạo link thanh toán mới...');
      const res = await orderApi.getVnPayPaymentUrl(orderId);
      const paymentUrl = res.data?.data?.payment_url;

      if (paymentUrl && typeof paymentUrl === 'string' && paymentUrl.startsWith('http')) {
        window.location.href = paymentUrl;
      } else {
        toast.error('Không nhận được link thanh toán từ hệ thống!');
      }
    } catch (error) {
      toast.error(error.response?.data?.message || 'Lỗi khi tạo lại thanh toán VNPay!');
    }
  };

  const handleViewDetails = async (orderId) => {
    try {
      toast.info('Đang tải dữ liệu...', { autoClose: 500 });
      const res = await orderApi.getOrderDetail(orderId);
      const summaryOrder = orders.find((order) => order.id === orderId);
      setSelectedOrder(mergeOrderSummaryAndDetail(summaryOrder || {}, res.data?.data || res.data));
      setIsDetailModalOpen(true);
    } catch {
      toast.error('Không thể lấy chi tiết đơn hàng');
    }
  };

  const openRefundModal = async (order) => {
    setSelectedOrder(order);
    setRefundData({ bank_code: '', account_number: '', account_holder: '' });
    setIsRefundSubmitting(false);
    setIsRefundModalOpen(true);

    try {
      const res = await orderApi.getRefundBanks();
      setRefundBanks(res.data?.data || res.data || []);
    } catch {
      toast.error('Không thể tải danh sách ngân hàng');
    }
  };

  const handleSubmitRefund = async (event) => {
    event.preventDefault();
    if (!selectedOrder || isRefundSubmitting || submittedRefundOrderIds.includes(selectedOrder.id)) return;

    const isConfirmed = window.confirm('Vui lòng kiểm tra kỹ. Nhập sai sẽ không được hoàn tiền.');
    if (!isConfirmed) return;

    setIsRefundSubmitting(true);
    try {
      await orderApi.submitRefundBank(selectedOrder.id, refundData);
      toast.success('Đã gửi thông tin nhận hoàn tiền thành công!');
      setSubmittedRefundOrderIds((prev) =>
        prev.includes(selectedOrder.id) ? prev : [...prev, selectedOrder.id]
      );
      fetchOrders(filterStatus);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Lỗi gửi thông tin hoàn tiền!');
    } finally {
      setIsRefundSubmitting(false);
    }
  };

  const openReviewModal = (order) => {
    const reviewableItem = (order.items || []).find((item) => item.can_review);

    if (!reviewableItem) {
      toast.info('Đơn hàng này không còn sản phẩm nào có thể đánh giá.');
      return;
    }

    setSelectedOrder(order);
    setSelectedReviewItem(reviewableItem);
    setReviewData({ rating: 5, comment: '' });
    setIsReviewModalOpen(true);
  };

  const handleSubmitReview = async (event) => {
    event.preventDefault();
    const orderItemId = getOrderItemId(selectedReviewItem);

    if (!orderItemId) {
      toast.error('Không xác định được sản phẩm cần đánh giá.');
      return;
    }

    try {
      await reviewApi.submitOrderItemReview(orderItemId, {
        rating: Number(reviewData.rating),
        comment: reviewData.comment.trim() || null,
      });
      toast.success('Đã gửi đánh giá. Cảm ơn bạn!');
      setIsReviewModalOpen(false);
      setSelectedReviewItem(null);
      fetchOrders(filterStatus);
    } catch (error) {
      toast.error(error.response?.data?.message || 'Không thể gửi đánh giá lúc này.');
    }
  };

  const renderOrderItems = (items) => {
    return items.map((item) => {
      const bookData = item.book || {};
      const bookName = item.book_name || bookData.name || bookData.title || 'Sản phẩm';
      const thumbnail = resolveMediaUrl(
        item.thumbnail_url ||
        (bookData.images && (bookData.images[0]?.url || bookData.images[0]?.image_url)) ||
        bookData.thumbnail_url ||
        bookData.thumbnail
      );
      const quantity = Number(item.quantity || 0);
      const lineTotal = Number(item.total_price ?? 0);
      const unitPrice = Number(item.price ?? 0);
      const displayPrice = lineTotal > 0 ? lineTotal : unitPrice;

      return (
        <div key={getOrderItemId(item) || `${bookName}-${thumbnail}`} className="flex gap-4 items-center mb-4 last:mb-0">
          <img
            src={thumbnail}
            alt={bookName}
            className="w-16 h-24 object-cover border border-gray-200 rounded"
            onError={(event) => {
              event.currentTarget.src = 'https://placehold.co/80x120?text=No+Image';
            }}
          />
          <div className="flex-grow">
            <h3 className="font-medium text-gray-800 line-clamp-2">{bookName}</h3>
            {quantity > 0 && <div className="text-sm text-gray-500">Số lượng: x{quantity}</div>}
          </div>
          <div className="font-bold text-primary">
            {displayPrice > 0 ? formatCurrency(displayPrice) : 'Đang cập nhật'}
          </div>
        </div>
      );
    });
  };

  const isRefundLocked = selectedOrder
    ? submittedRefundOrderIds.includes(selectedOrder.id)
    : false;
  const refundFormDisabled = isRefundSubmitting || isRefundLocked;

  return (
    <div className="bg-white p-4 md:p-8 rounded-lg shadow-sm border border-gray-100">
      <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Đơn hàng của tôi</h1>

      <div className="flex gap-2 mb-6 border-b pb-2 overflow-x-auto whitespace-nowrap scrollbar-hide">
        {ORDER_STATUSES.map((status, index) => (
          <button
            key={index}
            onClick={() => setFilterStatus(status.value)}
            className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
              filterStatus === status.value
                ? 'bg-primary text-white shadow-md'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            {status.label}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="py-20 flex justify-center">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
        </div>
      ) : orders.length === 0 ? (
        <div className="text-center py-16 flex flex-col items-center justify-center bg-gray-50 rounded-lg border border-dashed border-gray-200">
          <FiPackage size={48} className="text-gray-300 mb-3" />
          <p className="text-gray-500 font-medium">Không tìm thấy đơn hàng nào ở trạng thái này.</p>
        </div>
      ) : (
        <div className="flex flex-col gap-6">
          {orders.map((order) => {
            const currentStatus = getEnumValue(order.current_status);
            const paymentStatus = getEnumValue(order.payment_status);
            const paymentMethod = getEnumValue(order.payment_method);
            const statusUi = ORDER_STATUSES.find((status) => status.value === currentStatus) || ORDER_STATUSES[0];
            const paymentStatusUi = getPaymentStatusUi(paymentStatus);
            const isVnpayPending = paymentMethod === 'vnpay' && paymentStatus === 'pending' && currentStatus !== 'cancelled';
            const canRefund = !submittedRefundOrderIds.includes(order.id) && (
              paymentStatus === 'refunding' ||
              (currentStatus === 'cancelled' && paymentStatus === 'paid')
            );

            return (
              <div key={order.id} className="border border-gray-200 rounded-lg overflow-hidden">
                <div className="bg-gray-50 p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2 text-sm">
                  <div className="flex gap-4 items-center">
                    <span className="font-bold text-gray-800">Mã đơn: #{order.id}</span>
                    <span className="text-gray-500">{new Date(order.created_at).toLocaleDateString('vi-VN')}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    {paymentMethod === 'vnpay' && (
                      <span className={`text-xs font-bold px-2 py-1 rounded ${paymentStatusUi.color}`}>
                        {paymentStatusUi.label}
                      </span>
                    )}
                    <span className={`font-medium px-3 py-1 rounded-full text-xs ${statusUi.color}`}>
                      {statusUi.label}
                    </span>
                  </div>
                </div>

                <div className="p-4">
                  {renderOrderItems(order.items || [])}
                </div>

                <div className="bg-gray-50 p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                  <div className="text-sm text-gray-600">
                    Thành tiền: <span className="text-xl font-bold text-danger">{formatCurrency(order.final_amount)}</span>
                  </div>

                  <div className="flex gap-2 w-full sm:w-auto flex-wrap justify-end">
                    {isVnpayPending && (
                      <button
                        onClick={() => handleRetryPayment(order.id)}
                        className="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 transition flex items-center gap-2"
                      >
                        <FiClock /> Thanh toán ngay
                      </button>
                    )}

                    {canRefund && (
                      <button
                        onClick={() => openRefundModal(order)}
                        className="px-4 py-2 bg-purple-600 text-white rounded text-sm font-medium hover:bg-purple-700 transition flex items-center gap-2"
                      >
                        <FiAlertCircle /> Nhận hoàn tiền
                      </button>
                    )}

                    {currentStatus === 'pending' && (
                      <button
                        onClick={() => { setSelectedOrder(order); setIsCancelModalOpen(true); }}
                        className="px-4 py-2 border border-red-500 text-red-500 bg-white rounded text-sm font-medium hover:bg-red-50 transition"
                      >
                        Hủy đơn
                      </button>
                    )}

                    {currentStatus === 'completed' && (order.items || []).some((item) => item.can_review) && (
                      <button
                        onClick={() => openReviewModal(order)}
                        className="px-4 py-2 border border-primary text-primary bg-white rounded text-sm font-medium hover:bg-green-50 transition flex items-center gap-2"
                      >
                        <FiStar /> Đánh giá
                      </button>
                    )}

                    <button
                      onClick={() => handleViewDetails(order.id)}
                      className="px-4 py-2 bg-primary text-white rounded text-sm font-medium hover:bg-green-800 transition"
                    >
                      Chi tiết
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {isDetailModalOpen && selectedOrder && (
        <div className="app-modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center p-4 border-b border-gray-100 sticky top-0 bg-white z-10">
              <h2 className="text-lg font-bold text-gray-800">Chi tiết đơn hàng #{selectedOrder.id}</h2>
              <button onClick={() => setIsDetailModalOpen(false)} className="text-gray-400 hover:text-red-500"><FiX size={24}/></button>
            </div>

            <div className="p-6">
              <div className="bg-green-50 border border-green-100 p-4 rounded-lg mb-6">
                <h3 className="font-bold text-primary mb-2 uppercase text-xs">Thông tin giao hàng</h3>
                <p className="font-medium text-gray-800">{selectedOrder.shipping_name}</p>
                <p className="text-sm text-gray-600">SĐT: {selectedOrder.shipping_phone}</p>
                <p className="text-sm text-gray-600">Địa chỉ: {selectedOrder.shipping_address}</p>
                <p className="text-sm text-gray-600 mt-2">Ghi chú: {selectedOrder.note || 'Không có ghi chú'}</p>
              </div>

              <h3 className="font-bold text-gray-800 mb-3 border-b pb-2">Danh sách sản phẩm</h3>
              <div className="mb-6">
                {renderOrderItems(selectedOrder.items || [])}
              </div>

              <div className="bg-gray-50 p-4 rounded-lg text-sm text-gray-600 flex flex-col gap-2">
                <div className="flex justify-between"><span>Tổng tiền sách:</span> <span>{formatCurrency(selectedOrder.total_amount)}</span></div>
                <div className="flex justify-between"><span>Phí vận chuyển:</span> <span>{formatCurrency(selectedOrder.shipping_fee)}</span></div>
                <div className="flex justify-between border-t pt-2 mt-2">
                  <span className="font-bold text-gray-800">Thành tiền:</span>
                  <span className="font-bold text-xl text-danger">{formatCurrency(selectedOrder.final_amount)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {isCancelModalOpen && selectedOrder && (
        <div className="app-modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-4">Xác nhận hủy đơn</h2>
            <p className="text-gray-600 mb-6">
              Bạn có chắc chắn muốn hủy đơn hàng <strong>#{selectedOrder.id}</strong> không? Hành động này không thể hoàn tác.
            </p>
            <div className="flex gap-4 justify-end">
              <button onClick={() => setIsCancelModalOpen(false)} className="px-4 py-2 bg-gray-200 text-gray-800 rounded font-medium hover:bg-gray-300">Đóng</button>
              <button onClick={handleCancelOrder} className="px-4 py-2 bg-red-600 text-white rounded font-medium hover:bg-red-700">Đồng ý hủy</button>
            </div>
          </div>
        </div>
      )}

      {isRefundModalOpen && selectedOrder && (
        <div className="app-modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-gray-800">Nhập thông tin hoàn tiền</h2>
              <button onClick={() => setIsRefundModalOpen(false)} className="text-gray-400 hover:text-red-500"><FiX size={24}/></button>
            </div>

            <p className="text-sm text-gray-600 mb-4 bg-purple-50 p-3 rounded border border-purple-100">
              Đơn hàng <strong>#{selectedOrder.id}</strong> của bạn đã thanh toán nhưng bị hủy/giao thất bại. Vui lòng cung cấp STK để nhận lại <strong>{formatCurrency(selectedOrder.final_amount)}</strong>.
            </p>

            {isRefundLocked && (
              <div className="text-sm text-gray-600 mb-4 bg-gray-50 p-3 rounded border border-gray-200">
                Thông tin hoàn tiền đã được gửi. Form đang được khóa trong khi chờ xử lý.
              </div>
            )}

            <form onSubmit={handleSubmitRefund} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Ngân hàng *</label>
                <select
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none"
                  value={refundData.bank_code}
                  onChange={(event) => setRefundData({ ...refundData, bank_code: event.target.value })}
                  disabled={refundFormDisabled}
                  required
                >
                  <option value="">-- Chọn ngân hàng --</option>
                  {refundBanks.map((bank) => (
                    <option key={bank.code} value={bank.code}>{bank.short_name} - {bank.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Số tài khoản *</label>
                <input
                  type="text"
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none"
                  value={refundData.account_number}
                  onChange={(event) => setRefundData({ ...refundData, account_number: event.target.value })}
                  placeholder="Nhập số tài khoản..."
                  disabled={refundFormDisabled}
                  required
                />
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Tên chủ tài khoản *</label>
                <input
                  type="text"
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none uppercase"
                  value={refundData.account_holder}
                  onChange={(event) => setRefundData({ ...refundData, account_holder: event.target.value.toUpperCase() })}
                  placeholder="NGUYEN VAN A"
                  disabled={refundFormDisabled}
                  required
                />
              </div>

              <button
                type="submit"
                disabled={refundFormDisabled}
                className="w-full bg-primary text-white rounded p-3 font-bold hover:bg-green-800 transition mt-2 disabled:opacity-60 disabled:cursor-not-allowed"
              >
                {isRefundSubmitting
                  ? 'Đang gửi...'
                  : isRefundLocked
                    ? 'Đã gửi thông tin'
                    : 'Gửi thông tin hoàn tiền'}
              </button>
            </form>
          </div>
        </div>
      )}

      {isReviewModalOpen && selectedReviewItem && (
        <div className="app-modal-backdrop fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-gray-800">Đánh giá sản phẩm</h2>
              <button onClick={() => setIsReviewModalOpen(false)} className="text-gray-400 hover:text-red-500"><FiX size={24}/></button>
            </div>

            <div className="bg-gray-50 border border-gray-100 rounded p-3 mb-4 text-sm text-gray-700">
              <div className="font-bold text-gray-800 line-clamp-2">
                {selectedReviewItem.book_name || selectedReviewItem.book?.name || 'Sản phẩm'}
              </div>
              {selectedOrder?.id && (
                <div className="text-gray-500 mt-1">Đơn hàng #{selectedOrder.id}</div>
              )}
            </div>

            <form onSubmit={handleSubmitReview} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Điểm đánh giá *</label>
                <div className="flex items-center gap-2">
                  {[1, 2, 3, 4, 5].map((rating) => (
                    <button
                      key={rating}
                      type="button"
                      onClick={() => setReviewData({ ...reviewData, rating })}
                      className="p-1"
                      aria-label={`${rating} sao`}
                    >
                      <FiStar
                        size={28}
                        className={`app-rating-star is-clickable ${Number(reviewData.rating) >= rating ? 'is-filled fill-current' : ''}`}
                      />
                    </button>
                  ))}
                  <span className="text-sm text-gray-600 font-medium">{reviewData.rating}/5</span>
                </div>
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Bình luận</label>
                <textarea
                  rows="4"
                  maxLength="2000"
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none"
                  value={reviewData.comment}
                  onChange={(event) => setReviewData({ ...reviewData, comment: event.target.value })}
                  placeholder="Chia sẻ cảm nhận của bạn về cuốn sách..."
                />
              </div>

              <button type="submit" className="w-full bg-primary text-white rounded p-3 font-bold hover:bg-green-800 transition">
                Gửi đánh giá
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default MyOrders;
