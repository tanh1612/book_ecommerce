// src/components/Home/FlashSaleTimer.jsx
import { useState, useEffect } from 'react';

const FlashSaleTimer = () => {
  // Giả định Flash Sale kết thúc vào cuối ngày hôm nay
  const calculateTimeLeft = () => {
    const difference = new Date().setHours(23, 59, 59) - new Date();
    let timeLeft = {};

    if (difference > 0) {
      timeLeft = {
        h: Math.floor((difference / (1000 * 60 * 60)) % 24),
        m: Math.floor((difference / 1000 / 60) % 60),
        s: Math.floor((difference / 1000) % 60),
      };
    }
    return timeLeft;
  };

  const [timeLeft, setTimeLeft] = useState(calculateTimeLeft());

  useEffect(() => {
    const timer = setInterval(() => {
      setTimeLeft(calculateTimeLeft());
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const formatNumber = (num) => String(num).padStart(2, '0');

  return (
    <div className="flex items-center gap-2">
      <span className="text-white text-sm font-bold hidden sm:block uppercase">Kết thúc sau:</span>
      <div className="flex gap-1.5">
        {[timeLeft.h, timeLeft.m, timeLeft.s].map((unit, index) => (
          <div key={index} className="flex items-center">
            <div className="bg-white text-[#157a2c] w-8 h-8 rounded-md flex items-center justify-center font-bold text-lg shadow-sm">
              {formatNumber(unit || 0)}
            </div>
            {index < 2 && <span className="text-white font-bold mx-0.5">:</span>}
          </div>
        ))}
      </div>
    </div>
  );
};

export default FlashSaleTimer;