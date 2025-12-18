import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import axios from 'axios';
import Navbar from '../components/Navbar';

const API_URL = 'https://cr8.dcism.org/api';

export default function ResetPassword() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token');

  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [verifying, setVerifying] = useState(true);
  const [tokenValid, setTokenValid] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    if (!token) {
      setError('Invalid reset link');
      setVerifying(false);
      return;
    }

    // Verify token
    axios.post(`${API_URL}/auth.php?action=verify-reset-token`, { token })
      .then(() => {
        setTokenValid(true);
        setVerifying(false);
      })
      .catch(() => {
        setError('This reset link is invalid or has expired');
        setVerifying(false);
      });
  }, [token]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setMessage('');

    if (password.length < 6) {
      setError('Password must be at least 6 characters long');
      return;
    }

    if (password !== confirmPassword) {
      setError('Passwords do not match');
      return;
    }

    setLoading(true);

    try {
      const response = await axios.post(`${API_URL}/auth.php?action=reset-password`, {
        token,
        password
      });

      if (response.data.success) {
        setSuccess(true);
        setMessage('Password reset successfully! Redirecting to login...');
        setTimeout(() => {
          navigate('/login');
        }, 3000);
      } else {
        setError(response.data.message || 'Failed to reset password');
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'An error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (verifying) {
    return (
      <div className="bg-bg-color min-h-screen">
        <div className="px-4 md:px-10 lg:px-20 mx-auto">
          <Navbar />
        </div>
        <div className="flex items-center justify-center px-4 py-12">
          <div className="max-w-md w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8 text-center">
            <div className="w-16 h-16 border-4 border-purple border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p className="font-outfit text-dark-purple">Verifying reset link...</p>
          </div>
        </div>
      </div>
    );
  }

  if (!tokenValid) {
    return (
      <div className="bg-bg-color min-h-screen">
        <div className="px-4 md:px-10 lg:px-20 mx-auto">
          <Navbar />
        </div>
        <div className="flex items-center justify-center px-4 py-12">
          <div className="max-w-md w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8 text-center">
            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <h2 className="font-poetsen text-darkest-purple text-2xl md:text-3xl mb-4">Invalid Reset Link</h2>
            <p className="font-outfit text-dark-purple mb-6">{error}</p>
            <Link
              to="/forgot-password"
              className="inline-block gradient-btn text-white font-outfit font-bold py-3 px-6 rounded-full hover:scale-105 transition duration-300 ease-in-out"
            >
              Request New Link
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (success) {
    return (
      <div className="bg-bg-color min-h-screen">
        <div className="px-4 md:px-10 lg:px-20 mx-auto">
          <Navbar />
        </div>
        <div className="flex items-center justify-center px-4 py-12">
          <div className="max-w-md w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8 text-center">
            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 className="font-poetsen text-darkest-purple text-2xl md:text-3xl mb-4">Success!</h2>
            <p className="font-outfit text-dark-purple mb-6">{message}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-bg-color min-h-screen">
      <div className="px-4 md:px-10 lg:px-20 mx-auto">
        <Navbar />
      </div>

      <div className="flex items-center justify-center px-4 py-12">
        <div className="max-w-md w-full bg-white bg-opacity-50 rounded-xl shadow-lg p-8">
          <h2 className="font-poetsen text-darkest-purple text-3xl md:text-4xl text-center mb-6">
            Reset Your Password
          </h2>

          {error && (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="password" className="block font-outfit text-dark-purple font-semibold mb-2">
                New Password
              </label>
              <input
                type="password"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                placeholder="Enter new password"
                required
                minLength={6}
              />
            </div>

            <div>
              <label htmlFor="confirmPassword" className="block font-outfit text-dark-purple font-semibold mb-2">
                Confirm Password
              </label>
              <input
                type="password"
                id="confirmPassword"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="w-full px-4 py-2 border-2 border-dark-purple rounded-lg focus:outline-none focus:ring-2 focus:ring-light-purple"
                placeholder="Confirm new password"
                required
                minLength={6}
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full gradient-btn text-white font-outfit font-bold py-3 px-6 rounded-full hover:scale-105 transition duration-300 ease-in-out disabled:opacity-50"
            >
              {loading ? 'Resetting Password...' : 'Reset Password'}
            </button>
          </form>

          <p className="mt-6 text-center font-outfit text-dark-purple">
            Remember your password?{' '}
            <Link to="/login" className="text-purple font-semibold hover:text-light-purple">
              Back to Login
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
