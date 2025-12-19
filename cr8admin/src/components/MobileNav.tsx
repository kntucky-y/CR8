import { NavLink } from 'react-router-dom';
import { useState } from 'react';
import { useAuth } from '../context/AuthContext';

export default function MobileNav() {
  const [isOpen, setIsOpen] = useState(false);
  const { admin, logout } = useAuth();

  const handleLogout = async () => {
    try {
      await logout();
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <>
      {/* Mobile Header */}
      <div className="md:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b px-4 py-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <img src="/img/cr8-logo.png" alt="Logo" className="w-8 h-8 rounded-full" />
          <span className="font-bold text-lg text-purple-800">CR8 Cebu</span>
        </div>
        <button
          onClick={() => setIsOpen(!isOpen)}
          className="p-2 rounded-lg hover:bg-gray-100"
        >
          <svg className="w-6 h-6" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            {isOpen ? (
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            ) : (
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            )}
          </svg>
        </button>
      </div>

      {/* Mobile Menu */}
      {isOpen && (
        <div className="md:hidden fixed inset-0 z-40 bg-white overflow-y-auto pt-16">
          <nav className="flex flex-col gap-1 p-4">
            <NavLink
              to="/dashboard"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Dashboard
            </NavLink>
            <NavLink
              to="/inventory"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Inventory
            </NavLink>
            <NavLink
              to="/orders"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Orders
            </NavLink>
            <NavLink
              to="/customers"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Customers
            </NavLink>
            <NavLink
              to="/artists"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Artists
            </NavLink>
            <NavLink
              to="/artist_applications"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Applications
            </NavLink>
            <NavLink
              to="/inbox"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Inbox
            </NavLink>
            <NavLink
              to="/sales"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Sales
            </NavLink>
            <NavLink
              to="/reports"
              onClick={() => setIsOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                  isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                }`
              }
            >
              Reports
            </NavLink>
            {admin && admin.is_superadmin === 1 && (
              <NavLink
                to="/admin-management"
                onClick={() => setIsOpen(false)}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
                    isActive ? 'bg-purple-100 text-purple-700' : 'hover:bg-purple-50 text-gray-700'
                  }`
                }
              >
                Admin Management
              </NavLink>
            )}
          </nav>

          <div className="p-4 border-t mt-4">
            <div className="flex items-center gap-3 px-2 py-2 mb-3">
              <div className="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                <span className="text-purple-700 font-bold">{admin?.username[0].toUpperCase()}</span>
              </div>
              <div>
                <p className="font-semibold text-gray-800">{admin?.username}</p>
                <p className="text-xs text-gray-500">{admin?.is_superadmin === 1 ? 'Superadmin' : 'Admin'}</p>
              </div>
            </div>
            <button
              onClick={handleLogout}
              className="w-full bg-red-500 text-white py-2 rounded-lg font-semibold hover:bg-red-600 transition"
            >
              Logout
            </button>
          </div>
        </div>
      )}
    </>
  );
}
