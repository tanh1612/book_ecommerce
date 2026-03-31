import { Outlet } from 'react-router-dom';
import Header from './Header';
import Footer from './Footer';
import Chatbot from '../UI/Chatbot';

const MainLayout = () => {
  return (
    <div className="flex flex-col min-h-screen">
      <Header />
      {/* Outlet chính là phần nội dung sẽ thay đổi (Trang chủ, Giỏ hàng...) */}
      <main className="flex-grow container mx-auto px-4 py-6">
        <Outlet />
      </main>
      <Footer />
    <Chatbot />

    </div>
  );
};

export default MainLayout;