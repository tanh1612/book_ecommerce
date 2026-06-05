// src/App.jsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import MainLayout from './components/layout/MainLayout';
import HomePage from './components/Home/HomePage';
import CartPage from './pages/Cart/CartPage';
import CategoryPage from './pages/Category/CategoryPage';
import ProductDetailPage from './pages/ProductDetail/ProductDetailPage';
import LoginPage from './pages/Auth/LoginPage';
import RegisterPage from './pages/Auth/RegisterPage';
import CheckoutPage from './pages/Checkout/CheckoutPage';
import ProfilePage from './pages/Profile/ProfilePage';
import WishlistPage from './Wishlist/WishlistPage'; 
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import ForgotPasswordPage from './pages/Auth/ForgotPasswordPage';
import { useAuth } from './context/AuthContext';

const RequireAuth = ({ children }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="py-20 text-center text-gray-500">Đang tải...</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return children;
};

function App() {
  return (
    <BrowserRouter>
      <ToastContainer position="top-right" autoClose={3000} />
      <Routes>
        <Route path="/" element={<MainLayout />}>
          <Route index element={<HomePage />} />
          <Route path="cart" element={<CartPage />} />
          <Route path="checkout" element={<RequireAuth><CheckoutPage /></RequireAuth>} />
          <Route path="profile" element={<RequireAuth><ProfilePage /></RequireAuth>} />
          <Route path="login" element={<LoginPage />} />
          <Route path="register" element={<RegisterPage />} />
          <Route path="forgot-password" element={<ForgotPasswordPage />} />
          <Route path="wishlist" element={<RequireAuth><WishlistPage /></RequireAuth>} />

          {/* === ROUTE MỚI DÀNH CHO BỘ LỌC TỔNG HỢP === */}
          <Route path="catalog" element={<CategoryPage />} />
          
          <Route path="search" element={<CategoryPage />} />
          <Route path="book/:slug" element={<ProductDetailPage />} />

          {/* CHUYỂN HƯỚNG CÁC LINK CŨ ĐỂ KHÔNG BỊ LỖI NẾU KHÁCH LƯU LINK TRƯỚC ĐÓ */}
          <Route path="sach-moi" element={<Navigate to="/catalog?sort=newest" replace />} />
          <Route path="sach-ban-chay" element={<Navigate to="/catalog?sort=best_selling" replace />} />
          <Route path="sach-xu-huong" element={<Navigate to="/catalog?sort=trending" replace />} />
          <Route path="an-pham-dac-biet" element={<Navigate to="/catalog" replace />} />
          <Route path="sach-dat-truoc" element={<Navigate to="/catalog" replace />} />
          <Route path="category/:slug" element={<Navigate to="/catalog" replace />} />

          {/* Trang 404 */}
          <Route path="*" element={<h2 className="text-center mt-20 text-2xl font-bold text-red-500">404 - Không tìm thấy trang</h2>} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
