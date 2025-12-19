import { useState } from 'react'
import { Link } from 'react-router-dom'
import Navbar from '../components/Navbar'
import api from '../services/api'

const ForgotPassword = () => {
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      const response = await api.post('/auth?action=forgot-password', { email })
      if (response.data.success) {
        setSuccess(true)
      } else {
        setError(response.data.message || 'Failed to send reset email')
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'An error occurred. Please try again.')
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
          src="/img/bubber.png"
          alt="Decoration"
          className="absolute top-1/2 -right-16 w-1/5 opacity-15 animate-float hidden lg:block"
          style={{ animationDelay: '-2s' }}
        />
        <img
          src="/img/blubber.png"
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
        <div className="max-w-md w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8">
          <h2 className="font-poetsen text-darkest-purple text-3xl md:text-4xl text-center mb-6">
            Reset Password
          </h2>

          {success ? (
            <div className="text-center">
              <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p className="font-semibold">Check your email!</p>
                <p className="text-sm mt-2">
                  We've sent a password reset link to {email}. Please check your inbox and follow the instructions.
                </p>
              </div>
              <Link to="/login" className="text-purple hover:text-light-purple font-semibold">
                Back to Login
              </Link>
            </div>
          ) : (
            <>
              <p className="font-outfit text-dark-purple text-center mb-6">
                Enter your email address and we'll send you a link to reset your password.
              </p>

              {error && (
                <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                  {error}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-6">
                <div>
                  <label htmlFor="email" className="block font-outfit text-dark-purple font-semibold mb-2">
                    Email Address
                  </label>
                  <input
                    type="email"
                    id="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                    placeholder="Enter your email"
                  />
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className="w-full gradient-btn text-white font-outfit font-bold py-3 px-6 rounded-full hover:scale-105 transition duration-300 ease-in-out disabled:opacity-50"
                >
                  {loading ? 'Sending...' : 'Send Reset Link'}
                </button>
              </form>

              <div className="mt-6 text-center">
                <Link to="/login" className="text-purple hover:text-light-purple font-semibold">
                  Back to Login
                </Link>
              </div>
            </>
          )}
        </div>
      </div>
      </div>
    </div>
  )
}

export default ForgotPassword
