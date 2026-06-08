import { Outlet } from "react-router-dom";
import Header from "./Header";
import Footer from "./Footer";
import Chatbot from "../UI/Chatbot";

const MainLayout = () => {
  return (
    <div className="flex flex-col min-h-screen">
      <Header />
      <main className="flex-grow bg-page-surface py-6">
        <Outlet />
      </main>
      <Footer />
      <Chatbot />
    </div>
  );
};

export default MainLayout;
