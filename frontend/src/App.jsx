// src/App.jsx
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import MainLayout from './components/layout/MainLayout';
import HomePage from './components/Home/HomePage';
import CartPage from './pages/Cart/CartPage';
import CategoryPage from './pages/Category/CategoryPage';
import ProductDetailPage from './pages/ProductDetail/ProductDetailPage';
import LoginPage from './pages/Auth/LoginPage';
import RegisterPage from './pages/Auth/RegisterPage';
import CheckoutPage from './pages/Checkout/CheckoutPage';
import ProfilePage from './pages/Profile/ProfilePage';
// THÊM DÒNG IMPORT NÀY
import WishlistPage from './Wishlist/WishlistPage'; 
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import ForgotPasswordPage from './pages/Auth/ForgotPasswordPage';



function App() {
  return (
    <BrowserRouter>
    <ToastContainer position="top-right" autoClose={3000} />
      <Routes>
        <Route path="/" element={<MainLayout />}>
          <Route index element={<HomePage />} />
          <Route path="cart" element={<CartPage />} />
          <Route path="checkout" element={<CheckoutPage />} />
          <Route path="profile" element={<ProfilePage />} />
          <Route path="login" element={<LoginPage />} />
          <Route path="register" element={<RegisterPage />} />
          <Route path="forgot-password" element={<ForgotPasswordPage />} />
          
          {/* THÊM ROUTE WISHLIST TẠI ĐÂY */}
          <Route path="wishlist" element={<WishlistPage />} />

          <Route path="category/:slug" element={<CategoryPage />} />
          <Route path="sach-moi" element={<CategoryPage />} />
          <Route path="sach-ban-chay" element={<CategoryPage />} />
          <Route path="sach-xu-huong" element={<CategoryPage />} />
          <Route path="an-pham-dac-biet" element={<CategoryPage />} />
          <Route path="sach-dat-truoc" element={<CategoryPage />} />
          <Route path="search" element={<CategoryPage />} />
          <Route path="book/:slug" element={<ProductDetailPage />} />
          <Route path="*" element={<h2 className="text-center mt-10 text-2xl text-red-500">404 - Không tìm thấy trang</h2>} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;