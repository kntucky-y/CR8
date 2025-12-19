import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import MobileNav from './MobileNav';
import { useTheme } from '../context/ThemeContext';

export default function Layout() {
  const { isDarkMode } = useTheme();

  return (
    <div className={`min-h-screen flex flex-col md:flex-row ${isDarkMode ? 'dark bg-black' : 'bg-gray-100'}`}>
      <Sidebar />
      <MobileNav />
      <main className={`flex-1 flex flex-col min-h-screen pt-16 md:pt-0 ${isDarkMode ? 'bg-black' : ''}`}>
        <Outlet />
      </main>
    </div>
  );
}
