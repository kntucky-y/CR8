import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { User, AuthContextType, RegisterData } from '../types'
import api from '../services/api'

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    checkAuth()
  }, [])

  // Poll for user updates every 5 seconds to detect role changes
  useEffect(() => {
    if (!user) return

    const interval = setInterval(() => {
      checkAuthSilently()
    }, 5000) // Check every 5 seconds

    return () => clearInterval(interval)
  }, [user])

  const checkAuth = async () => {
    try {
      const response = await api.get('/auth?action=check')
      if (response.data.success) {
        setUser(response.data.user)
      }
    } catch (error) {
      console.error('Auth check failed:', error)
    } finally {
      setLoading(false)
    }
  }

  const checkAuthSilently = async () => {
    try {
      const response = await api.get('/auth?action=check')
      if (response.data.success) {
        // Only update if role actually changed to prevent unnecessary re-renders
        const newUser = response.data.user
        if (user && newUser.role !== user.role) {
          setUser(newUser)
        }
      }
    } catch (error) {
      console.error('Silent auth check failed:', error)
    }
  }

  const login = async (email: string, password: string) => {
    const response = await api.post('/auth?action=login', { email, password })
    if (response.data.success) {
      setUser(response.data.user)
    } else {
      throw new Error(response.data.message || 'Login failed')
    }
  }

  const register = async (userData: RegisterData) => {
    const response = await api.post('/auth?action=register', userData)
    if (response.data.success) {
      setUser(response.data.user)
    } else {
      throw new Error(response.data.message || 'Registration failed')
    }
  }

  const logout = async () => {
    try {
      await api.post('/auth?action=logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      setUser(null)
    }
  }

  return (
    <AuthContext.Provider value={{ user, login, register, logout, loading }}>
      {children}
    </AuthContext.Provider>
  )
}
