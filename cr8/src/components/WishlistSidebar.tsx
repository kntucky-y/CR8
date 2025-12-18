import { useCart } from '../context/CartContext'

interface WishlistSidebarProps {
  isOpen: boolean
  onClose: () => void
}

const WishlistSidebar = ({ isOpen, onClose }: WishlistSidebarProps) => {
  const { wishlist, removeFromWishlist, moveToCart } = useCart()

  const handleMoveToCart = async (productId: number) => {
    try {
      await moveToCart(productId)
    } catch (error) {
      console.error('Failed to move to cart:', error)
    }
  }

  const handleRemoveFromWishlist = async (productId: number) => {
    try {
      await removeFromWishlist(productId)
    } catch (error) {
      console.error('Failed to remove from wishlist:', error)
    }
  }

  if (!isOpen) return null

  return (
    <>
      <div className="fixed inset-0 bg-black bg-opacity-50 z-40" onClick={onClose}></div>
      <div className="fixed top-0 right-0 h-full w-80 md:w-96 bg-white shadow-lg z-50 transform transition-transform duration-300">
        <div className="flex flex-col h-full">
          <div className="p-4 border-b flex justify-between items-center bg-dark-purple text-white">
            <h2 className="text-xl font-lilita">Your Wishlist</h2>
            <button onClick={onClose} className="hover:text-light-purple transition-colors">
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div className="p-4 overflow-y-auto flex-1">
            {wishlist.length === 0 ? (
              <p className="text-center text-gray-500 font-outfit mt-8">Your wishlist is empty</p>
            ) : (
              <div className="space-y-4">
                {wishlist.map(item => (
                  <div key={item.id} className="bg-gray-50 rounded-lg p-3 flex gap-3">
                    <img
                      src={`https://cr8.dcism.org/${item.image || item.image_url}`}
                      alt={item.product_name}
                      className="w-20 h-20 object-cover rounded"
                      onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png' }}
                    />
                    <div className="flex-1">
                      <h4 className="font-outfit font-bold text-sm text-dark-purple truncate">{item.product_name}</h4>
                      <p className="font-outfit text-xs text-gray-600">{item.artist_name}</p>
                      <p className="font-outfit font-bold text-purple mt-1">₱{item.price.toFixed(2)}</p>
                      <div className="flex gap-2 mt-2">
                        <button
                          onClick={() => handleMoveToCart(item.product_id)}
                          className="text-xs bg-purple text-white px-3 py-1 rounded-full hover:bg-dark-purple transition"
                        >
                          Move to Cart
                        </button>
                        <button
                          onClick={() => handleRemoveFromWishlist(item.product_id)}
                          className="text-xs border border-red-500 text-red-500 px-3 py-1 rounded-full hover:bg-red-500 hover:text-white transition"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  )
}

export default WishlistSidebar
