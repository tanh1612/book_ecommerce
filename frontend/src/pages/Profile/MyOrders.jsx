// src/pages/Profile/MyOrders.jsx
import { useState, useEffect } from 'react';
import { FiX, FiAlertCircle, FiClock, FiPackage, FiStar } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { formatCurrency } from '../../utils/formatters';
import { resolveMediaUrl } from '../../utils/media';
import orderApi from '../../services/orderApi';
import reviewApi from '../../services/reviewApi';

// Danh sÃ¡ch cÃ¡c tráº¡ng thÃ¡i dÃ¹ng cho Tab Bá»™ lá»c & Hiá»ƒn thá»‹ (Khá»›p 100% vá»›i OrderStatus.php)
const ORDER_STATUSES = [
  { label: 'Táº¥t cáº£', value: null, color: 'text-gray-800 bg-gray-100' },
  { label: 'Chá» xá»­ lÃ½', value: 'pending', color: 'text-yellow-600 bg-yellow-100' },
  { label: 'ÄÃ£ xÃ¡c nháº­n', value: 'confirmed', color: 'text-blue-600 bg-blue-100' },
  { label: 'Äang xá»­ lÃ½', value: 'processing', color: 'text-indigo-600 bg-indigo-100' },
  { label: 'Äang giao hÃ ng', value: 'shipping', color: 'text-gray-600 bg-gray-200' },
  { label: 'HoÃ n táº¥t', value: 'completed', color: 'text-green-600 bg-green-100' },
  { label: 'ÄÃ£ há»§y', value: 'cancelled', color: 'text-red-600 bg-red-100' },
  { label: 'KhÃ´ng hoÃ n tiá»n', value: 'refund_closed', color: 'text-gray-500 bg-gray-100' },
];

