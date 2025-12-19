import { useState, useEffect } from 'react'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { Link } from 'react-router-dom'
import Navbar from '../components/Navbar'
import Receipt from '../components/Receipt'
import api from '../services/api'

interface Variant {
  id: number
  variant_name: string
  quantity: number
  price: number
  image: string | null
}

interface Product {
  id: number
  product_name: string
  description: string
  price: number
  quantity: number
  image: string
  variants: Variant[]
  is_active: number
  deactivation_reason?: string
}

interface Order {
  id: number
  order_no: string
  total: number
  address: string
  payment_method: string
  proof_path: string
  proof_delivery: string | null
  created_at: string
  item_count: number
  status: string
  tracking_number: string | null
  cancel_reason?: string | null
  refund_status?: 'Pending' | 'Refunded' | 'Not Required' | null
  refund_proof?: string | null
  customer_name?: string
  items?: Array<{
    product_id: number
    product_name: string
    image: string
    image_url?: string
    quantity: number
    price: string
    variant_name: string | null
    review_id?: number
    review_rating?: number
    review_comments?: string
  }> | string
  artist_earnings?: number
  review_id?: number
  review_rating?: number
  review_comments?: string
}

interface SalesData {
  total_sales: number
  total_orders: number
  products_sold: number
  recent_orders: Order[]
  top_products: Array<{
    id: number
    product_name: string
    image: string
    units_sold: number
    revenue: number
  }>
}

