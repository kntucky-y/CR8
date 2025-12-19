import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { type ReactNode } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import { ThemeProvider } from './context/ThemeContext';
import { NotificationsProvider } from './context/NotificationsContext';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Inventory from './pages/Inventory';
import Orders from './pages/Orders';
import Customers from './pages/Customers';
import Artists from './pages/Artists';
import ArtistApplications from './pages/ArtistApplications';
import Inbox from './pages/Inbox';
import Sales from './pages/Sales';
import Reports from './pages/Reports';
import AdminManagement from './pages/AdminManagement';
import Layout from './components/Layout';

function ProtectedRoute({ children }: { children: ReactNode }) {
  const { admin, loading } = useAuth();

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  return admin ? children : <Navigate to="/login" />;
}

function AppRoutes() {
  const { admin } = useAuth();

  return (
    <Routes>
      <Route path="/login" element={admin ? <Navigate to="/dashboard" /> : <Login />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <Layout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" />} />
        <Route path="dashboard" element={<Dashboard />} />
        <Route path="inventory" element={<Inventory />} />
        <Route path="orders" element={<Orders />} />
        <Route path="customers" element={<Customers />} />
        <Route path="artists" element={<Artists />} />
        <Route path="artist_applications" element={<ArtistApplications />} />
        <Route path="inbox" element={<Inbox />} />
        <Route path="sales" element={<Sales />} />
        <Route path="reports" element={<Reports />} />
        <Route path="admin-management" element={<AdminManagement />} />
      </Route>
    </Routes>
  );
}

function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <NotificationsProvider>
          <BrowserRouter>
            <AppRoutes />
          </BrowserRouter>
        </NotificationsProvider>
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;

