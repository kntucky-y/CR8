import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Navbar from '../components/Navbar'

const Register = () => {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    password: '',
    confirm_password: '',
    address: ''
  })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const navigate = useNavigate()
  const { register } = useAuth()

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    })
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    if (formData.password !== formData.confirm_password) {
      setError('Passwords do not match')
      return
    }

    if (formData.password.length < 6) {
      setError('Password must be at least 6 characters long')
      return
    }

    setLoading(true)

    try {
      const { confirm_password, ...registerData } = formData
      await register(registerData)
      navigate('/dashboard')
    } catch (err: any) {
      setError(err.response?.data?.message || 'Registration failed. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="bg-bg-color min-h-screen relative overflow-x-hidden">
      {/* Background decorations */}
      <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img
          src="/img/blubber.png"
          alt="Decoration"
          className="absolute top-0 -left-12 w-1/4 opacity-20 animate-float hidden lg:block"
        />
        <img
          src="/img/bubber.png"
          alt="Decoration"
          className="absolute bottom-0 -right-12 w-1/4 opacity-20 animate-float hidden lg:block"
        />
        <img
          src="/img/blubber.png"
          alt="Decoration"
          className="absolute top-1/2 -right-16 w-1/5 opacity-15 animate-float hidden lg:block"
          style={{ animationDelay: '-2s' }}
        />
        <img
          src="/img/bubber.png"
          alt="Decoration"
          className="absolute bottom-1/3 -left-16 w-1/5 opacity-15 animate-float hidden lg:block"
          style={{ animationDelay: '-3s' }}
        />
      </div>

      <div className="relative z-10">
        <div className="px-4 md:px-10 lg:px-20 mx-auto">
          <Navbar />
        </div>

      <div className="flex items-center justify-center px-4 py-12">
        <div className="max-w-2xl w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8">
          <h2 className="font-poetsen text-darkest-purple text-3xl md:text-4xl text-center mb-6">
            Create Account
          </h2>

          {error && (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label htmlFor="first_name" className="block font-outfit text-dark-purple font-semibold mb-2">
                  First Name *
                </label>
                <input
                  type="text"
                  id="first_name"
                  name="first_name"
                  value={formData.first_name}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                />
              </div>

              <div>
                <label htmlFor="last_name" className="block font-outfit text-dark-purple font-semibold mb-2">
                  Last Name *
                </label>
                <input
                  type="text"
                  id="last_name"
                  name="last_name"
                  value={formData.last_name}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                />
              </div>
            </div>

            <div>
              <label htmlFor="username" className="block font-outfit text-dark-purple font-semibold mb-2">
                Username *
              </label>
              <input
                type="text"
                id="username"
                name="username"
                value={formData.username}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
              />
            </div>

            <div>
              <label htmlFor="email" className="block font-outfit text-dark-purple font-semibold mb-2">
                Email *
              </label>
              <input
                type="email"
                id="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                required
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
              />
            </div>

            <div>
              <label htmlFor="address" className="block font-outfit text-dark-purple font-semibold mb-2">
                Address (Optional)
              </label>
              <input
                type="text"
                id="address"
                name="address"
                value={formData.address}
                onChange={handleChange}
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label htmlFor="password" className="block font-outfit text-dark-purple font-semibold mb-2">
                  Password *
                </label>
                <input
                  type="password"
                  id="password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                />
              </div>

              <div>
                <label htmlFor="confirm_password" className="block font-outfit text-dark-purple font-semibold mb-2">
                  Confirm Password *
                </label>
                <input
                  type="password"
                  id="confirm_password"
                  name="confirm_password"
                  value={formData.confirm_password}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full gradient-btn text-white font-outfit font-bold py-3 px-6 rounded-full hover:scale-105 transition duration-300 ease-in-out disabled:opacity-50"
            >
              {loading ? 'Creating Account...' : 'Register'}
            </button>
          </form>

          <p className="mt-6 text-center font-outfit text-dark-purple">
            Already have an account?{' '}
            <Link to="/login" className="text-purple font-semibold hover:text-light-purple">
              Login here
            </Link>
          </p>
        </div>
      </div>
      </div>
    </div>
  )
}

export default Register
