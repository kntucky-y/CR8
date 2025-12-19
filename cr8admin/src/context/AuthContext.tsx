import { createContext, useContext, useState, useEffect, useRef, type ReactNode } from 'react';
import { authAPI } from '../services/api';

interface Admin {
  id: number;
  username: string;
  is_superadmin: number;
}

interface AuthContextType {
  admin: Admin | null;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  loading: boolean;
  logoutMessage: string | null;
  clearLogoutMessage: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [admin, setAdmin] = useState<Admin | null>(null);
  const [loading, setLoading] = useState(true);
  const [logoutMessage, setLogoutMessage] = useState<string | null>(null);
  const IDLE_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds
  const idleTimerRef = useRef<number | null>(null);

  useEffect(() => {
    checkAuth();
  }, []);

  // Set up idle timeout tracker
  useEffect(() => {
    if (!admin) return; // Only track when logged in

    const resetIdleTimer = () => {
      // Clear existing timer
      if (idleTimerRef.current) {
        clearTimeout(idleTimerRef.current);
      }

      // Set new timer
      idleTimerRef.current = setTimeout(() => {
        handleIdleLogout();
      }, IDLE_TIMEOUT);
    };

    const handleIdleLogout = async () => {
      setLogoutMessage('You have been logged out due to inactivity.');
      await authAPI.logout();
      setAdmin(null);
    };

    // Events that indicate user activity
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

    // Add event listeners
    events.forEach(event => {
      document.addEventListener(event, resetIdleTimer);
    });

    // Start initial timer
    resetIdleTimer();

    // Cleanup
    return () => {
      if (idleTimerRef.current) {
        clearTimeout(idleTimerRef.current);
      }
      events.forEach(event => {
        document.removeEventListener(event, resetIdleTimer);
      });
    };
  }, [admin]);

  const checkAuth = async () => {
    try {
      const response = await authAPI.checkSession();
      if (response.data.authenticated) {
        setAdmin(response.data.admin);
      } else {
        setAdmin(null);
      }
    } catch (error) {
      setAdmin(null);
    } finally {
      setLoading(false);
    }
  };

  const login = async (username: string, password: string) => {
    try {
      const response = await authAPI.login(username, password);
      console.log('Login response:', response.data);
      if (response.data.success) {
        setAdmin(response.data.admin);
      } else {
        throw new Error(response.data.error || 'Login failed');
      }
    } catch (error: any) {
      console.error('Login API error:', error);
      if (error.response) {
        // Server responded with error
        throw new Error(error.response.data?.error || 'Server error occurred');
      } else if (error.request) {
        // Request made but no response
        throw new Error('Cannot connect to server. Please ensure XAMPP is running.');
      } else {
        // Something else happened
        throw new Error(error.message || 'Login failed');
      }
    }
  };

  const logout = async () => {
    await authAPI.logout();
    setAdmin(null);
  };

  const clearLogoutMessage = () => {
    setLogoutMessage(null);
  };

  return (
    <AuthContext.Provider value={{ admin, login, logout, loading, logoutMessage, clearLogoutMessage }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
