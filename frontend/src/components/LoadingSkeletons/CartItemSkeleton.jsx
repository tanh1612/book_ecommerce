// src/components/LoadingSkeletons/CartItemSkeleton.jsx
const CartItemSkeleton = () => {
  return (
    <div className="p-4 flex items-center gap-4 border-b border-gray-100 animate-pulse">
      <div className="w-4 h-4 bg-gray-200 rounded"></div>
      <div className="w-20 h-28 bg-gray-200 rounded"></div>
      <div className="flex-1">
        <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
        <div className="h-4 bg-gray-200 rounded w-1/2"></div>
      </div>
      <div className="h-4 bg-gray-200 rounded w-20"></div>
      <div className="h-4 bg-gray-200 rounded w-10"></div>
    </div>
  );
};

export default CartItemSkeleton;
