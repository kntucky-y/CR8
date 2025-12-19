import { useEffect, useState } from 'react';
import { adminManagementAPI } from '../services/api';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';

interface Admin {
  id: number;
  username: string;
  password: string;
  is_superadmin: number;
  last_signed_in: string | null;
  last_signed_out: string | null;
}

export default function AdminManagement() {
  const { admin: currentAdmin } = useAuth();
  const { isDarkMode } = useTheme();
  const [admins, setAdmins] = useState<Admin[]>([]);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (currentAdmin?.is_superadmin !== 1) {
      window.location.href = '/dashboard';
      return;
    }
    fetchAdmins();
  }, [currentAdmin]);

  const fetchAdmins = async () => {
    try {
      setLoading(true);
      const response = await adminManagementAPI.getAdmins();
      setAdmins(response.data.admins);
    } catch (error) {
      console.error('Failed to fetch admins:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleAddAdmin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!username.trim() || !password.trim()) {
      setError('Username and password are required');
      return;
    }

    try {
      await adminManagementAPI.addAdmin(username, password);
      setUsername('');
      setPassword('');
      fetchAdmins();
      alert('Admin added successfully');
    } catch (err: any) {
      setError(err.response?.data?.error || 'Failed to add admin');
    }
  };

  const handleDeleteAdmin = async (id: number) => {
    if (confirm('Are you sure you want to delete this admin?')) {
      try {
        await adminManagementAPI.deleteAdmin(id);
        fetchAdmins();
      } catch (error) {
        alert('Failed to delete admin');
      }
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading admin management...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`h-screen flex flex-col ${isDarkMode ? 'bg-black' : 'bg-gray-50'}`}>
      <div className={`${isDarkMode ? 'bg-black' : 'bg-gray-50'} p-4 md:p-8 pb-4`}>
        <h1 className={`text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-6`}>Admin Management</h1>

        {/* Add Admin Form */}
        <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-4 md:p-6`}>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-4`}>Add New Admin</h2>
          {error && (
            <div className="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">{error}</div>
          )}
          <form onSubmit={handleAddAdmin} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <input
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Username"
              className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Password"
              className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
            <button
              type="submit"
              className="px-6 py-2 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700 sm:col-span-2 lg:col-span-1"
            >
              Add Admin
            </button>
          </form>
        </div>
      </div>

      {/* Admins Table */}
      <div className={`mx-4 md:mx-8 mb-4 md:mb-8 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow flex-1 overflow-hidden flex flex-col`}>
        <div className="overflow-auto flex-1">
        <table className="w-full">
          <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
            <tr>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Username</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Role</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Last Signed In</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Last Signed Out</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Actions</th>
            </tr>
          </thead>
          <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
            {admins.map((admin) => (
              <tr key={admin.id} className={isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}>
                <td className={`px-6 py-4 font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{admin.username}</td>
                <td className="px-6 py-4">
                  <span className={`px-2 py-1 text-xs font-bold rounded ${admin.is_superadmin == 1 ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                    {admin.is_superadmin == 1 ? 'Super Admin' : 'Staff'}
                  </span>
                </td>
                <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  {admin.last_signed_in ? new Date(admin.last_signed_in).toLocaleString() : 'Never'}
                </td>
                <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  {admin.last_signed_out ? new Date(admin.last_signed_out).toLocaleString() : 'Never'}
                </td>
                <td className="px-6 py-4">
                  {admin.is_superadmin !== 1 && (
                    <button
                      onClick={() => handleDeleteAdmin(admin.id)}
                      className="px-3 py-1 bg-red-500 text-white text-sm font-semibold rounded hover:bg-red-600"
                    >
                      Delete
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
      </div>
    </section>
  );
}
