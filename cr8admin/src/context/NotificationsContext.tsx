import { createContext, useContext, useState, useEffect } from 'react';
import type { ReactNode } from 'react';
import { notificationsAPI } from '../services/api';

interface Notifications {
  unread_messages_count: number;
  pending_apps_count: number;
  pending_orders_count: number;
}

interface NotificationsContextType {
  notifications: Notifications;
  refreshNotifications: () => void;
}

const NotificationsContext = createContext<NotificationsContextType | undefined>(undefined);

export function NotificationsProvider({ children }: { children: ReactNode }) {
  const [notifications, setNotifications] = useState<Notifications>({
    unread_messages_count: 0,
    pending_apps_count: 0,
    pending_orders_count: 0,
  });

  const fetchNotifications = async () => {
    try {
      const response = await notificationsAPI.getCounts();
      setNotifications(response.data);
    } catch (error) {
      console.error('Failed to fetch notifications:', error);
    }
  };

  useEffect(() => {
    fetchNotifications();
    const interval = setInterval(fetchNotifications, 30000); // Refresh every 30s
    return () => clearInterval(interval);
  }, []);

  return (
    <NotificationsContext.Provider value={{ notifications, refreshNotifications: fetchNotifications }}>
      {children}
    </NotificationsContext.Provider>
  );
}

export function useNotifications() {
  const context = useContext(NotificationsContext);
  if (!context) {
    throw new Error('useNotifications must be used within NotificationsProvider');
  }
  return context;
}
