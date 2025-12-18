import { useState, useEffect } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { Product } from '../types'
import { useCart } from '../context/CartContext'
import { useAuth } from '../context/AuthContext'
import Navbar from '../components/Navbar'
import ProductModal from '../components/ProductModal'
import api from '../services/api'

const Shop = () => {
  const [products, setProducts] = useState<Product[]>([])
  const [filteredProducts, setFilteredProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [selectedArtist, setSelectedArtist] = useState('')
  const [artists, setArtists] = useState<{id: number, artist_name: string}[]>([])
  const [notification, setNotification] = useState({ show: false, message: '', type: 'success' as 'success' | 'error' })
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null)
  const [showProductModal, setShowProductModal] = useState(false)
  const [showScrollTop, setShowScrollTop] = useState(false)
  const [showLoginPrompt, setShowLoginPrompt] = useState(false)
  const { addToCart, addToWishlist, wishlist } = useCart()
  const { user } = useAuth()

  useEffect(() => {
    loadProducts(true) // Show loading on initial load
    loadArtists()
    
    // Set up polling to refresh products every 3 seconds
    const intervalId = setInterval(() => {
      loadProducts(false) // Don't show loading on auto-refresh
    }, 3000) // 3 seconds
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId)
  }, [])

  useEffect(() => {
    const handleScroll = () => {
      setShowScrollTop(window.scrollY > 300)
    }
    window.addEventListener('scroll', handleScroll)
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  useEffect(() => {
    filterProducts()
  }, [searchParams, selectedArtist, products])

  const loadProducts = async (showLoading = true) => {
    if (showLoading) {
      setLoading(true)
    }
    try {
      const response = await api.get('/products?action=get')
      if (response.data.success) {
        setProducts(response.data.products)
      }
    } catch (error) {
      console.error('Failed to load products:', error)
    } finally {
      if (showLoading) {
        setLoading(false)
      }
    }
  }

  const loadArtists = async () => {
    try {
      const response = await api.get('/artists?action=get')
      if (response.data.success) {
        setArtists(response.data.artists)
      }
    } catch (error) {
      console.error('Failed to load artists:', error)
    }
  }

  const filterProducts = () => {
    let filtered = [...products]
    
    const searchQuery = searchParams.get('search')
    if (searchQuery) {
      filtered = filtered.filter(p => 
        p.product_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (p.product_description || p.description || '').toLowerCase().includes(searchQuery.toLowerCase())
      )
    }

    if (selectedArtist) {
      filtered = filtered.filter(p => p.artist_id.toString() === selectedArtist)
    }

    setFilteredProducts(filtered)
  }

  const showNotification = (message: string, type: 'success' | 'error' = 'success') => {
    setNotification({ show: true, message, type })
    setTimeout(() => setNotification({ show: false, message: '', type: 'success' }), 3000)
  }

  const handleAddToCart = async (productId: number) => {
    if (!user) {
      localStorage.setItem('redirectAfterLogin', JSON.stringify({ action: 'addToCart', productId }))
      setShowLoginPrompt(true)
      return
    }
    try {
      await addToCart(productId, 1)
      showNotification('Product added to cart!')
    } catch (error) {
      showNotification('Failed to add to cart', 'error')
    }
  }

  const handleAddToWishlist = async (productId: number) => {
    if (!user) {
      window.location.href = '/login'
      return
    }
    try {
      await addToWishlist(productId)
      showNotification('Product added to wishlist!')
    } catch (error) {
      console.error('Wishlist error:', error)
      showNotification('Failed to add to wishlist', 'error')
    }
  }



  if (loading) {
    return (
      <div className="bg-bg-color min-h-screen flex justify-center items-center">
        <div className="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-purple"></div>
      </div>
    )
  }

  return (
    <div className="bg-bg-color min-h-screen">
      <div className="sticky top-0 z-30 bg-bg-color">
        <div className="px-4 md:px-10 lg:px-20 mx-auto">
          <Navbar showSearch={true} />
        </div>
      </div>

      {/* Notification */}
      {notification.show && (
        <div className={`fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${notification.type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white font-outfit animate-fade-in`}>
          {notification.message}
        </div>
      )}

      <main className="px-4 md:px-10 lg:px-20 mx-auto py-6">
        <div className="flex gap-6">
          {/* Filters Sidebar */}
          <div className="hidden lg:block w-64 flex-shrink-0">
            <div className="bg-white bg-opacity-50 rounded-xl p-6">
              <h3 className="font-poetsen text-dark-purple text-xl mb-4">Filters</h3>
              
              <div className="mb-4">
                <label className="block font-outfit text-dark-purple font-semibold mb-2">Artist</label>
                <select
                  value={selectedArtist}
                  onChange={(e) => setSelectedArtist(e.target.value)}
                  className="w-full px-3 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-purple font-outfit"
                >
                  <option value="">All Artists</option>
                  {artists.map(artist => (
                    <option key={artist.id} value={artist.id}>{artist.artist_name}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Products Grid */}
          <div className="flex-1">
            <div className="mb-6">
              <p className="font-outfit text-dark-purple">
                Showing {filteredProducts.length} product{filteredProducts.length !== 1 ? 's' : ''}
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {filteredProducts.map(product => (
                <div key={product.id} className="bg-white bg-opacity-50 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow flex flex-col">
                  <div 
                    className="aspect-square bg-gray-200 relative overflow-hidden cursor-pointer"
                    onClick={() => {
                      setSelectedProduct(product)
                      setShowProductModal(true)
                    }}
                  >
                    <img
                      src={`https://cr8.dcism.org/${product.image}`}
                      alt={product.product_name}
                      className="w-full h-full object-cover"
                      onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                    />
                  </div>
                  <div className="p-4 flex flex-col flex-grow">
                    <h3 
                      className="font-outfit font-bold text-dark-purple text-lg mb-1 truncate cursor-pointer hover:text-purple"
                      onClick={() => {
                        setSelectedProduct(product)
                        setShowProductModal(true)
                      }}
                    >
                      {product.product_name}
                    </h3>
                    <p className="font-outfit text-sm text-gray-600 mb-2">{product.artist_name}</p>
                    <p className="font-outfit text-dark-purple font-bold text-xl mb-2">
                      ₱{product.price.toFixed(2)}
                    </p>
                    <p className="font-outfit text-sm text-gray-600 mb-4 line-clamp-2 flex-grow">
                      {product.description || product.product_description}
                    </p>
                    <div className="mt-auto">
                      {product.quantity > 0 ? (
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleAddToCart(product.id)}
                            className="flex-1 bg-purple text-white font-outfit py-2 px-4 rounded-full hover:bg-dark-purple transition text-sm"
                          >
                            Add to Cart
                          </button>
                          <button
                            onClick={() => handleAddToWishlist(product.id)}
                            className={`p-2 border-2 rounded-full transition flex-shrink-0 ${
                              wishlist.some(item => Number(item.product_id) === Number(product.id))
                                ? 'bg-purple text-white border-purple'
                                : 'border-purple hover:bg-purple hover:text-white'
                            }`}
                            title={wishlist.some(item => Number(item.product_id) === Number(product.id)) ? 'In Wishlist' : 'Add to Wishlist'}
                          >
                            <svg 
                              className="h-5 w-5" 
                              fill={wishlist.some(item => Number(item.product_id) === Number(product.id)) ? 'currentColor' : 'none'}
                              viewBox="0 0 24 24" 
                              stroke="currentColor"
                            >
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.5l1.318-1.182a4.5 4.5 0 116.364 6.364L12 21l-7.682-7.318a4.5 4.5 0 010-6.364z" />
                            </svg>
                          </button>
                        </div>
                      ) : (
                        <button disabled className="w-full bg-gray-400 text-white font-outfit py-2 px-4 rounded-full cursor-not-allowed text-sm">
                          Out of Stock
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {filteredProducts.length === 0 && (
              <div className="text-center py-12">
                <p className="font-poetsen text-dark-purple text-2xl">No products found</p>
              </div>
            )}
          </div>
        </div>
      </main>

      {/* Product Modal */}
      <ProductModal
        product={selectedProduct}
        isOpen={showProductModal}
        onClose={() => {
          setShowProductModal(false)
          setSelectedProduct(null)
        }}
      />

      {/* Scroll to Top Button */}
      {showScrollTop && (
        <button
          onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
          className="fixed bottom-8 left-8 bg-purple text-white p-4 rounded-full shadow-lg hover:bg-dark-purple transition-all duration-300 z-50 animate-fade-in"
          aria-label="Scroll to top"
        >
          <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
        </button>
      )}

      {/* Login Prompt Modal */}
      {showLoginPrompt && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]">
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
                onClick={() => navigate('/login')}
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

export default Shop
