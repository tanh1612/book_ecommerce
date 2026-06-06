import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { FiAlertCircle, FiCheckCircle, FiChevronRight, FiClock, FiShoppingBag } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import orderApi from '../../services/orderApi';
import { formatCurrency } from '../../utils/formatters';

const STATUS_CONFIG = {
  paid: {
    icon: FiCheckCircle,
    title: 'Thanh toán thành công',
    description: 'Cảm ơn bạn. Hệ thống đã ghi nhận thanh toán VNPay cho đơn hàng.',
    tone: 'text-[#157a2c]',
    bg: 'bg-green-50',
    border: 'border-green-100',
  },
  failed: {
    icon: FiAlertCircle,
    title: 'Thanh toán chưa hoàn tất',
    description: 'Giao dịch chưa được xác nhận. Bạn có thể kiểm tra lại đơn hàng hoặc thanh toán lại nếu còn thời hạn.',
    tone: 'text-red-600',
    bg: 'bg-red-50',
    border: 'border-red-100',
  },
  pending: {
    icon: FiClock,
    title: 'Đang kiểm tra thanh toán',
    description: 'Hệ thống đang chờ xác nhận giao dịch. Vui lòng kiểm tra lại trạng thái đơn hàng sau ít phút.',
    tone: 'text-amber-600',
    bg: 'bg-amber-50',
    border: 'border-amber-100',
  },
};

const normalizeStatus = (value) => {
  const status = String(value || '').toLowerCase();

  if (status === 'paid' || status === 'success') return 'paid';
  if (status === 'pending') return 'pending';

  return 'failed';
};

const PaymentResultPage = () => {
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const [order, setOrder] = useState(null);
  const [isLoadingOrder, setIsLoadingOrder] = useState(false);

  const status = normalizeStatus(searchParams.get('status'));
  const orderId = searchParams.get('order_id');
  const message = searchParams.get('message');
  const config = STATUS_CONFIG[status];
  const StatusIcon = config.icon;

  const profileOrdersUrl = useMemo(() => {
    return orderId ? `/profile?tab=orders&order_id=${encodeURIComponent(orderId)}` : '/profile';
  }, [orderId]);

  useEffect(() => {
    if (!user || !orderId) return;

    let ignore = false;
    setIsLoadingOrder(true);

    orderApi.getOrderDetail(orderId)
      .then((res) => {
        if (!ignore) {
          setOrder(res.data?.data || res.data || null);
        }
      })
      .catch(() => {
        if (!ignore) setOrder(null);
      })
      .finally(() => {
        if (!ignore) setIsLoadingOrder(false);
      });

    return () => {
      ignore = true;
    };
  }, [orderId, user]);

  return (
    <div className="bg-gray-50 min-h-[70vh] pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c]">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Kết quả thanh toán</span>
        </div>
      </div>

      <div className="container mx-auto px-4 max-w-3xl">
        <div className={`bg-white border ${config.border} rounded-lg shadow-sm overflow-hidden`}>
          <div className={`${config.bg} px-6 py-8 text-center border-b ${config.border}`}>
            <StatusIcon className={`mx-auto mb-4 ${config.tone}`} size={56} />
            <h1 className="text-2xl font-bold text-gray-900 mb-2">{config.title}</h1>
            <p className="text-gray-600 max-w-xl mx-auto">{message || config.description}</p>
          </div>

          <div className="p-6">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
              <div className="border border-gray-100 rounded p-4">
                <div className="text-xs uppercase tracking-wide text-gray-500 mb-1">Mã đơn hàng</div>
                <div className="font-bold text-gray-900">{orderId ? `#${orderId}` : 'Chưa có thông tin'}</div>
              </div>
              <div className="border border-gray-100 rounded p-4">
                <div className="text-xs uppercase tracking-wide text-gray-500 mb-1">Trạng thái</div>
                <div className={`font-bold ${config.tone}`}>{config.title}</div>
              </div>
            </div>

            {isLoadingOrder && (
              <div className="border border-gray-100 rounded p-4 mb-6 text-sm text-gray-500 flex items-center gap-2">
                <div className="w-4 h-4 border-2 border-gray-300 border-t-[#157a2c] rounded-full animate-spin" />
                Đang tải thông tin đơn hàng...
              </div>
            )}

            {order && (
              <div className="border border-gray-100 rounded p-4 mb-6 bg-gray-50">
                <div className="flex items-center gap-2 font-bold text-gray-800 mb-3">
                  <FiShoppingBag className="text-[#157a2c]" />
                  Tóm tắt đơn hàng
                </div>
                <div className="space-y-2 text-sm text-gray-600">
                  <div className="flex justify-between gap-4">
                    <span>Người nhận</span>
                    <span className="font-medium text-gray-800 text-right">{order.shipping_name || 'Đang cập nhật'}</span>
                  </div>
                  <div className="flex justify-between gap-4">
                    <span>Số điện thoại</span>
                    <span className="font-medium text-gray-800 text-right">{order.shipping_phone || 'Đang cập nhật'}</span>
                  </div>
                  <div className="flex justify-between gap-4">
                    <span>Tổng thanh toán</span>
                    <span className="font-bold text-[#ff424e] text-right">{formatCurrency(order.final_amount || 0)}</span>
                  </div>
                </div>
              </div>
            )}

            <div className="flex flex-col sm:flex-row gap-3 justify-center">
              <Link
                to={user ? profileOrdersUrl : '/login'}
                className="bg-[#157a2c] text-white px-6 py-3 rounded font-bold text-center hover:bg-green-800 transition"
              >
                {user ? 'Xem đơn hàng' : 'Đăng nhập để xem đơn'}
              </Link>
              <Link
                to="/catalog"
                className="border border-[#157a2c] text-[#157a2c] px-6 py-3 rounded font-bold text-center hover:bg-green-50 transition"
              >
                Tiếp tục mua sắm
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PaymentResultPage;
