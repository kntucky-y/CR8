import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import Navbar from '../components/Navbar'
import api from '../services/api'

const Contact = () => {
  const { user } = useAuth()
  const [formData, setFormData] = useState({
    name: user?.username || '',
    email: user?.email || '',
    subject: '',
    message: ''
  })
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState('')

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    })
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      const response = await api.post('/contact', {
        action: 'send',
        ...formData
      })
      if (response.data.success) {
        setSuccess(true)
        setFormData({
          name: user?.username || '',
          email: user?.email || '',
          subject: '',
          message: ''
        })
      } else {
        setError(response.data.message || 'Failed to send message')
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to send message. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="bg-bg-color h-screen flex flex-col overflow-hidden">
      {/* Background decorations */}
      <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none opacity-15">
        <img src="/img/squirrel.png" className="absolute w-48 top-[5%] left-[-3%] animate-float" style={{ animationDuration: '40s' }} alt="" />
        <img src="/img/dino.png" className="absolute w-64 top-[10%] right-[-5%] animate-float" style={{ animationDuration: '38s' }} alt="" />
        <img src="/img/hamster.png" className="absolute w-56 bottom-[-5%] left-[20%] animate-float" style={{ animationDuration: '32s' }} alt="" />
      </div>

      <div className="relative px-4 md:px-10 lg:px-20 mx-auto w-full">
        <Navbar />
      </div>

      <main className="relative flex-1 flex items-center justify-center px-4 py-2">
        <div className="max-w-3xl mx-auto bg-white/90 backdrop-blur-sm rounded-lg shadow-lg p-4 md:p-6 w-full">
          <h2 className="text-2xl md:text-3xl font-lilita text-center text-dark-purple">Contact Us</h2>
          <p className="text-gray-700 font-outfit text-center text-sm mb-3">
            We'd love to hear from you! Fill out the form below and our team will get back to you within 24 hours.
          </p>

          {success && (
            <div className="bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded mb-3 text-sm">
              Message sent successfully! We'll get back to you soon.
            </div>
          )}

          {error && (
            <div className="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded mb-3 text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-3">
            <div>
              <label htmlFor="name" className="block text-sm font-poetsen text-dark-purple">Name</label>
              <input
                id="name"
                name="name"
                type="text"
                required
                value={formData.name}
                onChange={handleChange}
                className="mt-1 block w-full border border-purple/20 rounded-lg p-2 text-sm font-outfit focus:outline-none focus:ring-2 focus:ring-light-purple/20 transition-colors"
              />
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-poetsen text-dark-purple">Email</label>
              <input
                id="email"
                name="email"
                type="email"
                required
                value={formData.email}
                onChange={handleChange}
                className="mt-1 block w-full border border-purple/20 rounded-lg p-2 text-sm font-outfit focus:outline-none focus:ring-2 focus:ring-light-purple/20 transition-colors"
              />
            </div>

            <div>
              <label htmlFor="subject" className="block text-sm font-poetsen text-dark-purple">Subject</label>
              <input
                id="subject"
                name="subject"
                type="text"
                required
                value={formData.subject}
                onChange={handleChange}
                className="mt-1 block w-full border border-purple/20 rounded-lg p-2 text-sm font-outfit focus:outline-none focus:ring-2 focus:ring-light-purple/20 transition-colors"
              />
            </div>

            <div>
              <label htmlFor="message" className="block text-sm font-poetsen text-dark-purple">Message</label>
              <textarea
                id="message"
                name="message"
                required
                rows={3}
                value={formData.message}
                onChange={handleChange}
                className="mt-1 block w-full border border-purple/20 rounded-lg p-2 text-sm font-outfit focus:outline-none focus:ring-2 focus:ring-light-purple/20 transition-colors"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full gradient-btn text-white font-outfit font-bold py-2 px-6 rounded-full hover:scale-105 transition duration-300 ease-in-out disabled:opacity-50"
            >
              {loading ? 'Sending...' : 'Send Message'}
            </button>
          </form>

          <div className="mt-4 pt-4 border-t border-gray-200">
            <h3 className="font-lilita text-dark-purple text-lg mb-2">Other Ways to Reach Us</h3>
            <div className="space-y-1 font-outfit text-gray-700 text-sm">
              <p><strong>Email:</strong> support@cr8.com</p>
              <p><strong>Instagram:</strong> <a href="https://www.instagram.com/cr8.ceb/?hl=en" target="_blank" rel="noopener noreferrer" className="text-purple hover:underline">@cr8.ceb</a></p>
              <p><strong>Location:</strong> Cebu, Philippines</p>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}

export default Contact