const UserDashboard = () => {
  const { user, logout } = useAuth()
  const { wishlist, removeFromWishlist, moveToCart } = useCart()
  const [activeTab, setActiveTab] = useState<'profile' | 'wishlist' | 'orders' | 'manage-products' | 'sales-reports'>('profile')
  const [orders, setOrders] = useState<Order[]>([])
  const [uploadingProof, setUploadingProof] = useState<number | null>(null)
  const [selectedStatus, setSelectedStatus] = useState<string>('For Review')
  const [expandedOrders, setExpandedOrders] = useState<Set<number>>(new Set())
  const [showReceipt, setShowReceipt] = useState(false)
  const [receiptData, setReceiptData] = useState<any>(null)
  const [showCancelModal, setShowCancelModal] = useState(false)
  const [cancelOrderId, setCancelOrderId] = useState<number | null>(null)
  const [cancelReason, setCancelReason] = useState('')
  const [cancellingOrder, setCancellingOrder] = useState(false)
  const [showRefundProofModal, setShowRefundProofModal] = useState(false)
  const [refundProofUrl, setRefundProofUrl] = useState('')
  const [profileData, setProfileData] = useState({
    first_name: user?.first_name || '',
    last_name: user?.last_name || '',
    username: user?.username || '',
    address: user?.address || '',
    phone: user?.phone || ''
  })

  // Manage Products state
  const [products, setProducts] = useState<Product[]>([])
  const [productsLoading, setProductsLoading] = useState(false)
  const [showProductModal, setShowProductModal] = useState(false)
  const [productFormData, setProductFormData] = useState({
    product_name: '',
    description: '',
    price: '',
    quantity: ''
  })
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [hasVariants, setHasVariants] = useState(false)
  const [variants, setVariants] = useState<Array<{name: string; quantity: string; price: string; image: File | null; existingImage?: string | null; variantId?: number}>>([{name: '', quantity: '', price: '', image: null}])
  const [submittingProduct, setSubmittingProduct] = useState(false)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)
  const [productSearch, setProductSearch] = useState('')

  // Sales Reports state
  const [salesData, setSalesData] = useState<SalesData | null>(null)
  const [salesLoading, setSalesLoading] = useState(false)
  
  // Review state
  const [showReviewModal, setShowReviewModal] = useState(false)
  const [reviewOrderId, setReviewOrderId] = useState<number | null>(null)
  const [reviewId, setReviewId] = useState<number | null>(null)
  const [reviewProductId, setReviewProductId] = useState<number | null>(null)
  const [reviewRating, setReviewRating] = useState(5)
  const [reviewComments, setReviewComments] = useState('')
  const [submittingReview, setSubmittingReview] = useState(false)

  useEffect(() => {
    if (activeTab === 'orders') {
      loadOrders()
      
      // Set up polling to refresh orders every 3 seconds
      const intervalId = setInterval(() => {
        loadOrders()
      }, 3000) // 3 seconds
      
      // Cleanup interval when tab changes or component unmounts
      return () => clearInterval(intervalId)
    } else if (activeTab === 'manage-products' && user?.role === 'artist') {
      loadProducts(true) // Show loading on initial load
      
      // Set up polling to refresh products every 3 seconds
      const intervalId = setInterval(() => {
        loadProducts(false) // Don't show loading on auto-refresh
      }, 3000) // 3 seconds
      
      // Cleanup interval when tab changes or component unmounts
      return () => clearInterval(intervalId)
    } else if (activeTab === 'sales-reports' && user?.role === 'artist') {
      loadSalesData()
    }
  }, [activeTab, user])

  const loadOrders = async () => {
    try {
      const response = await api.get('/orders.php?action=list')
      if (response.data.success) {
        setOrders(response.data.orders)
      }
    } catch (error) {
      console.error('Failed to load orders:', error)
    }
  }

  const handleProofUpload = async (orderId: number, file: File) => {
    setUploadingProof(orderId)
    try {
      const formData = new FormData()
      formData.append('proof_delivery', file)
      formData.append('order_id', orderId.toString())

      const response = await fetch('https://cr8.dcism.org/api/orders.php?action=upload-proof', {
        method: 'POST',
        credentials: 'include',
        body: formData
      })

      const data = await response.json()
      if (data.success) {
        alert('Proof of delivery uploaded successfully!')
        loadOrders()
      } else {
        alert(data.message || 'Failed to upload proof')
      }
    } catch (error) {
      console.error('Upload error:', error)
      alert('Failed to upload proof of delivery')
    } finally {
      setUploadingProof(null)
    }
  }

  const handleSubmitReview = async () => {
    if (!reviewOrderId || !reviewProductId) return
    
    setSubmittingReview(true)
    try {
      const action = reviewId ? 'update' : 'create'
      const payload: any = {
        order_id: reviewOrderId,
        product_id: reviewProductId,
        rating: reviewRating,
        comments: reviewComments
      }
      
      if (reviewId) {
        payload.review_id = reviewId
      }
      
      const response = await api.post(`/reviews.php?action=${action}`, payload)
      
      if (response.data.success) {
        alert(reviewId ? 'Review updated successfully!' : 'Review submitted successfully!')
        setShowReviewModal(false)
        setReviewOrderId(null)
        setReviewId(null)
        setReviewProductId(null)
        setReviewRating(5)
        setReviewComments('')
        loadOrders() // Reload orders to get updated review status
      } else {
        alert(response.data.message || 'Failed to submit review')
      }
    } catch (error) {
      console.error('Error submitting review:', error)
      alert('Failed to submit review')
    } finally {
      setSubmittingReview(false)
    }
  }

  const handleViewReceipt = (order: Order) => {
    setReceiptData({
      order_no: order.order_no,
      created_at: order.created_at,
      items: order.items || [],
      first_name: (order as any).first_name || user?.first_name || '',
      last_name: (order as any).last_name || user?.last_name || '',
      email: (order as any).email || user?.email || '',
      contact_number: (order as any).contact_number || user?.phone || '',
      address: order.address,
      payment_method: order.payment_method,
      total: order.total,
      tracking_number: order.tracking_number
    })
    setShowReceipt(true)
  }

  const handleCancelOrder = async () => {
    if (!cancelOrderId || !cancelReason.trim()) {
      alert('Please provide a cancellation reason')
      return
    }

    setCancellingOrder(true)
    try {
      const response = await api.post('/orders.php?action=cancel', {
        order_id: cancelOrderId,
        reason: cancelReason
      })

      if (response.data.success) {
        alert('Order cancelled successfully')
        setShowCancelModal(false)
        setCancelOrderId(null)
        setCancelReason('')
        loadOrders() // Reload orders
      } else {
        alert(response.data.message || 'Failed to cancel order')
      }
    } catch (error) {
      console.error('Error cancelling order:', error)
      alert('Failed to cancel order')
    } finally {
      setCancellingOrder(false)
    }
  }

  const handleProfileInputChange = (field: string, value: string) => {
    // Phone validation - only allow numbers and limit to 11 digits
    if (field === 'phone') {
      const numericValue = value.replace(/\D/g, '').slice(0, 11)
      setProfileData({...profileData, [field]: numericValue})
      return
    }
    
    // Apply character limits to text fields
    let processedValue = value
    if (field === 'first_name' || field === 'last_name') {
      processedValue = value.slice(0, 50)
    } else if (field === 'username') {
      processedValue = value.slice(0, 50)
    } else if (field === 'address') {
      processedValue = value.slice(0, 255)
    }
    
    setProfileData({...profileData, [field]: processedValue})
  }

  const handleProfileUpdate = async (e: React.FormEvent) => {
    e.preventDefault()
    
    // Validate phone number if provided
    if (profileData.phone && !profileData.phone.match(/^09\d{9}$/)) {
      alert('Please enter a valid 11-digit phone number starting with 09')
      return
    }
    
    // Validate required fields
    if (!profileData.first_name.trim() || !profileData.last_name.trim()) {
      alert('First name and last name are required')
      return
    }
    
    if (!profileData.username.trim()) {
      alert('Username is required')
      return
    }
    
    try {
      const response = await api.post('/user.php?action=update-profile', profileData)
      if (response.data.success) {
        alert('Profile updated successfully!')
      } else {
        alert('Failed to update profile')
      }
    } catch (error) {
      console.error('Profile update error:', error)
      alert('Failed to update profile')
    }
  }

  // Product Management Functions
  const loadProducts = async (showLoading = true) => {
    if (showLoading) {
      setProductsLoading(true)
    }
    try {
      const response = await fetch('/api/artist_products.php', {
        credentials: 'include'
      })
      const data = await response.json()
      if (data.success) {
        setProducts(data.products || [])
      }
    } catch (error) {
      console.error('Error fetching products:', error)
    } finally {
      if (showLoading) {
        setProductsLoading(false)
      }
    }
  }

  const loadSalesData = async () => {
    setSalesLoading(true)
    try {
      const response = await api.get('/artist_sales.php')
      if (response.data.success) {
        setSalesData(response.data.data)
      }
    } catch (error) {
      console.error('Error fetching sales data:', error)
    } finally {
      setSalesLoading(false)
    }
  }

  const resetProductForm = () => {
    setEditingProduct(null)
    setProductFormData({
      product_name: '',
      description: '',
      price: '',
      quantity: ''
    })
    setImageFile(null)
    setHasVariants(false)
    setVariants([{name: '', quantity: '', price: '', image: null}])
  }

  const handleProductSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmittingProduct(true)

    // Validate quantity
    const quantity = parseInt(productFormData.quantity)
    if (isNaN(quantity) || quantity < 0) {
      alert('Quantity must be a valid number and cannot be negative')
      setSubmittingProduct(false)
      return
    }
    if (!editingProduct && quantity === 0) {
      alert('Quantity must be greater than 0 when adding a new product')
      setSubmittingProduct(false)
      return
    }

    // Validate variant quantities if has variants
    if (hasVariants) {
      const validVariants = variants.filter(v => v.name.trim() !== '')
      for (let i = 0; i < validVariants.length; i++) {
        const variantQty = parseInt(validVariants[i].quantity)
        if (isNaN(variantQty) || variantQty < 0) {
          alert(`Variant ${i + 1} quantity must be a valid number and cannot be negative`)
          setSubmittingProduct(false)
          return
        }
        if (!editingProduct && variantQty === 0) {
          alert(`Variant ${i + 1} quantity must be greater than 0 when adding a new product`)
          setSubmittingProduct(false)
          return
        }
      }
    }

    const formDataObj = new FormData()
    formDataObj.append('product_name', productFormData.product_name)
    formDataObj.append('description', productFormData.description)
    formDataObj.append('price', productFormData.price)
    formDataObj.append('quantity', productFormData.quantity)
    formDataObj.append('has_variants', hasVariants ? '1' : '0')
    
    if (editingProduct) {
      formDataObj.append('product_id', editingProduct.id.toString())
    }
    
    if (imageFile) {
      formDataObj.append('image', imageFile)
    }
    
    if (hasVariants) {
      const validVariants = variants.filter(v => v.name.trim() !== '')
      formDataObj.append('variants', JSON.stringify(validVariants.map(v => ({
        name: v.name,
        quantity: v.quantity,
        price: v.price,
        variantId: v.variantId,
        existingImage: v.existingImage
      }))))
      
      validVariants.forEach((variant, index) => {
        if (variant.image) {
          formDataObj.append(`variant_image_${index}`, variant.image)
        }
      })
    }

    try {
      const endpoint = editingProduct ? '/api/update_product.php' : '/api/add_product.php'
      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        body: formDataObj
      })
      const data = await response.json()
      
      if (data.success) {
        alert(editingProduct ? 'Product updated successfully!' : 'Product added successfully!')
        setShowProductModal(false)
        resetProductForm()
        loadProducts()
      } else {
        alert(data.message || 'Error saving product')
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Error saving product')
    } finally {
      setSubmittingProduct(false)
    }
  }

  const addVariant = () => {
    setVariants([...variants, {name: '', quantity: '', price: '', image: null}])
  }

  const removeVariant = (index: number) => {
    const newVariants = variants.filter((_, i) => i !== index)
    if (newVariants.length === 0) {
      setHasVariants(false)
      setVariants([{name: '', quantity: '', price: '', image: null}])
    } else {
      setVariants(newVariants)
    }
  }

  const updateVariantField = (index: number, field: 'name' | 'quantity' | 'price', value: string) => {
    const newVariants = [...variants]
    newVariants[index][field] = value
    setVariants(newVariants)
  }

  const updateVariantImage = (index: number, file: File | null) => {
    const newVariants = [...variants]
    newVariants[index].image = file
    setVariants(newVariants)
  }

  const handleDeleteProduct = async (productId: number) => {
    if (!confirm('Are you sure you want to delete this product?')) return

    try {
      const formData = new FormData()
      formData.append('product_id', productId.toString())

      const response = await fetch('/api/delete_product.php', {
        method: 'POST',
        credentials: 'include',
        body: formData
      })
      const data = await response.json()

      if (data.success) {
        alert('Product deleted successfully')
        loadProducts()
      } else {
        alert(data.message || 'Error deleting product')
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Error deleting product')
    }
  }

  const handleEditProduct = (product: Product) => {
    setEditingProduct(product)
    setProductFormData({
      product_name: product.product_name,
      description: product.description,
      price: product.price.toString(),
      quantity: product.quantity.toString()
    })
    
    setImageFile(null)
    
    if (product.variants && product.variants.length > 0) {
      setHasVariants(true)
      setVariants(product.variants.map(v => ({
        name: v.variant_name,
        quantity: v.quantity.toString(),
        price: v.price.toString(),
        image: null,
        existingImage: v.image,
        variantId: v.id
      })))
    } else {
      setHasVariants(false)
      setVariants([{name: '', quantity: '', price: '', image: null}])
    }
    
    setShowProductModal(true)
  }

  return (
    <div className="bg-bg-color h-screen flex flex-col overflow-hidden relative">
      {/* Background decorations */}
      <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img
          src="/img/blubber.png"
          alt="Decoration"
          className="absolute top-10 left-0 w-1/6 opacity-40 animate-float hidden lg:block"
        />
        <img
          src="/img/bubber.png"
          alt="Decoration"
          className="absolute top-[400px] right-0 w-1/6 opacity-30 animate-float hidden lg:block"
        />
      </div>

      <div className="relative z-10 h-full flex flex-col overflow-hidden">
        <div className="px-4 md:px-10 lg:px-20 mx-auto w-full flex-shrink-0">
          <Navbar showSearch={false} hideWishlist={true} />
        </div>

      <main className="px-4 md:px-10 lg:px-20 mx-auto flex-1 overflow-hidden w-full flex flex-col">
        <div className="max-w-6xl mx-auto flex-1 flex flex-col overflow-hidden w-full">
          <h1 className="font-poetsen text-darkest-purple text-4xl mb-8 flex-shrink-0">My Dashboard</h1>

          <div className="flex flex-col md:flex-row gap-8 flex-1 overflow-hidden min-h-0">
            {/* Sidebar */}
            <aside className="w-full md:w-64 bg-white bg-opacity-50 rounded-xl p-6 flex-shrink-0 h-fit">
              <nav className="space-y-2">
                <button
                  onClick={() => setActiveTab('profile')}
                  className={`w-full text-left px-4 py-2 rounded-lg font-outfit ${
                    activeTab === 'profile' ? 'bg-purple text-white' : 'text-dark-purple hover:bg-purple hover:bg-opacity-20'
                  }`}
                >
                  Profile
                </button>
                <button
                  onClick={() => setActiveTab('wishlist')}
                  className={`w-full text-left px-4 py-2 rounded-lg font-outfit ${
                    activeTab === 'wishlist' ? 'bg-purple text-white' : 'text-dark-purple hover:bg-purple hover:bg-opacity-20'
                  }`}
                >
                  Wishlist
                </button>
                <button
                  onClick={() => setActiveTab('orders')}
                  className={`w-full text-left px-4 py-2 rounded-lg font-outfit ${
                    activeTab === 'orders' ? 'bg-purple text-white' : 'text-dark-purple hover:bg-purple hover:bg-opacity-20'
                  }`}
                >
                  Orders
                </button>
                {user?.role === 'artist' && (
                  <>
                    <button
                      onClick={() => setActiveTab('manage-products')}
                      className={`w-full text-left px-4 py-2 rounded-lg font-outfit ${
                        activeTab === 'manage-products' ? 'bg-purple text-white' : 'text-dark-purple hover:bg-purple hover:bg-opacity-20'
                      }`}
                    >
                      Manage Products
                    </button>
                    <button
                      onClick={() => setActiveTab('sales-reports')}
                      className={`w-full text-left px-4 py-2 rounded-lg font-outfit ${
                        activeTab === 'sales-reports' ? 'bg-purple text-white' : 'text-dark-purple hover:bg-purple hover:bg-opacity-20'
                      }`}
                    >
                      Sales Reports
                    </button>
                  </>
                )}
                {user?.role !== 'artist' && (
                  <Link
                    to="/artist-application"
                    className="block w-full text-left px-4 py-2 rounded-lg font-outfit text-dark-purple hover:bg-purple hover:bg-opacity-20"
                  >
                    Become an Artist
                  </Link>
                )}
                <button
                  onClick={logout}
                  className="w-full text-left px-4 py-2 rounded-lg font-outfit text-red-600 hover:bg-red-100"
                >
                  Logout
                </button>
              </nav>
            </aside>

            {/* Main Content */}
            <div className="flex-1 bg-white bg-opacity-50 rounded-xl p-8 overflow-auto min-h-0 h-fit max-h-full">
              {activeTab === 'profile' && (
                <div>
                  <h2 className="font-lilita text-dark-purple text-2xl mb-6">Profile Information</h2>
                  <form onSubmit={handleProfileUpdate} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block font-outfit text-dark-purple font-semibold mb-2">First Name *</label>
                        <input
                          type="text"
                          value={profileData.first_name}
                          onChange={(e) => handleProfileInputChange('first_name', e.target.value)}
                          required
                          maxLength={50}
                          className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg"
                        />
                      </div>
                      <div>
                        <label className="block font-outfit text-dark-purple font-semibold mb-2">Last Name *</label>
                        <input
                          type="text"
                          value={profileData.last_name}
                          onChange={(e) => handleProfileInputChange('last_name', e.target.value)}
                          required
                          maxLength={50}
                          className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg"
                        />
                      </div>
                    </div>
                    <div>
                      <label className="block font-outfit text-dark-purple font-semibold mb-2">Username *</label>
                      <input
                        type="text"
                        value={profileData.username}
                        onChange={(e) => handleProfileInputChange('username', e.target.value)}
                        required
                        maxLength={50}
                        className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block font-outfit text-dark-purple font-semibold mb-2">Address</label>
                      <input
                        type="text"
                        value={profileData.address}
                        onChange={(e) => handleProfileInputChange('address', e.target.value)}
                        maxLength={255}
                        className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block font-outfit text-dark-purple font-semibold mb-2">Phone Number</label>
                      <input
                        type="text"
                        value={profileData.phone}
                        onChange={(e) => handleProfileInputChange('phone', e.target.value)}
                        placeholder="09XXXXXXXXX"
                        maxLength={11}
                        pattern="09\d{9}"
                        title="Please enter a valid 11-digit phone number starting with 09"
                        className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg"
                      />
                    </div>
                    <button type="submit" className="gradient-btn text-white font-outfit font-bold py-2 px-6 rounded-full">
                      Update Profile
                    </button>
                  </form>
                </div>
              )}

              {activeTab === 'wishlist' && (
                <div>
                  <h2 className="font-lilita text-dark-purple text-2xl mb-6">My Wishlist</h2>
                  {wishlist.length === 0 ? (
                    <p className="text-center text-gray-600">Your wishlist is empty</p>
                  ) : (
                    <div className="grid gap-4">
                      {wishlist.map(item => (
                        <div key={item.id} className="flex gap-4 bg-white p-4 rounded-lg">
                          <img 
                            src={`https://cr8.dcism.org/${item.image || item.image_url}`} 
                            alt={item.product_name} 
                            className="w-24 h-24 object-cover rounded" 
                            onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                          />
                          <div className="flex-1">
                            <h3 className="font-outfit font-bold">{item.product_name}</h3>
                            <p className="text-sm text-gray-600">{item.artist_name}</p>
                            <p className="font-bold">₱{item.price.toFixed(2)}</p>
                          </div>
                          <div className="flex flex-col gap-2">
                            <button
                              onClick={() => moveToCart(item.product_id)}
                              className="bg-purple text-white px-4 py-2 rounded-full text-sm"
                            >
                              Move to Cart
                            </button>
                            <button
                              onClick={() => removeFromWishlist(item.product_id)}
                              className="border border-red-500 text-red-500 px-4 py-2 rounded-full text-sm"
                            >
                              Remove
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {activeTab === 'orders' && (
                <div>
                  <h2 className="font-lilita text-dark-purple text-2xl mb-6">Order History</h2>
                  {orders.length === 0 ? (
                    <p className="text-center text-gray-600">No orders yet</p>
                  ) : (
                    <div>
                      <div className="flex flex-wrap gap-2 mb-6">
                        {['For Review', 'Processing', 'Out for Delivery', 'Completed', 'Cancelled'].map(status => {
                          const count = orders.filter(o => o.status === status).length;
                          const statusColors = {
                            'For Review': selectedStatus === status ? 'bg-yellow-200 text-yellow-800 border-yellow-400' : 'bg-yellow-50 text-yellow-700 border-yellow-200',
                            'Processing': selectedStatus === status ? 'bg-orange-200 text-orange-800 border-orange-400' : 'bg-orange-50 text-orange-700 border-orange-200',
                            'Out for Delivery': selectedStatus === status ? 'bg-indigo-200 text-indigo-800 border-indigo-400' : 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'Completed': selectedStatus === status ? 'bg-green-200 text-green-800 border-green-400' : 'bg-green-50 text-green-700 border-green-200',
                            'Cancelled': selectedStatus === status ? 'bg-red-200 text-red-800 border-red-400' : 'bg-red-50 text-red-700 border-red-200'
                          };
                          
                          return (
                            <button
                              key={status}
                              onClick={() => setSelectedStatus(status)}
                              className={`px-4 py-2 rounded-lg border-2 font-semibold text-sm transition-all ${statusColors[status as keyof typeof statusColors]} ${selectedStatus === status ? 'shadow-md' : 'hover:shadow'}`}
                            >
                              {status} ({count})
                            </button>
                          );
                        })}
                      </div>
                      
                      <div className="space-y-4">
                        {orders.filter(o => o.status === selectedStatus).map(order => (
                                <div key={order.id} className="bg-white p-6 rounded-lg border-2 border-dark-purple">
                                  <div className="flex justify-between items-start mb-4">
                                    <div>
                                      <h4 className="font-lilita text-dark-purple text-lg">Order #{order.order_no}</h4>
                                      <p className="text-sm text-gray-600">{new Date(order.created_at).toLocaleDateString()}</p>
                                      <p className="text-sm text-gray-600">{order.item_count} item(s)</p>
                                      <span className={`inline-block mt-2 text-xs font-semibold px-3 py-1 rounded-full ${
                                        order.status === 'For Review' ? 'bg-yellow-200 text-yellow-700' :
                                        order.status === 'Processing' ? 'bg-orange-200 text-orange-700' :
                                        order.status === 'Out for Delivery' ? 'bg-indigo-200 text-indigo-700' :
                                        order.status === 'Completed' ? 'bg-green-200 text-green-700' :
                                        'bg-red-200 text-red-700'
                                      }`}>
                                        {order.status}
                                      </span>
                                    </div>
                                    <div className="text-right">
                                      <p className="font-bold text-lg">₱{parseFloat(order.total.toString()).toFixed(2)}</p>
                                      <span className="text-xs px-3 py-1 rounded-full bg-purple bg-opacity-20 text-purple">
                                        {order.payment_method.toUpperCase()}
                                      </span>
                                    </div>
                                  </div>

                                  {/* Product Items with Review Buttons */}
                                  {order.items && Array.isArray(order.items) && order.items.length > 0 && (
                                    <div className="border-t pt-4 mt-4">
                                      <p className="text-sm text-gray-700 mb-3"><strong>Items:</strong></p>
                                      <div className="space-y-3">
                                        {order.items
                                          .slice(0, expandedOrders.has(order.id) ? order.items.length : 3)
                                          .map((item, idx) => (
                                          <div key={idx} className="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
                                            <img 
                                              src={item.image_url || `https://cr8.dcism.org/${item.image}`}
                                              alt={item.product_name}
                                              className="w-16 h-16 object-cover rounded"
                                              onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                                            />
                                            <div className="flex-1 text-sm">
                                              <p className="font-semibold text-gray-800">{item.product_name}</p>
                                              {item.variant_name && <p className="text-gray-500 text-xs">Variant: {item.variant_name}</p>}
                                              <p className="text-gray-600 text-xs">Qty: {item.quantity} × ₱{parseFloat(item.price).toFixed(2)}</p>
                                              {item.review_id && (
                                                <div className="mt-1 flex items-center gap-1 text-xs">
                                                  <span className="text-yellow-500">{'★'.repeat(item.review_rating || 0)}{'☆'.repeat(5 - (item.review_rating || 0))}</span>
                                                  <span className="text-gray-600">({item.review_rating}/5)</span>
                                                </div>
                                              )}
                                            </div>
                                            {order.status === 'Completed' && (
                                              <button
                                                onClick={() => {
                                                  setReviewOrderId(order.id)
                                                  setReviewProductId(item.product_id)
                                                  
                                                  if (item.review_id) {
                                                    // Editing existing review
                                                    setReviewId(item.review_id)
                                                    setReviewRating(item.review_rating || 5)
                                                    setReviewComments(item.review_comments || '')
                                                  } else {
                                                    // New review
                                                    setReviewId(null)
                                                    setReviewRating(5)
                                                    setReviewComments('')
                                                  }
                                                  setShowReviewModal(true)
                                                }}
                                                className={`px-3 py-1.5 text-xs text-white rounded font-semibold transition whitespace-nowrap ${
                                                  item.review_id 
                                                    ? 'bg-blue-600 hover:bg-blue-700' 
                                                    : 'bg-green-600 hover:bg-green-700'
                                                }`}
                                              >
                                                {item.review_id ? 'Edit' : 'Review'}
                                              </button>
                                            )}
                                          </div>
                                        ))}
                                        {order.items.length > 3 && (
                                          <button
                                            onClick={() => {
                                              setExpandedOrders(prev => {
                                                const newSet = new Set(prev)
                                                if (newSet.has(order.id)) {
                                                  newSet.delete(order.id)
                                                } else {
                                                  newSet.add(order.id)
                                                }
                                                return newSet
                                              })
                                            }}
                                            className="w-full text-xs text-purple hover:text-dark-purple font-semibold py-2 transition"
                                          >
                                            {expandedOrders.has(order.id) 
                                              ? '▲ Show Less'
                                              : `▼ Show ${order.items.length - 3} More Item${order.items.length - 3 !== 1 ? 's' : ''}`
                                            }
                                          </button>
                                        )}
                                      </div>
                                    </div>
                                  )}
                                  
                                  <div className="border-t pt-4 mt-4">
                                    <p className="text-sm text-gray-700 mb-2"><strong>Shipping Address:</strong></p>
                                    <p className="text-sm text-gray-600 whitespace-pre-wrap">{order.address}</p>
                                  </div>

                                  {order.tracking_number && (
                                    <div className="border-t pt-4 mt-4">
                                      <p className="text-sm text-gray-700 mb-1"><strong>Tracking Number:</strong></p>
                                      <p className="text-sm font-mono text-purple bg-purple bg-opacity-10 px-3 py-2 rounded inline-block">{order.tracking_number}</p>
                                    </div>
                                  )}

                                  {order.status === 'Cancelled' && order.cancel_reason && (
                                    <div className="border-t pt-4 mt-4">
                                      <p className="text-sm text-gray-700 mb-1"><strong>Cancellation Reason:</strong></p>
                                      <p className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded mb-3">{order.cancel_reason}</p>
                                      
                                      <div className="mt-3">
                                        <p className="text-sm text-gray-700 mb-1"><strong>Refund Status:</strong></p>
                                        <div className="flex items-center gap-3">
                                          <p className={`text-sm px-3 py-2 rounded inline-block ${
                                            order.refund_status === 'Refunded' 
                                              ? 'text-green-700 bg-green-50' 
                                              : order.refund_status === 'Pending'
                                              ? 'text-yellow-700 bg-yellow-50'
                                              : 'text-gray-600 bg-gray-50'
                                          }`}>
                                            {order.refund_status || 'Not Required'}
                                          </p>
                                          {order.refund_status === 'Refunded' && order.refund_proof && (
                                            <button
                                              onClick={() => {
                                                setRefundProofUrl(`https://cr8admin.dcism.org/${order.refund_proof}`)
                                                setShowRefundProofModal(true)
                                              }}
                                              className="text-sm px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-semibold"
                                            >
                                              View Refund Proof
                                            </button>
                                          )}
                                        </div>
                                      </div>
                                    </div>
                                  )}

                                  {order.status === 'Out for Delivery' && (
                                    !order.proof_delivery ? (
                                      <div className="border-t pt-4 mt-4">
                                        <label className="block text-sm font-bold text-dark-purple mb-2">
                                          Upload Proof of Delivery
                                        </label>
                                        <p className="text-xs text-gray-600 mb-2">Please upload a photo of the delivered package or delivery receipt</p>
                                        <input
                                          type="file"
                                          accept="image/*"
                                          onChange={(e) => {
                                            if (e.target.files && e.target.files[0]) {
                                              handleProofUpload(order.id, e.target.files[0])
                                            }
                                          }}
                                          disabled={uploadingProof === order.id}
                                          className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple file:bg-opacity-10 file:text-purple hover:file:bg-opacity-20 cursor-pointer disabled:opacity-50"
                                        />
                                        {uploadingProof === order.id && (
                                          <p className="text-sm text-purple mt-2">Uploading...</p>
                                        )}
                                      </div>
                                    ) : (
                                      <div className="border-t pt-4 mt-4">
                                        <p className="text-sm font-bold text-green-600 mb-2">✓ Proof of Delivery Submitted</p>
                                        <img 
                                          src={`https://cr8.dcism.org/${order.proof_delivery}`}
                                          alt="Proof of delivery"
                                          className="max-w-xs h-auto rounded border"
                                        />
                                      </div>
                                    )
                                  )}

                                  {/* Action Buttons */}
                                  <div className="border-t pt-4 mt-4 space-y-2">
                                    <button
                                      onClick={() => handleViewReceipt(order)}
                                      className="w-full px-4 py-2 bg-purple text-white rounded-lg font-semibold hover:bg-dark-purple transition"
                                    >
                                      View Receipt
                                    </button>
                                    {order.status === 'For Review' && (
                                      <button
                                        onClick={() => {
                                          setCancelOrderId(order.id)
                                          setShowCancelModal(true)
                                        }}
                                        className="w-full px-4 py-2 bg-red-500 text-white rounded-lg font-semibold hover:bg-red-600 transition"
                                      >
                                        Cancel Order
                                      </button>
                                    )}
                                  </div>
                                </div>
                              ))}
                      </div>
                      
                      {orders.filter(o => o.status === selectedStatus).length === 0 && (
                        <p className="text-center text-gray-500 py-8">No orders with status "{selectedStatus}"</p>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* Manage Products Tab */}
              {activeTab === 'manage-products' && (
                <div className="flex flex-col h-full overflow-hidden min-h-0">
                  <div className="flex justify-between items-center mb-4 flex-shrink-0">
                    <h2 className="font-lilita text-dark-purple text-2xl">Your Products</h2>
                    <button 
                      onClick={() => {
                        resetProductForm()
                        setShowProductModal(true)
                      }}
                      className="px-4 py-2 bg-purple text-white rounded-lg hover:bg-opacity-90 font-outfit"
                    >
                      Add New Product
                    </button>
                  </div>

                  {/* Search Bar */}
                  <div className="mb-4 flex-shrink-0">
                    <input
                      type="text"
                      placeholder="Search products..."
                      value={productSearch}
                      onChange={(e) => setProductSearch(e.target.value)}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                    />
                  </div>
                  
                  {productsLoading ? (
                    <p className="text-gray-500">Loading products...</p>
                  ) : products.length === 0 ? (
                    <p className="text-gray-500">No products yet. Start by adding your first product!</p>
                  ) : (
                    <div className="flex-1 overflow-auto border border-gray-200 rounded-lg min-h-0">
                      <table className="w-full">
                        <thead className="sticky top-0 bg-gray-50 z-10">
                          <tr className="border-b">
                            <th className="text-left py-3 px-4 font-outfit">Image</th>
                            <th className="text-left py-3 px-4 font-outfit">Product Name</th>
                            <th className="text-left py-3 px-4 font-outfit">Price</th>
                            <th className="text-left py-3 px-4 font-outfit">Stock</th>
                            <th className="text-left py-3 px-4 font-outfit">Status</th>
                            <th className="text-left py-3 px-4 font-outfit">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          {products
                            .filter(product => 
                              product.product_name.toLowerCase().includes(productSearch.toLowerCase()) ||
                              product.description.toLowerCase().includes(productSearch.toLowerCase())
                            )
                            .map(product => (
                            <tr key={product.id} className="border-b hover:bg-white hover:bg-opacity-50">
                              <td className="py-3 px-4">
                                <img 
                                  src={`https://cr8.dcism.org/${product.image}`} 
                                  alt={product.product_name} 
                                  className="w-16 h-16 object-cover rounded"
                                  onError={(e) => { 
                                    (e.target as HTMLImageElement).src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="64" height="64"%3E%3Crect width="64" height="64" fill="%23ddd"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="monospace" font-size="14" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E'
                                  }}
                                />
                              </td>
                              <td className="py-3 px-4 font-outfit">{product.product_name}</td>
                              <td className="py-3 px-4 font-outfit">₱{parseFloat(product.price.toString()).toFixed(2)}</td>
                              <td className="py-3 px-4 font-outfit">{product.quantity}</td>
                              <td className="py-3 px-4">
                                {product.is_active === 0 ? (
                                  <div className="flex flex-col gap-1">
                                    <span className="inline-block px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded">
                                      Deactivated by Admin
                                    </span>
                                    {product.deactivation_reason && (
                                      <span className="text-xs text-gray-600 italic">
                                        Reason: {product.deactivation_reason}
                                      </span>
                                    )}
                                  </div>
                                ) : (
                                  <span className="inline-block px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded">
                                    Active
                                  </span>
                                )}
                              </td>
                              <td className="py-3 px-4">
                                <button onClick={() => handleEditProduct(product)} className="text-blue-600 hover:underline mr-3 font-outfit">Edit</button>
                                <button onClick={() => handleDeleteProduct(product.id)} className="text-red-600 hover:underline font-outfit">Delete</button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                      {products.filter(product => 
                        product.product_name.toLowerCase().includes(productSearch.toLowerCase()) ||
                        product.description.toLowerCase().includes(productSearch.toLowerCase())
                      ).length === 0 && (
                        <p className="text-center text-gray-500 py-8 font-outfit">No products found matching "{productSearch}"</p>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* Sales Reports Tab */}
              {activeTab === 'sales-reports' && (
                <div className="space-y-6">
                  <h2 className="font-lilita text-dark-purple text-2xl mb-6">Sales Reports</h2>
                  
                  {salesLoading ? (
                    <p className="text-gray-500">Loading sales data...</p>
                  ) : salesData ? (
                    <>
                      {/* Summary Cards */}
                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-white bg-opacity-80 rounded-xl p-6 shadow-sm">
                          <h3 className="text-sm font-outfit text-gray-600 mb-2">Total Sales</h3>
                          <p className="text-3xl font-bold text-purple">₱{salesData.total_sales.toFixed(2)}</p>
                        </div>
                        
                        <div className="bg-white bg-opacity-80 rounded-xl p-6 shadow-sm">
                          <h3 className="text-sm font-outfit text-gray-600 mb-2">Total Orders</h3>
                          <p className="text-3xl font-bold text-purple">{salesData.total_orders}</p>
                        </div>
                        
                        <div className="bg-white bg-opacity-80 rounded-xl p-6 shadow-sm">
                          <h3 className="text-sm font-outfit text-gray-600 mb-2">Products Sold</h3>
                          <p className="text-3xl font-bold text-purple">{salesData.products_sold}</p>
                        </div>
                      </div>

                      {/* Top Products */}
                      {salesData.top_products && salesData.top_products.length > 0 && (
                        <div className="bg-white bg-opacity-80 rounded-xl p-6 shadow-sm">
                          <h3 className="font-lilita text-dark-purple text-xl mb-4">Top Selling Products</h3>
                          <div className="space-y-3">
                            {salesData.top_products.map((product) => (
                              <div key={product.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div className="flex items-center gap-3">
                                  <img
                                    src={`https://cr8.dcism.org/${product.image}`}
                                    alt={product.product_name}
                                    className="w-12 h-12 object-cover rounded"
                                    onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                                  />
                                  <div>
                                    <p className="font-semibold text-gray-800">{product.product_name}</p>
                                    <p className="text-sm text-gray-500">{product.units_sold} units sold</p>
                                  </div>
                                </div>
                                <p className="font-bold text-purple">₱{Number(product.revenue).toFixed(2)}</p>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Recent Orders */}
                      {salesData.recent_orders && salesData.recent_orders.length > 0 && (
                        <div className="bg-white bg-opacity-80 rounded-xl p-6 shadow-sm">
                          <h3 className="font-lilita text-dark-purple text-xl mb-4">Recent Orders</h3>
                          <div className="overflow-x-auto">
                            <table className="w-full">
                              <thead>
                                <tr className="border-b">
                                  <th className="text-left py-3 px-2 font-outfit text-gray-700">Order #</th>
                                  <th className="text-left py-3 px-2 font-outfit text-gray-700">Customer</th>
                                  <th className="text-left py-3 px-2 font-outfit text-gray-700">Items</th>
                                  <th className="text-left py-3 px-2 font-outfit text-gray-700">Date</th>
                                  <th className="text-right py-3 px-2 font-outfit text-gray-700">Earnings</th>
                                </tr>
                              </thead>
                              <tbody>
                                {salesData.recent_orders.map((order) => (
                                  <tr key={order.id} className="border-b hover:bg-gray-50">
                                    <td className="py-3 px-2 font-mono text-sm">{order.order_no}</td>
                                    <td className="py-3 px-2">{order.customer_name}</td>
                                    <td className="py-3 px-2 text-sm text-gray-600">{typeof order.items === 'string' ? order.items : `${order.item_count} item(s)`}</td>
                                    <td className="py-3 px-2 text-sm">{new Date(order.created_at).toLocaleDateString()}</td>
                                    <td className="py-3 px-2 text-right font-semibold text-purple">
                                      ₱{Number(order.artist_earnings).toFixed(2)}
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      )}

                      {(!salesData.recent_orders || salesData.recent_orders.length === 0) && (
                        <div className="bg-white bg-opacity-80 rounded-xl p-8 text-center">
                          <p className="text-gray-500">No sales data yet. Start selling to see your reports!</p>
                        </div>
                      )}
                    </>
                  ) : (
                    <p className="text-gray-500">Failed to load sales data.</p>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

      {/* Product Modal */}
      {showProductModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h2 className="text-2xl font-outfit font-bold mb-6 text-dark-purple">{editingProduct ? 'Edit Product' : 'Add New Product'}</h2>
            <form onSubmit={handleProductSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1 font-outfit">Product Name</label>
                <input
                  type="text"
                  required
                  value={productFormData.product_name}
                  onChange={(e) => setProductFormData({...productFormData, product_name: e.target.value})}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1 font-outfit">Description</label>
                <textarea
                  required
                  rows={4}
                  value={productFormData.description}
                  onChange={(e) => setProductFormData({...productFormData, description: e.target.value})}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1 font-outfit">Price (₱)</label>
                  <input
                    type="number"
                    step="0.01"
                    required
                    value={productFormData.price}
                    onChange={(e) => setProductFormData({...productFormData, price: e.target.value})}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1 font-outfit">Stock Quantity</label>
                  <input
                    type="number"
                    required
                    min={editingProduct ? "0" : "1"}
                    value={productFormData.quantity}
                    onChange={(e) => setProductFormData({...productFormData, quantity: e.target.value})}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                  />
                  <p className="text-xs text-gray-500 mt-1 font-outfit">{editingProduct ? 'Set to 0 for out of stock' : 'Must be at least 1'}</p>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1 font-outfit">Product Image</label>
                {editingProduct && editingProduct.image && (
                  <div className="mb-2">
                    <p className="text-sm text-gray-600 mb-1 font-outfit">Current image:</p>
                    <img src={`https://cr8.dcism.org/${editingProduct.image}`} alt="Current" className="h-20 w-20 object-cover rounded" />
                  </div>
                )}
                <input
                  type="file"
                  accept="image/*"
                  required={!editingProduct}
                  onChange={(e) => setImageFile(e.target.files?.[0] || null)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 font-outfit"
                />
                {editingProduct && <p className="text-xs text-gray-500 mt-1 font-outfit">Leave empty to keep current image</p>}
              </div>
              
              <div className="border-t pt-4">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={hasVariants}
                    onChange={(e) => {
                      setHasVariants(e.target.checked)
                      if (!e.target.checked) setVariants([{name: '', quantity: '', price: '', image: null}])
                    }}
                    className="w-4 h-4 text-purple focus:ring-purple border-gray-300 rounded"
                  />
                  <span className="text-sm font-medium text-gray-700 font-outfit">This product has variants</span>
                </label>
              </div>

              {hasVariants && (
                <div className="space-y-4">
                  <label className="block text-sm font-medium text-gray-700 font-outfit">Variants</label>
                  {variants.map((variant, index) => (
                    <div key={index} className="border border-gray-200 rounded-lg p-4 space-y-3">
                      <div className="flex justify-between items-center">
                        <span className="font-medium text-gray-700 font-outfit">Variant {index + 1}</span>
                        <button
                          type="button"
                          onClick={() => removeVariant(index)}
                          className="text-sm text-red-600 hover:underline font-outfit"
                        >
                          Remove
                        </button>
                      </div>
                      <input
                        type="text"
                        value={variant.name}
                        onChange={(e) => updateVariantField(index, 'name', e.target.value)}
                        placeholder="Variant name (e.g., Red, Large)"
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                      />
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs text-gray-600 mb-1 font-outfit">Price (₱)</label>
                          <input
                            type="number"
                            step="0.01"
                            value={variant.price}
                            onChange={(e) => updateVariantField(index, 'price', e.target.value)}
                            placeholder="Price"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-gray-600 mb-1 font-outfit">Quantity</label>
                          <input
                            type="number"
                            min={editingProduct ? "0" : "1"}
                            value={variant.quantity}
                            onChange={(e) => updateVariantField(index, 'quantity', e.target.value)}
                            placeholder="Stock"
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
                          />
                        </div>
                      </div>
                      <div>
                        <label className="block text-xs text-gray-600 mb-1 font-outfit">Image</label>
                        {variant.existingImage && !variant.image && (
                          <div className="mb-2 flex items-center gap-2">
                            <img src={`https://cr8.dcism.org/${variant.existingImage}`} alt="Current variant" className="h-16 w-16 object-cover rounded border border-gray-300" />
                            <span className="text-xs text-gray-600 font-outfit">Current image</span>
                          </div>
                        )}
                        {variant.image && (
                          <div className="mb-2 flex items-center gap-2">
                            <img src={URL.createObjectURL(variant.image)} alt="New variant" className="h-16 w-16 object-cover rounded border border-green-300" />
                            <span className="text-xs text-green-600 font-outfit">New image (will replace current)</span>
                          </div>
                        )}
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => updateVariantImage(index, e.target.files?.[0] || null)}
                          className="w-full border border-gray-300 rounded-lg px-3 py-1 text-sm font-outfit"
                        />
                        {editingProduct && variant.existingImage && <p className="text-xs text-gray-500 font-outfit mt-1">Leave empty to keep current image</p>}
                      </div>
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={addVariant}
                    className="text-sm text-purple hover:underline font-outfit"
                  >
                    + Add Another Variant
                  </button>
                </div>
              )}
              
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={() => {
                    setShowProductModal(false)
                    resetProductForm()
                  }}
                  className="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-outfit"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submittingProduct}
                  className="px-6 py-2 bg-purple text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50 font-outfit"
                >
                  {submittingProduct ? (editingProduct ? 'Updating...' : 'Adding...') : (editingProduct ? 'Update Product' : 'Add Product')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Receipt Modal */}
      {showReceipt && receiptData && (
        <Receipt
          isOpen={showReceipt}
          onClose={() => setShowReceipt(false)}
          orderData={receiptData}
        />
      )}

      {/* Cancel Order Modal */}
      {showCancelModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold text-red-600 mb-4 font-lilita">
              Cancel Order
            </h2>
            
            <p className="text-gray-700 mb-4 font-outfit">
              Are you sure you want to cancel this order? Please provide a reason for cancellation.
            </p>

            <div className="mb-4">
              <label className="block text-sm font-semibold mb-2 font-outfit">Cancellation Reason</label>
              <textarea
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                placeholder="e.g., Changed my mind, Found a better deal, etc."
                rows={4}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
              />
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => {
                  setShowCancelModal(false)
                  setCancelOrderId(null)
                  setCancelReason('')
                }}
                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-outfit font-semibold"
              >
                Keep Order
              </button>
              <button
                onClick={handleCancelOrder}
                disabled={cancellingOrder || !cancelReason.trim()}
                className="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 disabled:opacity-50 font-outfit font-semibold"
              >
                {cancellingOrder ? 'Cancelling...' : 'Cancel Order'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Refund Proof Modal */}
      {showRefundProofModal && (
        <div className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" onClick={() => setShowRefundProofModal(false)}>
          <div className="relative max-w-4xl w-full" onClick={(e) => e.stopPropagation()}>
            <button
              onClick={() => setShowRefundProofModal(false)}
              className="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl font-bold"
            >
              ✕
            </button>
            <img
              src={refundProofUrl}
              alt="Refund Proof"
              className="w-full h-auto max-h-[80vh] object-contain rounded-lg"
            />
          </div>
        </div>
      )}

      {/* Review Modal */}
      {showReviewModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h2 className="text-xl font-bold text-dark-purple mb-4 font-lilita">
              {reviewId ? 'Edit Review' : 'Add Review'}
            </h2>
            
            <div className="mb-4">
              <label className="block text-sm font-semibold mb-2 font-outfit">Rating</label>
              <div className="flex gap-2">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setReviewRating(star)}
                    className="text-3xl focus:outline-none transition-transform hover:scale-110"
                  >
                    {star <= reviewRating ? '★' : '☆'}
                  </button>
                ))}
              </div>
              <p className="text-sm text-gray-600 mt-1 font-outfit">{reviewRating} out of 5 stars</p>
            </div>

            <div className="mb-4">
              <label className="block text-sm font-semibold mb-2 font-outfit">Comments</label>
              <textarea
                value={reviewComments}
                onChange={(e) => setReviewComments(e.target.value)}
                placeholder="Share your experience with this order..."
                rows={4}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple focus:border-transparent font-outfit"
              />
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => {
                  setShowReviewModal(false)
                  setReviewOrderId(null)
                  setReviewId(null)
                  setReviewRating(5)
                  setReviewComments('')
                }}
                className="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-outfit font-semibold"
              >
                Cancel
              </button>
              <button
                onClick={handleSubmitReview}
                disabled={submittingReview}
                className="flex-1 px-4 py-2 bg-purple text-white rounded-lg hover:bg-dark-purple disabled:opacity-50 font-outfit font-semibold"
              >
                {submittingReview ? 'Submitting...' : (reviewId ? 'Update Review' : 'Submit Review')}
              </button>
            </div>
          </div>
        </div>
      )}
      </main>
      </div>
    </div>
  )
}

export default UserDashboard