const getOrderItemId = (item) => item.id || item.review_target_id;

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
  
  // State quáº£n lÃ½ Modals
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [isRefundModalOpen, setIsRefundModalOpen] = useState(false);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
  const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);
  const [selectedReviewItem, setSelectedReviewItem] = useState(null);
  const [reviewData, setReviewData] = useState({ rating: 5, comment: '' });
  
  // State Form HoÃ n tiá»n (Khá»›p vá»›i OrderRefundBankInfoController)
  const [refundBanks, setRefundBanks] = useState([]);
  const [refundData, setRefundData] = useState({ bank_code: '', account_number: '', account_holder: '' });

  // 1. Láº¤Y DANH SÃCH ÄÆ N HÃ€NG (CÃ“ Lá»ŒC)
  const fetchOrders = async (status = null) => {
    setIsLoading(true);
    try {
      const res = await orderApi.getOrders(status ? { status } : {});
      const orderList = res.data?.data || [];
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

      setOrders(enrichedOrders);
    } catch {
      toast.error("KhÃ´ng thá»ƒ táº£i danh sÃ¡ch Ä‘Æ¡n hÃ ng!");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders(filterStatus);
  }, [filterStatus]);

  // 2. Xá»¬ LÃ Há»¦Y ÄÆ N
  const handleCancelOrder = async () => {
    if (!selectedOrder) return;
    try {
      await orderApi.cancelOrder(selectedOrder.id);
      toast.success("Há»§y Ä‘Æ¡n hÃ ng thÃ nh cÃ´ng!");
      setIsCancelModalOpen(false);
      fetchOrders(filterStatus);
    } catch (error) {
      toast.error(error.response?.data?.message || "Lá»—i khi há»§y Ä‘Æ¡n hÃ ng!");
    }
  };

  // 3. Xá»¬ LÃ THANH TOÃN Láº I VNPAY
  const handleRetryPayment = async (orderId) => {
    try {
      toast.info("Äang táº¡o link thanh toÃ¡n má»›i...");
      const res = await orderApi.getVnPayPaymentUrl(orderId);
      
      const paymentUrl = res.data?.data?.payment_url;
      
      if (paymentUrl && typeof paymentUrl === 'string' && paymentUrl.startsWith('http')) {
        window.location.href = paymentUrl; // Chuyá»ƒn hÆ°á»›ng sang VNPay
      } else {
        toast.error("KhÃ´ng nháº­n Ä‘Æ°á»£c link thanh toÃ¡n tá»« há»‡ thá»‘ng!");
      }
    } catch (error) {
      toast.error(error.response?.data?.message || "Lá»—i khi táº¡o láº¡i thanh toÃ¡n VNPay!");
    }
  };

  // 4. Xá»¬ LÃ XEM CHI TIáº¾T
  const handleViewDetails = async (orderId) => {
    try {
      toast.info("Äang táº£i dá»¯ liá»‡u...", { autoClose: 500 });
      const res = await orderApi.getOrderDetail(orderId);
      const summaryOrder = orders.find((order) => order.id === orderId);
      setSelectedOrder(mergeOrderSummaryAndDetail(summaryOrder || {}, res.data?.data || res.data));
      setIsDetailModalOpen(true);
    } catch {
      toast.error("KhÃ´ng thá»ƒ láº¥y chi tiáº¿t Ä‘Æ¡n hÃ ng");
    }
  };

  // 5. Xá»¬ LÃ HOÃ€N TIá»€N
  const openRefundModal = async (order) => {
    setSelectedOrder(order);
    setIsRefundModalOpen(true);
    try {
      const res = await orderApi.getRefundBanks();
      setRefundBanks(res.data?.data || res.data || []);
    } catch {
      toast.error("KhÃ´ng thá»ƒ táº£i danh sÃ¡ch ngÃ¢n hÃ ng");
    }
  };

  const handleSubmitRefund = async (e) => {
    e.preventDefault();
    try {
      await orderApi.submitRefundBank(selectedOrder.id, refundData);
      toast.success("ÄÃ£ gá»­i thÃ´ng tin nháº­n hoÃ n tiá»n thÃ nh cÃ´ng!");
      setIsRefundModalOpen(false);
      fetchOrders(filterStatus);
    } catch (error) {
      toast.error(error.response?.data?.message || "Lá»—i gá»­i thÃ´ng tin hoÃ n tiá»n!");
    }
  };

  const openReviewModal = (order) => {
    const reviewableItem = (order.items || []).find((item) => item.can_review);

    if (!reviewableItem) {
      toast.info('ÄÆ¡n hÃ ng nÃ y khÃ´ng cÃ²n sáº£n pháº©m nÃ o cÃ³ thá»ƒ Ä‘Ã¡nh giÃ¡.');
      return;
    }

    setSelectedOrder(order);
    setSelectedReviewItem(reviewableItem);
    setReviewData({ rating: 5, comment: '' });
    setIsReviewModalOpen(true);
  };

  const handleSubmitReview = async (e) => {
    e.preventDefault();
    const orderItemId = getOrderItemId(selectedReviewItem);

    if (!orderItemId) {
      toast.error('KhÃ´ng xÃ¡c Ä‘á»‹nh Ä‘Æ°á»£c sáº£n pháº©m cáº§n Ä‘Ã¡nh giÃ¡.');
      return;
    }

    try {
      await reviewApi.submitOrderItemReview(orderItemId, {
        rating: Number(reviewData.rating),
        comment: reviewData.comment.trim() || null,
      });
      toast.success('ÄÃ£ gá»­i Ä‘Ã¡nh giÃ¡. Cáº£m Æ¡n báº¡n!');
      setIsReviewModalOpen(false);
      setSelectedReviewItem(null);
      fetchOrders(filterStatus);
    } catch (error) {
      toast.error(error.response?.data?.message || 'KhÃ´ng thá»ƒ gá»­i Ä‘Ã¡nh giÃ¡ lÃºc nÃ y.');
    }
  };

  // UI Render Items
  const renderOrderItems = (items) => {
    return items.map((item) => {
      const bookData = item.book || {};
      const bookName = item.book_name || bookData.name || bookData.title || "Sáº£n pháº©m";
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
            {quantity > 0 && <div className="text-sm text-gray-500">Sá»‘ lÆ°á»£ng: x{quantity}</div>}
          </div>
          <div className="font-bold text-primary">
            {displayPrice > 0 ? formatCurrency(displayPrice) : 'Äang cáº­p nháº­t'}
          </div>
        </div>
      );
    });
  };

  return (
    <div className="bg-white p-4 md:p-8 rounded-lg shadow-sm border border-gray-100">
      <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">ÄÆ¡n hÃ ng cá»§a tÃ´i</h1>
      
      {/* --- TAB Bá»˜ Lá»ŒC TRáº NG THÃI --- */}
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
        <div className="py-20 flex justify-center"><div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div></div>
      ) : orders.length === 0 ? (
        <div className="text-center py-16 flex flex-col items-center justify-center bg-gray-50 rounded-lg border border-dashed border-gray-200">
          <FiPackage size={48} className="text-gray-300 mb-3" />
          <p className="text-gray-500 font-medium">KhÃ´ng tÃ¬m tháº¥y Ä‘Æ¡n hÃ ng nÃ o á»Ÿ tráº¡ng thÃ¡i nÃ y.</p>
        </div>
      ) : (
        <div className="flex flex-col gap-6">
          {orders.map((order) => {
            // An toÃ n Ã©p kiá»ƒu Enum thÃ nh String
            const currentStatus = typeof order.current_status === 'object' ? order.current_status.value : order.current_status;
            const paymentStatus = typeof order.payment_status === 'object' ? order.payment_status.value : order.payment_status;
            const paymentMethod = typeof order.payment_method === 'object' ? order.payment_method.value : order.payment_method;
            
            const statusUi = ORDER_STATUSES.find(s => s.value === currentStatus) || ORDER_STATUSES[0];
            
            // Logic hiá»ƒn thá»‹ nÃºt
            const isVnpayPending = paymentMethod === 'vnpay' && paymentStatus === 'pending' && currentStatus !== 'cancelled';
            const canRefund = paymentStatus === 'refunding' || (currentStatus === 'cancelled' && paymentStatus === 'paid');

            return (
              <div key={order.id} className="border border-gray-200 rounded-lg overflow-hidden">
                <div className="bg-gray-50 p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2 text-sm">
                  <div className="flex gap-4 items-center">
                    <span className="font-bold text-gray-800">MÃ£ Ä‘Æ¡n: #{order.id}</span>
                    <span className="text-gray-500">{new Date(order.created_at).toLocaleDateString('vi-VN')}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    {paymentMethod === 'vnpay' && (
                       <span className={`text-xs font-bold px-2 py-1 rounded ${paymentStatus === 'paid' ? 'text-green-700 bg-green-100' : 'text-orange-700 bg-orange-100'}`}>
                         {paymentStatus === 'paid' ? 'ÄÃ£ TT VNPay' : 'ChÆ°a TT VNPay'}
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
                    ThÃ nh tiá»n: <span className="text-xl font-bold text-danger">{formatCurrency(order.final_amount)}</span>
                  </div>
                  
                  <div className="flex gap-2 w-full sm:w-auto flex-wrap justify-end">
                    
                    {/* NÃšT THANH TOÃN Láº I VNPAY */}
                    {isVnpayPending && (
                      <button 
                        onClick={() => handleRetryPayment(order.id)} 
                        className="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 transition flex items-center gap-2"
                      >
                         <FiClock /> Thanh toÃ¡n ngay
                      </button>
                    )}

                    {/* NÃšT HOÃ€N TIá»€N */}
                    {canRefund && (
                      <button 
                        onClick={() => openRefundModal(order)} 
                        className="px-4 py-2 bg-purple-600 text-white rounded text-sm font-medium hover:bg-purple-700 transition flex items-center gap-2"
                      >
                        <FiAlertCircle /> Nháº­n hoÃ n tiá»n
                      </button>
                    )}

                    {/* NÃšT Há»¦Y ÄÆ N */}
                    {currentStatus === 'pending' && (
                      <button 
                        onClick={() => { setSelectedOrder(order); setIsCancelModalOpen(true); }} 
                        className="px-4 py-2 border border-red-500 text-red-500 bg-white rounded text-sm font-medium hover:bg-red-50 transition"
                      >
                        Há»§y Ä‘Æ¡n
                      </button>
                    )}

                    {/* NÃšT ÄÃNH GIÃ */}
                    {currentStatus === 'completed' && (order.items || []).some((item) => item.can_review) && (
                      <button 
                        onClick={() => openReviewModal(order)} 
                        className="px-4 py-2 border border-primary text-primary bg-white rounded text-sm font-medium hover:bg-green-50 transition flex items-center gap-2"
                      >
                        <FiStar /> ÄÃ¡nh giÃ¡
                      </button>
                    )}

                    {/* NÃšT XEM CHI TIáº¾T */}
                    <button 
                      onClick={() => handleViewDetails(order.id)} 
                      className="px-4 py-2 bg-primary text-white rounded text-sm font-medium hover:bg-green-800 transition"
                    >
                      Chi tiáº¿t
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* ================= MODAL CHI TIáº¾T ÄÆ N HÃ€NG ================= */}
      {isDetailModalOpen && selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center p-4 border-b border-gray-100 sticky top-0 bg-white z-10">
              <h2 className="text-lg font-bold text-gray-800">Chi tiáº¿t Ä‘Æ¡n hÃ ng #{selectedOrder.id}</h2>
              <button onClick={() => setIsDetailModalOpen(false)} className="text-gray-400 hover:text-red-500"><FiX size={24}/></button>
            </div>
            
            <div className="p-6">
              <div className="bg-green-50 border border-green-100 p-4 rounded-lg mb-6">
                <h3 className="font-bold text-primary mb-2 uppercase text-xs">ThÃ´ng tin giao hÃ ng</h3>
                <p className="font-medium text-gray-800">{selectedOrder.shipping_name}</p>
                <p className="text-sm text-gray-600">SÄT: {selectedOrder.shipping_phone}</p>
                <p className="text-sm text-gray-600">Äá»‹a chá»‰: {selectedOrder.shipping_address}</p>
                <p className="text-sm text-gray-600 mt-2">Ghi chÃº: {selectedOrder.note || 'KhÃ´ng cÃ³ ghi chÃº'}</p>
              </div>

              <h3 className="font-bold text-gray-800 mb-3 border-b pb-2">Danh sÃ¡ch sáº£n pháº©m</h3>
              <div className="mb-6">
                {renderOrderItems(selectedOrder.items || [])}
              </div>

              <div className="bg-gray-50 p-4 rounded-lg text-sm text-gray-600 flex flex-col gap-2">
                <div className="flex justify-between"><span>Tá»•ng tiá»n sÃ¡ch:</span> <span>{formatCurrency(selectedOrder.total_amount)}</span></div>
                <div className="flex justify-between"><span>PhÃ­ váº­n chuyá»ƒn:</span> <span>{formatCurrency(selectedOrder.shipping_fee)}</span></div>
                <div className="flex justify-between border-t pt-2 mt-2">
                  <span className="font-bold text-gray-800">ThÃ nh tiá»n:</span> 
                  <span className="font-bold text-xl text-danger">{formatCurrency(selectedOrder.final_amount)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL Há»¦Y ÄÆ N ================= */}
      {isCancelModalOpen && selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-4">XÃ¡c nháº­n há»§y Ä‘Æ¡n</h2>
            <p className="text-gray-600 mb-6">Báº¡n cÃ³ cháº¯c cháº¯n muá»‘n há»§y Ä‘Æ¡n hÃ ng <strong>#{selectedOrder.id}</strong> khÃ´ng? HÃ nh Ä‘á»™ng nÃ y khÃ´ng thá»ƒ hoÃ n tÃ¡c.</p>
            <div className="flex gap-4 justify-end">
              <button onClick={() => setIsCancelModalOpen(false)} className="px-4 py-2 bg-gray-200 text-gray-800 rounded font-medium hover:bg-gray-300">ÄÃ³ng</button>
              <button onClick={handleCancelOrder} className="px-4 py-2 bg-red-600 text-white rounded font-medium hover:bg-red-700">Äá»“ng Ã½ Há»§y</button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL NHáº¬P THÃ”NG TIN HOÃ€N TIá»€N ================= */}
      {isRefundModalOpen && selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-gray-800">Nháº­p thÃ´ng tin hoÃ n tiá»n</h2>
              <button onClick={() => setIsRefundModalOpen(false)} className="text-gray-400 hover:text-red-500"><FiX size={24}/></button>
            </div>
            
            <p className="text-sm text-gray-600 mb-4 bg-purple-50 p-3 rounded border border-purple-100">
              ÄÆ¡n hÃ ng <strong>#{selectedOrder.id}</strong> cá»§a báº¡n Ä‘Ã£ thanh toÃ¡n nhÆ°ng bá»‹ há»§y/giao tháº¥t báº¡i. Vui lÃ²ng cung cáº¥p STK Ä‘á»ƒ nháº­n láº¡i <strong>{formatCurrency(selectedOrder.final_amount)}</strong>.
            </p>

            <form onSubmit={handleSubmitRefund} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">NgÃ¢n hÃ ng *</label>
                <select 
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none"
                  value={refundData.bank_code}
                  onChange={(e) => setRefundData({...refundData, bank_code: e.target.value})}
                  required
                >
                  <option value="">-- Chá»n ngÃ¢n hÃ ng --</option>
                  {refundBanks.map(bank => (
                    <option key={bank.code} value={bank.code}>{bank.short_name} - {bank.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Sá»‘ tÃ i khoáº£n *</label>
                <input 
                  type="text" 
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none"
                  value={refundData.account_number}
                  onChange={(e) => setRefundData({...refundData, account_number: e.target.value})}
                  placeholder="Nháº­p sá»‘ tÃ i khoáº£n..."
                  required
                />
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">TÃªn chá»§ tÃ i khoáº£n *</label>
                <input 
                  type="text" 
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none uppercase"
                  value={refundData.account_holder}
                  onChange={(e) => setRefundData({...refundData, account_holder: e.target.value.toUpperCase()})}
                  placeholder="NGUYEN VAN A"
                  required
                />
              </div>

              <button type="submit" className="w-full bg-primary text-white rounded p-3 font-bold hover:bg-green-800 transition mt-2">
                Gá»¬I THÃ”NG TIN HOÃ€N TIá»€N
              </button>
            </form>
          </div>
        </div>
      )}

      {isReviewModalOpen && selectedReviewItem && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
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
                <select
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none bg-white"
                  value={reviewData.rating}
                  onChange={(e) => setReviewData({ ...reviewData, rating: e.target.value })}
                  required
                >
                  {[5, 4.5, 4, 3.5, 3, 2.5, 2, 1.5, 1, 0.5].map((rating) => (
                    <option key={rating} value={rating}>{rating} sao</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Bình luận</label>
                <textarea
                  rows="4"
                  maxLength="2000"
                  className="w-full border border-gray-300 rounded p-2 focus:border-primary outline-none"
                  value={reviewData.comment}
                  onChange={(e) => setReviewData({ ...reviewData, comment: e.target.value })}
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


