import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCart } from '../context/CartContext'
import { useAuth } from '../context/AuthContext'
import Navbar from '../components/Navbar'
import Receipt from '../components/Receipt'

interface Location {
  code: string
  name: string
}

const Checkout = () => {
  const { cart } = useCart()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    streetAddress: '',
    region: '070000000', // Central Visayas
    province: '072200000', // Cebu
    city: '',
    barangay: '',
    postalCode: '',
    paymentMethod: ''
  })
  const [regions, setRegions] = useState<Location[]>([])
  const [provinces, setProvinces] = useState<Location[]>([])
  const [cities, setCities] = useState<Location[]>([])
  const [barangays, setBarangays] = useState<Location[]>([])
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [showQR, setShowQR] = useState(false)
  const [proofFile, setProofFile] = useState<File | null>(null)
  const [showReceipt, setShowReceipt] = useState(false)
  const [receiptData, setReceiptData] = useState<any>(null)
  
  const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
  const API_PH = 'https://psgc.gitlab.io/api'

  // Prefill user data
  useEffect(() => {
    if (user) {
      setFormData(prev => ({
        ...prev,
        firstName: user.first_name || '',
        lastName: user.last_name || '',
        email: user.email || '',
        phone: user.phone || '',
        streetAddress: user.address || ''
      }))
    }
  }, [user])

  useEffect(() => {
    fetchRegions()
  }, [])

  useEffect(() => {
    if (formData.region) fetchProvinces(formData.region)
  }, [formData.region])

  useEffect(() => {
    if (formData.province) fetchCities(formData.province)
  }, [formData.province])

  useEffect(() => {
    if (formData.city) fetchBarangays(formData.city)
  }, [formData.city])

  useEffect(() => {
    if (formData.paymentMethod && formData.paymentMethod !== 'cod') {
      setShowQR(true)
    } else {
      setShowQR(false)
    }
  }, [formData.paymentMethod])

  const fetchRegions = async () => {
    try {
      const response = await fetch(`${API_PH}/regions/`)
      const data = await response.json()
      setRegions(data.sort((a: Location, b: Location) => a.name.localeCompare(b.name)))
      fetchProvinces('070000000')
    } catch (error) {
      console.error('Failed to fetch regions:', error)
    }
  }

  const fetchProvinces = async (regionCode: string) => {
    try {
      const response = await fetch(`${API_PH}/regions/${regionCode}/provinces/`)
      const data = await response.json()
      setProvinces(data.sort((a: Location, b: Location) => a.name.localeCompare(b.name)))
      if (regionCode === '070000000') {
        fetchCities('072200000')
      }
    } catch (error) {
      console.error('Failed to fetch provinces:', error)
    }
  }

  const fetchCities = async (provinceCode: string) => {
    try {
      const response = await fetch(`${API_PH}/provinces/${provinceCode}/cities-municipalities/`)
      const data = await response.json()
      setCities(data.sort((a: Location, b: Location) => a.name.localeCompare(b.name)))
    } catch (error) {
      console.error('Failed to fetch cities:', error)
    }
  }

  const fetchBarangays = async (cityCode: string) => {
    try {
      const response = await fetch(`${API_PH}/cities-municipalities/${cityCode}/barangays/`)
      const data = await response.json()
      setBarangays(data.sort((a: Location, b: Location) => a.name.localeCompare(b.name)))
    } catch (error) {
      console.error('Failed to fetch barangays:', error)
    }
  }

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target
    
    // Phone validation - only allow numbers and limit to 11 digits
    if (name === 'phone') {
      const numericValue = value.replace(/\D/g, '').slice(0, 11)
      setFormData(prev => ({ ...prev, [name]: numericValue }))
      return
    }
    
    // Postal code - only numbers
    if (name === 'postalCode') {
      const numericValue = value.replace(/\D/g, '')
      setFormData(prev => ({ ...prev, [name]: numericValue }))
      return
    }
    
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setProofFile(e.target.files[0])
    }
  }

  const handlePlaceOrder = async (e: React.FormEvent) => {
    e.preventDefault()

    // Validation
    if (!formData.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
      alert('Please enter a valid email address')
      return
    }

    if (!formData.phone.match(/^09\d{9}$/)) {
      alert('Please enter a valid 11-digit phone number starting with 09')
      return
    }

    if (formData.paymentMethod !== 'cod' && !proofFile) {
      alert('Please upload proof of payment')
      return
    }

    setIsSubmitting(true)

    try {
      const cityName = cities.find(c => c.code === formData.city)?.name || ''
      const provinceName = provinces.find(p => p.code === formData.province)?.name || ''
      const barangayName = barangays.find(b => b.code === formData.barangay)?.name || ''
      
      const formDataToSend = new FormData()
      formDataToSend.append('first_name', formData.firstName)
      formDataToSend.append('last_name', formData.lastName)
      formDataToSend.append('email', formData.email)
      formDataToSend.append('contact_number', formData.phone)
      formDataToSend.append('street_address', formData.streetAddress)
      formDataToSend.append('country', 'Philippines')
      formDataToSend.append('province_text', provinceName)
      formDataToSend.append('city_text', cityName)
      formDataToSend.append('barangay_text', barangayName)
      formDataToSend.append('postal_code', formData.postalCode)
      formDataToSend.append('payment_method', formData.paymentMethod)
      formDataToSend.append('total', total.toString())
      formDataToSend.append('cart_items', JSON.stringify(cart))
      
      if (proofFile) {
        formDataToSend.append('proof', proofFile)
      }

      const response = await fetch('https://cr8.dcism.org/api/orders.php?action=create', {
        method: 'POST',
        credentials: 'include',
        body: formDataToSend
      })

      const data = await response.json()
      console.log('Order response:', data) // Debug log
      
      if (data.success) {
        // Prepare receipt data
        setReceiptData({
          order_no: data.order_no,
          created_at: new Date().toISOString(),
          items: cart.map(item => ({
            product_name: item.product_name,
            variant_name: (item as any).variant_name || '',
            quantity: item.quantity,
            price: item.price,
            image: item.image
          })),
          first_name: formData.firstName,
          last_name: formData.lastName,
          email: formData.email,
          contact_number: formData.phone,
          address: `${formData.streetAddress}, ${barangayName}, ${cityName}, ${provinceName}, Philippines ${formData.postalCode}`,
          payment_method: formData.paymentMethod,
          total: total
        })
        setShowReceipt(true)
      } else {
        alert(data.message || 'Failed to place order')
        console.error('Order error:', data)
      }
    } catch (error) {
      console.error('Order error:', error)
      alert('Failed to place order. Please try again.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="bg-bg-color min-h-screen">
      <div className="px-4 md:px-10 lg:px-20 mx-auto">
        <Navbar showSearch={true} />
      </div>
      <main className="px-4 md:px-10 lg:px-20 mx-auto py-12">
        <h1 className="font-poetsen text-darkest-purple text-4xl text-center mb-8">Checkout</h1>
        {cart.length === 0 ? (
          <div className="max-w-4xl mx-auto">
            <div className="bg-white bg-opacity-50 rounded-xl p-8">
              <p className="text-center text-gray-600">Your cart is empty</p>
            </div>
          </div>
        ) : (
          <div className="max-w-7xl mx-auto">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Left: Order Summary */}
              <div className="bg-white bg-opacity-50 rounded-xl p-6 lg:p-8 h-fit lg:sticky lg:top-6">
                <h2 className="font-lilita text-dark-purple text-2xl mb-4">Order Summary</h2>
                <div className="space-y-4 mb-6 max-h-96 overflow-y-auto">
                  {cart.map(item => (
                    <div key={item.id} className="flex gap-3 items-center bg-white p-3 rounded-lg">
                      <img 
                        src={`https://cr8.dcism.org/${item.image || item.image_url}`} 
                        alt={item.product_name} 
                        className="w-16 h-16 object-cover rounded"
                        onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }}
                      />
                      <div className="flex-1 min-w-0">
                        <p className="font-outfit font-bold text-sm truncate">{item.product_name}</p>
                        <p className="text-xs text-gray-600">Qty: {item.quantity}</p>
                        <p className="text-xs text-gray-600">₱{item.price.toFixed(2)} each</p>
                      </div>
                      <p className="font-bold text-sm">₱{(item.price * item.quantity).toFixed(2)}</p>
                    </div>
                  ))}
                </div>
                <div className="border-t pt-4">
                  <div className="flex justify-between items-center text-xl font-bold">
                    <span>Total:</span>
                    <span>₱{total.toFixed(2)}</span>
                  </div>
                </div>
              </div>

              {/* Right: Shipping Form */}
              <div className="bg-white bg-opacity-50 rounded-xl p-6 lg:p-8">
                <h2 className="font-lilita text-dark-purple text-2xl mb-4">Customer Information</h2>
                <form onSubmit={handlePlaceOrder} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">First Name *</label>
                      <input
                        type="text"
                        name="firstName"
                        value={formData.firstName}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      />
                    </div>
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">Last Name *</label>
                      <input
                        type="text"
                        name="lastName"
                        value={formData.lastName}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      />
                    </div>
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">Email *</label>
                      <input
                        type="email"
                        name="email"
                        value={formData.email}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      />
                    </div>
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">Phone *</label>
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleInputChange}
                        required
                        maxLength={11}
                        pattern="09\d{9}"
                        title="Please enter a valid 11-digit phone number starting with 09"
                        placeholder="09XXXXXXXXX"
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      />
                    </div>
                  </div>

                  <h3 className="font-lilita text-dark-purple text-xl mt-6">Shipping Address</h3>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">Region *</label>
                      <select
                        name="region"
                        value={formData.region}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      >
                        <option value="">Select Region</option>
                        {regions.map(r => (
                          <option key={r.code} value={r.code}>{r.name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">Province *</label>
                      <select
                        name="province"
                        value={formData.province}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      >
                        <option value="">Select Province</option>
                        {provinces.map(p => (
                          <option key={p.code} value={p.code}>{p.name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">City *</label>
                      <select
                        name="city"
                        value={formData.city}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      >
                        <option value="">Select City</option>
                        {cities.map(c => (
                          <option key={c.code} value={c.code}>{c.name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block font-outfit text-sm font-bold mb-2">Barangay *</label>
                      <select
                        name="barangay"
                        value={formData.barangay}
                        onChange={handleInputChange}
                        required
                        className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                      >
                        <option value="">Select Barangay</option>
                        {barangays.map(b => (
                          <option key={b.code} value={b.code}>{b.name}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block font-outfit text-sm font-bold mb-2">Street Address *</label>
                    <input
                      type="text"
                      name="streetAddress"
                      value={formData.streetAddress}
                      onChange={handleInputChange}
                      required
                      placeholder="Building, House No."
                      className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                    />
                  </div>

                  <div>
                    <label className="block font-outfit text-sm font-bold mb-2">Postal Code *</label>
                    <input
                      type="text"
                      name="postalCode"
                      value={formData.postalCode}
                      onChange={handleInputChange}
                      required
                      className="w-full px-4 py-2 rounded-lg border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                    />
                  </div>

                  <h3 className="font-lilita text-dark-purple text-xl mt-6">Payment Method</h3>
                  <div className="space-y-2">
                    <label className="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-purple hover:bg-opacity-10 has-[:checked]:border-purple has-[:checked]:bg-purple has-[:checked]:bg-opacity-10">
                      <input
                        type="radio"
                        name="paymentMethod"
                        value="gcash"
                        onChange={handleInputChange}
                        required
                        className="mr-3"
                      />
                      <span className="font-outfit">GCash</span>
                    </label>
                    <label className="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-purple hover:bg-opacity-10 has-[:checked]:border-purple has-[:checked]:bg-purple has-[:checked]:bg-opacity-10">
                      <input
                        type="radio"
                        name="paymentMethod"
                        value="bank"
                        onChange={handleInputChange}
                        className="mr-3"
                      />
                      <span className="font-outfit">Bank Transfer</span>
                    </label>
                    <label className="flex items-center p-3 border-2 rounded-lg cursor-pointer hover:bg-purple hover:bg-opacity-10 has-[:checked]:border-purple has-[:checked]:bg-purple has-[:checked]:bg-opacity-10">
                      <input
                        type="radio"
                        name="paymentMethod"
                        value="maya"
                        onChange={handleInputChange}
                        className="mr-3"
                      />
                      <span className="font-outfit">Maya</span>
                    </label>
                  </div>

                  {showQR && (
                    <div className="border-t pt-4 space-y-4">
                      <h3 className="font-lilita text-dark-purple text-lg">Complete Payment</h3>
                      <img 
                        src={`https://cr8.dcism.org/cr8images/qrs/${formData.paymentMethod}.jpg`}
                        alt="QR Code"
                        className="w-48 h-48 mx-auto border-4 border-white rounded-lg shadow-md"
                      />
                      <div className="text-center text-sm bg-purple bg-opacity-10 p-3 rounded-md">
                        <p className="font-bold">NOTE: Please pay the exact amount of ₱{total.toFixed(2)}.</p>
                      </div>
                      <div>
                        <label className="block font-outfit text-sm font-bold mb-2">Upload Proof of Payment *</label>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={handleFileChange}
                          required
                          className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple file:bg-opacity-10 file:text-purple hover:file:bg-opacity-20 cursor-pointer"
                        />
                      </div>
                    </div>
                  )}

                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="w-full bg-green-600 text-white py-4 rounded-full font-lilita text-xl hover:bg-green-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed mt-6"
                  >
                    {isSubmitting ? 'PLACING ORDER...' : 'PLACE ORDER'}
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}
      </main>

      {/* Receipt Modal */}
      {showReceipt && receiptData && (
        <Receipt
          isOpen={showReceipt}
          onClose={() => {
            setShowReceipt(false)
            navigate('/dashboard')
          }}
          orderData={receiptData}
        />
      )}
    </div>
  )
}

export default Checkout
