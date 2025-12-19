import { NavLink, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { useNotifications } from '../context/NotificationsContext';

export default function Sidebar() {
  const { admin, logout } = useAuth();
  const { isDarkMode, toggleDarkMode } = useTheme();
  const navigate = useNavigate();
  const { notifications } = useNotifications();
  const [showLogoutMenu, setShowLogoutMenu] = useState(false);

  const handleLogout = async () => {
    try {
      await logout();
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  const navLinkClass = (isActive: boolean) =>
    `flex items-center gap-3 px-4 py-3 rounded-lg font-semibold ${
      isActive 
        ? isDarkMode ? 'bg-purple-900 text-purple-300' : 'bg-purple-100 text-purple-700'
        : isDarkMode ? 'hover:bg-gray-900 text-gray-300' : 'hover:bg-purple-50 text-gray-700'
    }`;

  return (
    <aside className={`sticky top-0 h-screen z-40 w-64 ${isDarkMode ? 'bg-black border-gray-800' : 'bg-white'} border-r flex-shrink-0 hidden md:flex flex-col justify-between`}>
      <div>
        <button 
          onClick={() => navigate('/dashboard')}
          className={`flex items-center gap-2 px-6 py-6 border-b w-full hover:bg-opacity-80 transition-colors ${isDarkMode ? 'border-gray-800 hover:bg-gray-900' : 'hover:bg-gray-50'}`}
        >
          <img src="/img/cr8-logo.png" alt="Logo" className="w-10 h-10 rounded-full" />
          <span className={`font-bold text-xl ${isDarkMode ? 'text-purple-400' : 'text-purple-800'}`}>CR8 Cebu</span>
        </button>

        <nav className="flex flex-col gap-1 mt-6 px-2">
          <NavLink
            to="/dashboard"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
            Dashboard
          </NavLink>

          <NavLink
            to="/inventory"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
            </svg>
            Inventory
          </NavLink>

          <NavLink
            to="/orders"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            </svg>
            Orders
            {notifications.pending_orders_count > 0 && (
              <span className="ml-auto bg-purple-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                {notifications.pending_orders_count}
              </span>
            )}
          </NavLink>

          <NavLink
            to="/customers"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            Customers
          </NavLink>

          <NavLink
            to="/artists"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" />
            </svg>
            Artists
          </NavLink>

          <NavLink
            to="/artist_applications"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            Applications
            {notifications.pending_apps_count > 0 && (
              <span className="ml-auto bg-purple-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                {notifications.pending_apps_count}
              </span>
            )}
          </NavLink>

          <NavLink
            to="/inbox"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
            Inbox
            {notifications.unread_messages_count > 0 && (
              <span className="ml-auto bg-purple-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                {notifications.unread_messages_count}
              </span>
            )}
          </NavLink>

          <NavLink
            to="/sales"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
            </svg>
            Sales
          </NavLink>

          <NavLink
            to="/reports"
            className={({ isActive }) => navLinkClass(isActive)}
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            Reports
          </NavLink>

          {admin && admin.is_superadmin === 1 && (
            <NavLink
              to="/admin-management"
              className={({ isActive }) => navLinkClass(isActive)}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="1.5" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Admin Management
            </NavLink>
          )}
        </nav>
      </div>

      {/* Admin Profile Section with Popup */}
      <div className={`relative px-4 py-4 ${isDarkMode ? 'border-gray-700' : ''} border-t`}>
        <button
          onClick={() => setShowLogoutMenu(!showLogoutMenu)}
          className={`flex items-center gap-3 w-full rounded-lg p-2 transition-colors ${
            isDarkMode ? 'hover:bg-gray-900' : 'hover:bg-gray-50'
          }`}
        >
          <div className={`w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 ${isDarkMode ? 'bg-purple-900' : 'bg-purple-100'}`}>
            <span className={`font-bold ${isDarkMode ? 'text-purple-300' : 'text-purple-700'}`}>{admin?.username[0].toUpperCase()}</span>
          </div>
          <div className="flex-1 min-w-0 text-left">
            <p className={`font-semibold truncate ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>{admin?.username}</p>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{admin?.is_superadmin === 1 ? 'Superadmin' : 'Admin'}</p>
          </div>
          <button
            onClick={(e) => {
              e.stopPropagation();
              toggleDarkMode();
            }}
            className={`p-2 rounded-lg transition-colors flex-shrink-0 ${
              isDarkMode ? 'bg-gray-800 text-yellow-400 hover:bg-gray-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
            aria-label="Toggle Dark Mode"
          >
            {isDarkMode ? (
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" />
              </svg>
            ) : (
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
              </svg>
            )}
          </button>
        </button>

        {/* Logout Popup Menu */}
        {showLogoutMenu && (
          <>
            {/* Backdrop to close menu when clicking outside */}
            <div
              className="fixed inset-0 z-40"
              onClick={() => setShowLogoutMenu(false)}
            />
            <div className={`absolute bottom-full left-4 right-4 mb-2 z-50 rounded-lg shadow-lg border ${
              isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'
            }`}>
              <button
                onClick={handleLogout}
                className={`w-full flex items-center gap-3 px-4 py-3 text-red-500 rounded-lg font-semibold transition-colors ${
                  isDarkMode ? 'hover:bg-gray-900' : 'hover:bg-red-50'
                }`}
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Logout
              </button>
            </div>
          </>
        )}
      </div>
    </aside>
  );
}
