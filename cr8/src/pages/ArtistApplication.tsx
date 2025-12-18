import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { useNavigate } from 'react-router-dom'
import Navbar from '../components/Navbar'
import api from '../services/api'

const ArtistApplication = () => {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [formData, setFormData] = useState({
    email: user?.email || '',
    full_name: `${user?.first_name || ''} ${user?.last_name || ''}`.trim() || user?.username || '',
    artist_name: user?.artist_name || '',
    contact_number: user?.phone || '',
    portfolio: '',
    product_desc: ''
  })
  const [agreedToTerms, setAgreedToTerms] = useState(false)
  const [showTermsModal, setShowTermsModal] = useState(false)
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState('')

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target
    
    // Phone validation - only allow numbers and limit to 11 digits
    if (name === 'contact_number') {
      const numericValue = value.replace(/\D/g, '').slice(0, 11)
      setFormData({
        ...formData,
        [name]: numericValue
      })
      return
    }
    
    setFormData({
      ...formData,
      [name]: value
    })
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    if (!agreedToTerms) {
      setError('You must agree to the Terms & Conditions')
      return
    }

    // Validate email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(formData.email)) {
      setError('Please enter a valid email address')
      return
    }

    // Validate phone number
    if (!formData.contact_number.match(/^09\d{9}$/)) {
      setError('Please enter a valid 11-digit contact number starting with 09')
      return
    }

    // Validate portfolio URL
    try {
      new URL(formData.portfolio)
    } catch {
      setError('Please enter a valid portfolio URL (must start with http:// or https://)')
      return
    }

    // Validate required fields
    if (!formData.full_name.trim()) {
      setError('Full name is required')
      return
    }

    if (!formData.artist_name.trim()) {
      setError('Artist/Crafter name is required')
      return
    }

    if (!formData.product_desc.trim()) {
      setError('Product description is required')
      return
    }

    setLoading(true)

    try {
      const response = await api.post('/artist-application?action=submit', formData)
      if (response.data.success) {
        setSuccess(true)
      } else {
        setError(response.data.message || 'Failed to submit application')
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to submit application. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  if (!user) {
    navigate('/login')
    return null
  }

  return (
    <div className="bg-bg-color h-screen flex flex-col overflow-hidden font-outfit">
      {/* Background decorations */}
      <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img src="/img/blubber.png" alt="" className="absolute top-20 right-0 w-1/4 opacity-80 animate-float" />
        <img src="/img/bubber.png" alt="" className="absolute bottom-20 left-0 w-1/5 opacity-80 animate-float" style={{ animationDelay: '-2s' }} />
      </div>

      <div className="relative z-10 px-4 md:px-10 lg:px-20">
        <Navbar showSearch={true} />
      </div>

      <main className="flex-1 flex items-center justify-center px-4 py-2 relative z-10">
        <div className="bg-white bg-opacity-90 rounded-3xl shadow-2xl p-4 md:p-6 w-full max-w-3xl">
          <h1 className="font-lilita text-xl md:text-2xl text-purple text-center">Artist Application Form</h1>
          <p className="text-center text-gray-600 mb-3 font-outfit text-xs">
            Join our creative community and showcase your artwork!
          </p>

          {success ? (
            <div className="text-center">
              <div className="bg-green-100 text-green-800 px-3 py-2 rounded mb-3 text-sm">
                Your application has been submitted! We will contact you soon.
              </div>
              <button
                onClick={() => navigate('/dashboard')}
                className="gradient-btn text-white font-outfit font-bold py-2 px-6 rounded-full hover:scale-105 transition"
              >
                Go to Dashboard
              </button>
            </div>
          ) : (
            <>
              {error && (
                <div className="bg-red-100 text-red-800 px-3 py-2 rounded mb-3 text-sm">
                  {error}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-3">
                {/* Basic Information */}
                <div>
                  <h2 className="font-bold text-dark-purple mb-2 text-base border-b pb-1">Basic Information</h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2">
                    <div>
                      <label htmlFor="email" className="block text-xs font-medium text-gray-700 mb-1">
                        Email Address <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="email"
                        name="email"
                        id="email"
                        required
                        value={formData.email}
                        onChange={handleChange}
                        readOnly={!!user?.email}
                        className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-purple"
                      />
                    </div>

                    <div>
                      <label htmlFor="full_name" className="block text-xs font-medium text-gray-700 mb-1">
                        Full Name <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        name="full_name"
                        id="full_name"
                        required
                        value={formData.full_name}
                        onChange={handleChange}
                        maxLength={100}
                        className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-purple"
                      />
                    </div>

                    <div>
                      <label htmlFor="artist_name" className="block text-xs font-medium text-gray-700 mb-1">
                        Artist/Crafter Name <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        name="artist_name"
                        id="artist_name"
                        required
                        value={formData.artist_name}
                        onChange={handleChange}
                        maxLength={100}
                        className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-purple"
                      />
                    </div>

                    <div>
                      <label htmlFor="contact_number" className="block text-xs font-medium text-gray-700 mb-1">
                        Contact Number <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        name="contact_number"
                        id="contact_number"
                        required
                        value={formData.contact_number}
                        onChange={handleChange}
                        placeholder="09XXXXXXXXX"
                        maxLength={11}
                        pattern="09\d{9}"
                        title="Please enter a valid 11-digit phone number starting with 09"
                        className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-purple"
                      />
                    </div>
                  </div>

                  <div className="mt-2">
                    <label htmlFor="portfolio" className="block text-xs font-medium text-gray-700 mb-1">
                      Portfolio/Social Media Link <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="url"
                      name="portfolio"
                      id="portfolio"
                      required
                      value={formData.portfolio}
                      onChange={handleChange}
                      placeholder="https://..."
                      maxLength={500}
                      pattern="https?://.+"
                      title="Please enter a valid URL starting with http:// or https://"
                      className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-purple"
                    />
                    <p className="text-xs text-gray-500 mt-1">
                      Please provide a Google Drive link or a link to any site that shows your portfolio.
                    </p>
                  </div>
                </div>

                {/* Product Information */}
                <div>
                  <h2 className="font-bold text-dark-purple mb-2 text-base border-b pb-1">Product Information</h2>
                  <div>
                    <label htmlFor="product_desc" className="block text-xs font-medium text-gray-700 mb-1">
                      Product Descriptions <span className="text-red-500">*</span>
                    </label>
                    <textarea
                      name="product_desc"
                      id="product_desc"
                      required
                      value={formData.product_desc}
                      onChange={handleChange}
                      rows={2}
                      maxLength={1000}
                      className="border border-gray-300 rounded-md px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-purple"
                      placeholder="Describe the products you plan to sell..."
                    />
                    <p className="text-xs text-gray-500 mt-1">
                      {formData.product_desc.length}/1000 characters
                    </p>
                  </div>
                </div>

                {/* Terms & Conditions */}
                <div>
                  <h2 className="font-bold text-dark-purple mb-2 text-base border-b pb-1">Terms & Conditions</h2>
                  <div className="flex items-center gap-2 mt-2 text-xs">
                    <input
                      type="checkbox"
                      checked={agreedToTerms}
                      onChange={(e) => setAgreedToTerms(e.target.checked)}
                      required
                      id="terms-checkbox"
                      className="h-3 w-3 text-purple focus:ring-purple border-gray-300 rounded"
                    />
                    <label htmlFor="terms-checkbox" className="cursor-pointer">
                      I have read and agree to the{' '}
                      <button
                        type="button"
                        onClick={() => setShowTermsModal(true)}
                        className="text-purple underline"
                      >
                        Terms & Conditions
                      </button>{' '}
                      <span className="text-red-500">*</span>
                    </label>
                  </div>
                </div>

                {/* Submit Button */}
                <div className="text-center pt-2">
                  <button
                    type="submit"
                    disabled={loading || !agreedToTerms}
                    className="gradient-btn text-white font-lilita text-base py-2 px-8 rounded-full disabled:opacity-50 disabled:cursor-not-allowed transition hover:scale-105"
                  >
                    {loading ? 'Submitting...' : 'Submit Application'}
                  </button>
                </div>
              </form>
            </>
          )}
        </div>
      </main>

      {/* Terms Modal */}
      {showTermsModal && (
        <div className="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto p-6 md:p-8 relative">
            <h2 className="font-lilita text-2xl text-purple mb-4">Terms & Conditions</h2>

            <div className="prose prose-sm max-h-80 overflow-y-auto pr-4 text-gray-700 space-y-2">
              <p>Please read these terms and conditions carefully before submitting your application.</p>
              <h3 className="font-bold">1. Eligibility</h3>
              <p>Artists must be 18 years or older to apply. All submitted artwork must be the original, sole creation of the applicant.</p>
              <h3 className="font-bold">2. Submissions</h3>
              <p>All works must be submitted via a public portfolio link (e.g., Google Drive, Behance, personal website). Incomplete applications will not be considered.</p>
              <h3 className="font-bold">3. Rights & Reproductions</h3>
              <p>By submitting your application, you grant CR8 the non-exclusive right to display your submitted works on our website and social media for promotional purposes. Full credit will always be given to the artist.</p>
              <h3 className="font-bold">4. Selection Process</h3>
              <p>Our curation team will review all applications. The selection process is based on quality, originality, and alignment with the CR8 brand. We will contact successful applicants via email.</p>
              <h3 className="font-bold">5. Agreement</h3>
              <p>By checking the "I agree" box, you confirm that you have read, understood, and agree to be bound by these terms and conditions.</p>
            </div>

            <div className="flex justify-end gap-4 mt-6 pt-4 border-t">
              <button
                onClick={() => setShowTermsModal(false)}
                className="px-6 py-2 border border-purple text-purple rounded-full hover:bg-purple hover:text-white transition"
              >
                Close
              </button>
              <button
                onClick={() => {
                  setAgreedToTerms(true)
                  setShowTermsModal(false)
                }}
                className="gradient-btn text-white px-6 py-2 rounded-full"
              >
                I Agree
              </button>
            </div>
          </div>
        </div>
      )}

      <footer className="bg-dark-purple bg-opacity-10 py-4 mt-auto relative z-10">
        <p className="text-center text-xs font-poetsen text-dark-purple">© 2025 CR8. All Rights Reserved.</p>
      </footer>
    </div>
  )
}

export default ArtistApplication
