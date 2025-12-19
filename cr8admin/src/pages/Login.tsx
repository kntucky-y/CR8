import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useNavigate } from 'react-router-dom';

export default function Login() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login, logoutMessage, clearLogoutMessage } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    // Clear logout message when component unmounts
    return () => {
      if (logoutMessage) {
        clearLogoutMessage();
      }
    };
  }, [logoutMessage, clearLogoutMessage]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(username, password);
      navigate('/dashboard');
    } catch (err: any) {
      console.error('Login error:', err);
      const errorMessage = err.response?.data?.error || err.message || 'Invalid username or password.';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-gray-100 min-h-screen flex flex-col md:flex-row">
      {/* Logo/Brand Section */}
      <div className="w-full md:w-64 bg-white border-b md:border-b-0 md:border-r flex flex-col items-center justify-center py-8 md:min-h-screen">
        <img src="/img/cr8-logo.png" alt="Logo" className="w-20 h-20 rounded-full mb-4" />
        <span className="font-bold text-2xl text-purple-800">CR8 Cebu</span>
      </div>

      {/* Login Form Section */}
      <main className="flex-1 flex items-center justify-center py-8">
        <div className="bg-white rounded-xl shadow-xl p-6 sm:p-10 w-full max-w-md flex flex-col items-center">
          <div className="bg-purple-100 rounded-full p-3 mb-4">
            <svg className="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 16v-4m0-4h.01" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-gray-900 mb-2 text-center">Admin Login</h1>
          <p className="text-gray-500 text-center mb-6">Access the CR8 Shop Admin panel</p>

          {logoutMessage && (
            <div className="bg-yellow-100 text-yellow-800 px-4 py-3 rounded mb-4 w-full text-center border border-yellow-300">
              <strong>Session Expired:</strong> {logoutMessage}
            </div>
          )}

          {error && (
            <div className="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 w-full text-center">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="w-full flex flex-col gap-4">
            <div>
              <label className="block text-sm font-semibold mb-1">Username</label>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="Enter your username"
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-purple-400"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-semibold mb-1">Password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-purple-400"
                required
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className="bg-purple-700 text-white font-bold py-2 rounded-md mt-2 hover:bg-purple-800 transition disabled:opacity-50"
            >
              {loading ? 'Signing In...' : 'Sign In'}
            </button>
          </form>
        </div>
      </main>
    </div>
  );
}
