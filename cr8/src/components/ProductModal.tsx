import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Product, ProductVariant } from '../types'
import { useCart } from '../context/CartContext'
import { useAuth } from '../context/AuthContext'

interface ProductModalProps {
  product: Product | null
  isOpen: boolean
  onClose: () => void
}

const ProductModal = ({ product, isOpen, onClose }: ProductModalProps) => {
  const [quantity, setQuantity] = useState(1)
  const [selectedImage, setSelectedImage] = useState('')
  const [selectedVariant, setSelectedVariant] = useState<ProductVariant | null>(null)
  const [showLoginPrompt, setShowLoginPrompt] = useState(false)
  const navigate = useNavigate()
  const { addToCart } = useCart()
  const { user } = useAuth()

  useEffect(() => {
    if (product) {
      const defaultVariant = product.variants && product.variants.length > 0 ? product.variants[0] : null
      setSelectedVariant(defaultVariant)
      setSelectedImage(defaultVariant ? `https://cr8.dcism.org/${defaultVariant.image}` : `https://cr8.dcism.org/${product.image}`)
      setQuantity(1)
    }
  }, [product])

  if (!isOpen || !product) return null

  const hasVariants = product.variants && product.variants.length > 1
  const currentVariant = selectedVariant ? {
    id: selectedVariant.id,
    name: selectedVariant.name,
    image: selectedVariant.image || product.image,
    price: selectedVariant.price !== null && selectedVariant.price !== undefined ? selectedVariant.price : product.price,
    quantity: selectedVariant.quantity !== null && selectedVariant.quantity !== undefined ? selectedVariant.quantity : product.quantity
  } : {
    id: 'base',
    name: 'Default',
    image: product.image,
    price: product.price,
    quantity: product.quantity
  }

  const handleAddToCart = async () => {
    if (!user) {
      localStorage.setItem('redirectAfterLogin', JSON.stringify({ action: 'addToCart', productId: product.id, quantity }))
      setShowLoginPrompt(true)
      return
    }
    try {
      await addToCart(product.id, quantity)
      onClose()
    } catch (error) {
      console.error('Failed to add to cart:', error)
    }
  }

  const handleVariantSelect = (variant: ProductVariant) => {
    setSelectedVariant(variant)
    const variantImage = variant.image || product.image
    setSelectedImage(`https://cr8.dcism.org/${variantImage}`)
    setQuantity(1)
  }

  const increaseQuantity = () => {
    if (quantity < currentVariant.quantity) {
      setQuantity(quantity + 1)
    }
  }

  const decreaseQuantity = () => {
    if (quantity > 1) {
      setQuantity(quantity - 1)
    }
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" onClick={onClose}>
      <div className="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-y-auto relative" onClick={(e) => e.stopPropagation()}>
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-700 z-10"
        >
          <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <div className="p-6">
          <div className="flex flex-col md:flex-row gap-8">
            <div className="md:w-1/2">
              <img
                src={selectedImage}
                alt={product.product_name}
                className="w-full h-auto object-cover rounded-lg shadow-lg"
                onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
              />
            </div>

            <div className="md:w-1/2 flex flex-col">
              <h2 className="text-3xl font-lilita text-dark-purple">{product.product_name}</h2>
              <p className="text-sm text-gray-600 font-outfit mt-2">By {product.artist_name}</p>
              
              <div className="mt-4">
                <p className="text-3xl font-bold text-purple font-poetsen">₱{currentVariant.price.toFixed(2)}</p>
                {currentVariant.quantity > 0 ? (
                  <p className="text-sm text-green-600 mt-1">{currentVariant.quantity} in stock</p>
                ) : (
                  <p className="text-sm text-red-600 mt-1">Out of stock</p>
                )}
              </div>

              {hasVariants && (
                <div className="mt-6">
                  <div className="flex items-baseline gap-2 mb-2">
                    <p className="text-sm font-bold text-gray-700">Select Variant:</p>
                    <p className="text-sm font-semibold text-purple">{currentVariant.name}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {product.variants?.map((variant) => (
                      <button
                        key={variant.id}
                        onClick={() => handleVariantSelect(variant)}
                        className={`p-1 border-2 rounded-md transition ${
                          selectedVariant?.id === variant.id ? 'border-purple' : 'border-transparent hover:border-gray-300'
                        }`}
                        title={variant.name}
                      >
                        <img
                          src={`https://cr8.dcism.org/${variant.image || product.image}`}
                          alt={variant.name}
                          className="w-16 h-16 object-cover rounded-sm"
                          onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                        />
                      </button>
                    ))}
                  </div>
                </div>
              )}

              <div className="mt-6">
                <p className="text-gray-700 font-outfit">{product.description || product.product_description}</p>
              </div>

              {currentVariant.quantity > 0 && (
                <div className="mt-6">
                  <label className="block text-sm font-semibold text-gray-700 mb-2">Quantity</label>
                  <div className="flex items-center gap-3">
                    <button
                      onClick={decreaseQuantity}
                      className="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center"
                      disabled={quantity <= 1}
                    >
                      -
                    </button>
                    <span className="text-lg font-semibold w-12 text-center">{quantity}</span>
                    <button
                      onClick={increaseQuantity}
                      className="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center"
                      disabled={quantity >= currentVariant.quantity}
                    >
                      +
                    </button>
                  </div>
                </div>
              )}

              <div className="mt-8">
                {currentVariant.quantity > 0 ? (
                  <button
                    onClick={handleAddToCart}
                    className="w-full bg-purple hover:bg-dark-purple text-white py-3 px-6 rounded-full font-lilita text-lg transition"
                  >
                    Add to Cart
                  </button>
                ) : (
                  <button
                    disabled
                    className="w-full bg-gray-400 text-white py-3 px-6 rounded-full font-lilita text-lg cursor-not-allowed"
                  >
                    Out of Stock
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Reviews Section */}
          {product.reviews && product.reviews.length > 0 && (
            <div className="mt-8 border-t pt-6">
              <h3 className="text-2xl font-lilita text-dark-purple mb-4">Customer Reviews ({product.review_count || 0})</h3>
              
              {product.average_rating && Number(product.average_rating) > 0 && (
                <div className="flex items-center gap-2 mb-6">
                  <div className="flex">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <svg
                        key={star}
                        className={`h-5 w-5 ${
                          star <= Math.round(Number(product.average_rating) || 0) ? 'text-yellow-400' : 'text-gray-300'
                        }`}
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                    ))}
                  </div>
                  <span className="text-lg font-semibold text-gray-700">{Number(product.average_rating).toFixed(1)} out of 5</span>
                </div>
              )}

              <div className="space-y-4 max-h-96 overflow-y-auto">
                {product.reviews.map((review) => (
                  <div key={review.id} className="bg-gray-50 p-4 rounded-lg">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-2">
                          <span className="font-semibold text-gray-800">{review.user_name}</span>
                          <span className="text-sm text-gray-500">{review.created_at}</span>
                        </div>
                        <div className="flex mb-2">
                          {[1, 2, 3, 4, 5].map((star) => (
                            <svg
                              key={star}
                              className={`h-4 w-4 ${
                                star <= review.rating ? 'text-yellow-400' : 'text-gray-300'
                              }`}
                              fill="currentColor"
                              viewBox="0 0 20 20"
                            >
                              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                          ))}
                        </div>
                        <p className="text-gray-700">{review.comments}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Login Prompt Modal */}
      {showLoginPrompt && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70]">
          <div className="bg-white rounded-lg p-8 max-w-md w-full mx-4 shadow-xl">
            <h2 className="font-lilita text-2xl text-dark-purple mb-4 text-center">
              Log in to Add to Cart
            </h2>
            <p className="text-gray-600 mb-6 text-center">
              You need to be logged in to add items to your cart.
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowLoginPrompt(false)}
                className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-full font-lilita hover:bg-gray-50 transition-colors"
              >
                CANCEL
              </button>
              <button
                onClick={() => { setShowLoginPrompt(false); onClose(); navigate('/login') }}
                className="flex-1 px-6 py-3 bg-purple text-white rounded-full font-lilita hover:bg-dark-purple transition-colors"
              >
                LOG IN
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default ProductModal
