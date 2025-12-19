import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCart } from '../context/CartContext'
import { useAuth } from '../context/AuthContext'

interface CartSidebarProps {
  isOpen: boolean
  onClose: () => void
}

const CartSidebar = ({ isOpen, onClose }: CartSidebarProps) => {
  const { cart, updateCartQuantity, removeFromCart } = useCart()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [showLoginPrompt, setShowLoginPrompt] = useState(false)

  const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)

  const handleCheckout = () => {
    if (!user) {
      setShowLoginPrompt(true)
      return
    }
    onClose()
    navigate('/checkout')
  }

  const handleLoginRedirect = () => {
    setShowLoginPrompt(false)
    onClose()
    navigate('/login')
  }

  const handleQuantityChange = async (productId: number, newQuantity: number) => {
    if (newQuantity > 0) {
      try {
        await updateCartQuantity(productId, newQuantity)
      } catch (error: any) {
        alert(error.message || 'Failed to update quantity')
      }
    }
  }

  const handleRemove = async (productId: number) => {
    await removeFromCart(productId)
  }

  if (!isOpen) return null

  return (
    <>
      <div className="fixed inset-0 bg-black bg-opacity-50 z-40" onClick={onClose}></div>
      <div className="fixed top-0 right-0 h-full w-80 md:w-96 bg-white shadow-lg z-50 transform transition-transform duration-300">
        <div className="flex flex-col h-full">
          <div className="p-4 border-b flex justify-between items-center bg-dark-purple text-white">
            <h2 className="text-xl font-lilita">Your Cart</h2>
            <button onClick={onClose} className="hover:text-light-purple transition-colors">
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          
          <div className="p-4 overflow-y-auto flex-1">
            {cart.length === 0 ? (
              <p className="text-center text-gray-500 font-outfit mt-8">Your cart is empty</p>
            ) : (
              <div className="space-y-4">
                {cart.map(item => (
                  <div key={item.id} className="bg-gray-50 rounded-lg p-3 flex gap-3">
                    <img
                      src={`https://cr8.dcism.org/${item.image || item.image_url}`}
                      alt={item.product_name}
                      className="w-20 h-20 object-cover rounded"
                      onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                    />
                    <div className="flex-1">
                      <h4 className="font-outfit font-bold text-sm text-dark-purple truncate">{item.product_name}</h4>
                      <p className="font-outfit text-xs text-gray-600">{item.artist_name}</p>
                      <p className="font-outfit font-bold text-purple mt-1">₱{item.price.toFixed(2)}</p>
                      
                      <div className="flex items-center gap-2 mt-2">
                        <button
                          onClick={() => handleQuantityChange(item.product_id, item.quantity - 1)}
                          className="w-6 h-6 rounded-full bg-purple text-white flex items-center justify-center hover:bg-dark-purple transition"
                          disabled={item.quantity <= 1}
                        >
                          -
                        </button>
                        <span className="text-sm font-outfit w-8 text-center">{item.quantity}</span>
                        <button
                          onClick={() => handleQuantityChange(item.product_id, item.quantity + 1)}
                          className="w-6 h-6 rounded-full bg-purple text-white flex items-center justify-center hover:bg-dark-purple transition"
                          disabled={item.quantity >= (item.stock || 99)}
                        >
                          +
                        </button>
                        <button
                          onClick={() => handleRemove(item.product_id)}
                          className="ml-auto text-xs border border-red-500 text-red-500 px-2 py-1 rounded-full hover:bg-red-500 hover:text-white transition"
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

          {cart.length > 0 && (
            <div className="p-4 border-t bg-gray-50">
              <div className="flex justify-between items-center mb-4">
                <span className="font-lilita text-dark-purple text-lg">Total:</span>
                <span className="font-lilita text-purple text-xl">₱{total.toFixed(2)}</span>
              </div>
              <button
                onClick={handleCheckout}
                className="w-full bg-purple text-white py-3 rounded-full font-lilita text-lg hover:bg-dark-purple transition-colors"
              >
                CHECKOUT
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Login Prompt Modal */}
      {showLoginPrompt && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]">
          <div className="bg-white rounded-lg p-8 max-w-md w-full mx-4 shadow-xl">
            <h2 className="font-lilita text-2xl text-dark-purple mb-4 text-center">
              Log in to Check out
            </h2>
            <p className="text-gray-600 mb-6 text-center">
              You need to be logged in to proceed with your purchase.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowLoginPrompt(false)}
                className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-full font-lilita hover:bg-gray-50 transition-colors"
              >
                CANCEL
              </button>
              <button
                onClick={handleLoginRedirect}
                className="flex-1 px-6 py-3 bg-purple text-white rounded-full font-lilita hover:bg-dark-purple transition-colors"
              >
                LOG IN
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

export default CartSidebar
