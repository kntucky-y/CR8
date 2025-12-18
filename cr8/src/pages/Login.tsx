import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import Navbar from '../components/Navbar'
import api from '../services/api'

const Login = () => {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const navigate = useNavigate()
  const { login } = useAuth()
  const { addToCart } = useCart()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      await login(email, password)
      
      // Check for redirect intent
      const redirectData = localStorage.getItem('redirectAfterLogin')
      if (redirectData) {
        const intent = JSON.parse(redirectData)
        localStorage.removeItem('redirectAfterLogin')
        
        if (intent.action === 'addToCart') {
          // Add item to cart and go to shop
          try {
            await addToCart(intent.productId, intent.quantity || 1)
            navigate('/shop')
            return
          } catch (err) {
            console.error('Failed to add to cart:', err)
          }
        } else if (intent.action === 'sellProducts') {
          // Check user role and redirect accordingly
          const response = await api.get('/auth?action=check')
          if (response.data.success) {
            const user = response.data.user
            if (user.role === 'artist') {
              navigate('/dashboard')
            } else {
              navigate('/artist-application')
            }
            return
          }
        }
      }
      
      // Default redirect
      navigate('/dashboard')
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed. Please check your credentials.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="bg-bg-color min-h-screen">
      <div className="px-4 md:px-10 lg:px-20 mx-auto">
        <Navbar />
      </div>

      <div className="flex items-center justify-center px-4 py-12">
        <div className="max-w-md w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8">
          <h2 className="font-poetsen text-darkest-purple text-3xl md:text-4xl text-center mb-6">
            Welcome Back
          </h2>

          {error && (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="email" className="block font-outfit text-dark-purple font-semibold mb-2">
                Email or Username
              </label>
              <input
                type="text"
                id="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                placeholder="Enter your email or username"
              />
            </div>

            <div>
              <label htmlFor="password" className="block font-outfit text-dark-purple font-semibold mb-2">
                Password
              </label>
              <input
                type="password"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                placeholder="Enter your password"
              />
            </div>

            <div className="flex items-center justify-between">
              <Link to="/forgot-password" className="text-sm text-purple hover:text-light-purple">
                Forgot Password?
              </Link>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full gradient-btn text-white font-outfit font-bold py-3 px-6 rounded-full hover:scale-105 transition duration-300 ease-in-out disabled:opacity-50"
            >
              {loading ? 'Logging in...' : 'Login'}
            </button>
          </form>

          <p className="mt-6 text-center font-outfit text-dark-purple">
            Don't have an account?{' '}
            <Link to="/register" className="text-purple font-semibold hover:text-light-purple">
              Register here
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}

export default Login
