import { BrowserRouter, Routes, Route } from 'react-router-dom';
import MainLayout from './components/layout/MainLayout';
import HomePage from './pages/Home/HomePage';
import CartPage from './pages/Cart/CartPage';
import CategoryPage from './pages/Category/CategoryPage';
import ProductDetailPage from './pages/ProductDetail/ProductDetailPage';
import LoginPage from './pages/Auth/LoginPage';
import RegisterPage from './pages/Auth/RegisterPage';
import CheckoutPage from './pages/Checkout/CheckoutPage';
import ProfilePage from './pages/Profile/ProfilePage';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Route cha dùng MainLayout làm khung */}
        <Route path="/" element={<MainLayout />}>
          {/* Các Route con sẽ được render vào vị trí của <Outlet /> */}
          <Route index element={<HomePage />} />
          <Route path="cart" element={<CartPage />} />

          <Route path="checkout" element={<CheckoutPage />} />
          <Route path="profile" element={<ProfilePage />} />

          <Route path="login" element={<LoginPage />} />
          <Route path="register" element={<RegisterPage />} />

          <Route path="category/:slug" element={<CategoryPage />} />
          <Route path="sach-moi" element={<CategoryPage />} />
          <Route path="sach-ban-chay" element={<CategoryPage />} />
          <Route path="sach-xu-huong" element={<CategoryPage />} />
          <Route path="an-pham-dac-biet" element={<CategoryPage />} />
          <Route path="sach-dat-truoc" element={<CategoryPage />} />
          
          <Route path="search" element={<CategoryPage />} />
          {/* Nếu người dùng nhập sai URL, hiển thị trang 404 */}
          <Route path="book/:slug" element={<ProductDetailPage />} />
          <Route path="*" element={<h2 className="text-center mt-10 text-2xl text-red-500">404 - Không tìm thấy trang</h2>} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;