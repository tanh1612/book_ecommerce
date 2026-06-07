// src/components/Home/FlashSaleTimer.jsx
import { useState, useEffect, useCallback } from 'react';

const FlashSaleTimer = ({ endAt }) => {
  // Fallback to end of day when the API response does not include an end time.
  const calculateTimeLeft = useCallback(() => {
    const fallbackDeadline = new Date().setHours(23, 59, 59, 999);
    const deadline = endAt ? new Date(endAt).getTime() : fallbackDeadline;
    const difference = deadline - Date.now();
    let timeLeft = {};

    if (difference > 0) {
      timeLeft = {
        h: Math.floor((difference / (1000 * 60 * 60)) % 24),
        m: Math.floor((difference / 1000 / 60) % 60),
        s: Math.floor((difference / 1000) % 60),
      };
    }
    return timeLeft;
  }, [endAt]);

  const [timeLeft, setTimeLeft] = useState(() => calculateTimeLeft());

  useEffect(() => {
    setTimeLeft(calculateTimeLeft());
    const timer = setInterval(() => {
      setTimeLeft(calculateTimeLeft());
    }, 1000);
    return () => clearInterval(timer);
  }, [calculateTimeLeft]);

  const formatNumber = (num) => String(num).padStart(2, '0');

  return (
    <div className="flex items-center gap-2">
      <span className="flash-sale-timer-label text-sm font-bold hidden sm:block uppercase">Kết thúc sau:</span>
      <div className="flex gap-1.5">
        {[timeLeft.h, timeLeft.m, timeLeft.s].map((unit, index) => (
          <div key={index} className="flex items-center">
            <div className="flash-sale-timer-box w-8 h-8 rounded-md flex items-center justify-center font-bold text-lg">
              {formatNumber(unit || 0)}
            </div>
            {index < 2 && <span className="flash-sale-timer-separator font-bold mx-0.5">:</span>}
          </div>
        ))}
      </div>
    </div>
  );
};

export default FlashSaleTimer;
