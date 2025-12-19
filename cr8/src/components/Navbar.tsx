import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useState, useEffect } from 'react'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import CartSidebar from './CartSidebar'
import NotificationSidebar from './NotificationSidebar'
import WishlistSidebar from './WishlistSidebar'
import api from '../services/api'

interface NavbarProps {
  showSearch?: boolean
  hideWishlist?: boolean
  hideNotifications?: boolean
}

const Navbar = ({ showSearch = false, hideWishlist = false, hideNotifications = false }: NavbarProps) => {
  const { user, logout } = useAuth()
  const { cart, wishlist } = useCart()
  const navigate = useNavigate()
  const location = useLocation()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [showCartSidebar, setShowCartSidebar] = useState(false)
  const [showWishlistSidebar, setShowWishlistSidebar] = useState(false)
  const [showNotifications, setShowNotifications] = useState(false)
  const [unreadCount, setUnreadCount] = useState(0)

  useEffect(() => {
    if (user) {
      loadUnreadCount()
      const interval = setInterval(loadUnreadCount, 30000) // Check every 30 seconds
      return () => clearInterval(interval)
    }
  }, [user])

  const loadUnreadCount = async () => {
    try {
      const response = await api.get('/notifications.php?action=unread-count')
      if (response.data.success) {
        setUnreadCount(response.data.count)
      }
    } catch (error) {
      console.error('Failed to load unread count:', error)
    }
  }

  // Auto-search with debounce (only when on shop page)
  useEffect(() => {
    if (!showSearch || location.pathname !== '/shop') return

    const timer = setTimeout(() => {
      const params = new URLSearchParams(location.search)
      if (searchQuery.trim()) {
        params.set('search', searchQuery)
      } else {
        params.delete('search')
      }
      navigate(`/shop?${params.toString()}`, { replace: true })
    }, 300) // Wait 300ms after user stops typing

    return () => clearTimeout(timer)
  }, [searchQuery, showSearch, location.pathname])

  // Initialize search query from URL params
  useEffect(() => {
    if (showSearch && location.pathname === '/shop') {
      const params = new URLSearchParams(location.search)
      const urlSearch = params.get('search')
      if (urlSearch && urlSearch !== searchQuery) {
        setSearchQuery(urlSearch)
      }
    }
  }, [location.search, showSearch])

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    // Search is already handled by useEffect
  }

  return (
    <>
      {!showSearch ? (
        // Simple header for home, contact, etc.
        <header className="relative py-6 flex items-center justify-between gap-4">
          <div className="logo flex-shrink-0">
            <Link to="/">
              <img src="/img/cr8-logo.png" alt="CR8 Logo" className="w-16 md:w-20" />
            </Link>
          </div>
          
          <nav className="hidden md:flex">
            <ul className="flex items-center justify-center py-3 gap-8 md:gap-12 lg:gap-24">
              {user ? (
                <>
                  <li>
                    <Link to="/dashboard" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                      ACCOUNT
                    </Link>
                  </li>
                </>
              ) : (
                <li>
                  <Link to="/login" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                    ACCOUNT
                  </Link>
                </li>
              )}
              <li>
                <Link to="/shop" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                  SHOP
                </Link>
              </li>
              <li>
                <Link to="/contact" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                  CONTACT US
                </Link>
              </li>
            </ul>
          </nav>

          <div className="flex-shrink-0 flex justify-end items-center gap-2">
            {user && (
              <>
                {!hideNotifications && (
                  <button onClick={() => setShowNotifications(true)} className="relative p-2 hover:bg-purple hover:bg-opacity-20 rounded-full transition-colors">
                    <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    {unreadCount > 0 && (
                      <span className="absolute -top-1 -right-1 bg-light-purple text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                        {unreadCount > 9 ? '9+' : unreadCount}
                      </span>
                    )}
                  </button>
                )}
                {!hideWishlist && (
                  <button onClick={() => setShowWishlistSidebar(true)} className="relative p-2 hover:bg-purple hover:bg-opacity-20 rounded-full transition-colors">
                    <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.5l1.318-1.182a4.5 4.5 0 116.364 6.364L12 21l-7.682-7.318a4.5 4.5 0 010-6.364z" />
                    </svg>
                    {wishlist.length > 0 && (
                      <span className="absolute -top-1 -right-1 bg-light-purple text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                        {wishlist.length}
                      </span>
                    )}
                  </button>
                )}
                <button onClick={() => setShowCartSidebar(true)} className="relative p-2 hover:bg-purple hover:bg-opacity-20 rounded-full transition-colors">
                  <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                  {cart.length > 0 && (
                    <span className="absolute -top-1 -right-1 bg-light-purple text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                      {cart.length}
                    </span>
                  )}
                </button>
              </>
            )}
            <div className="md:hidden">
              <button
                onClick={() => setMobileMenuOpen(true)}
                className="focus:outline-none p-2"
              >
                <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>
            </div>
          </div>
        </header>
      ) : (
        // Shop header with search and cart
        <>
          <header className="relative py-6 flex flex-wrap items-center justify-between gap-4">
            <div className="logo flex-shrink-0">
              <Link to="/">
                <img src="/img/cr8-logo.png" alt="CR8 Logo" className="w-16 md:w-20" />
              </Link>
            </div>
            
            <div className="w-full md:w-auto order-3 md:order-2 flex-grow">
              <form onSubmit={handleSearch} className="relative">
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search products..."
                  className="w-full py-2 px-4 pr-10 rounded-full border-2 border-dark-purple focus:outline-none focus:ring-2 focus:ring-light-purple"
                />
                <svg className="h-5 w-5 absolute top-1/2 right-4 transform -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </form>
            </div>

            <div className="flex items-center space-x-2 sm:space-x-4 order-2 md:order-3">
              {user && (
                <>
                  {!hideNotifications && (
                    <button onClick={() => setShowNotifications(true)} className="relative p-2 hover:bg-purple hover:bg-opacity-20 rounded-full transition-colors">
                      <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                      </svg>
                      {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 bg-light-purple text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                          {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                      )}
                    </button>
                  )}
                  {!hideWishlist && (
                    <button onClick={() => setShowWishlistSidebar(true)} className="relative p-2 hover:bg-purple hover:bg-opacity-20 rounded-full transition-colors">
                      <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.5l1.318-1.182a4.5 4.5 0 116.364 6.364L12 21l-7.682-7.318a4.5 4.5 0 010-6.364z" />
                      </svg>
                      {wishlist.length > 0 && (
                        <span className="absolute -top-1 -right-1 bg-light-purple text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                          {wishlist.length}
                        </span>
                      )}
                    </button>
                  )}
                  <button onClick={() => setShowCartSidebar(true)} className="relative p-2 hover:bg-purple hover:bg-opacity-20 rounded-full transition-colors">
                    <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    {cart.length > 0 && (
                      <span className="absolute -top-1 -right-1 bg-light-purple text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">
                        {cart.length}
                      </span>
                    )}
                  </button>
                </>
              )}
              
              <div className="md:hidden">
                <button
                  onClick={() => setMobileMenuOpen(true)}
                  className="focus:outline-none p-2"
                >
                  <svg className="h-6 w-6 text-dark-purple" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                </button>
              </div>
            </div>
          </header>

          <nav className="mt-4 md:mt-0 w-full">
            <div className="hidden md:flex max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
              <ul className="flex flex-wrap justify-center w-full py-3 gap-8 md:gap-12 lg:gap-24">
                {user ? (
                  <>
                    <li>
                      <Link to="/dashboard" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                        ACCOUNT
                      </Link>
                    </li>
                  </>
                ) : (
                  <li>
                    <Link to="/login" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                      ACCOUNT
                    </Link>
                  </li>
                )}
                <li>
                  <Link to="/shop" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                    SHOP
                  </Link>
                </li>
                <li>
                  <Link to="/contact" className="font-lilita text-dark-purple hover:text-purple transition-colors">
                    CONTACT US
                  </Link>
                </li>
              </ul>
            </div>
          </nav>
        </>
      )}

      {/* Mobile Menu */}
      <div
        className={`fixed inset-0 bg-dark-purple z-[9999] flex flex-col items-center justify-center transform transition-transform duration-300 ease-in-out md:hidden ${
          mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <button
          onClick={() => setMobileMenuOpen(false)}
          className="absolute top-6 right-6 text-white p-2"
        >
          <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <ul className="flex flex-col gap-8 text-center">
          {user ? (
            <>
              <li>
                <Link
                  to="/dashboard"
                  onClick={() => setMobileMenuOpen(false)}
                  className="font-lilita text-2xl text-white hover:text-light-purple"
                >
                  ACCOUNT
                </Link>
              </li>
            </>
          ) : (
            <li>
              <Link
                to="/login"
                onClick={() => setMobileMenuOpen(false)}
                className="font-lilita text-2xl text-white hover:text-light-purple"
              >
                ACCOUNT
              </Link>
            </li>
          )}
          <li>
            <Link
              to="/shop"
              onClick={() => setMobileMenuOpen(false)}
              className="font-lilita text-2xl text-white hover:text-light-purple"
            >
              SHOP
            </Link>
          </li>
          <li>
            <Link
              to="/contact"
              onClick={() => setMobileMenuOpen(false)}
              className="font-lilita text-2xl text-white hover:text-light-purple"
            >
              CONTACT US
            </Link>
          </li>
          {user && (
            <li>
              <button
                onClick={() => {
                  logout()
                  setMobileMenuOpen(false)
                }}
                className="font-lilita text-xl text-red-400 hover:text-red-300 pt-4"
              >
                LOGOUT
              </button>
            </li>
          )}
        </ul>
      </div>

      {/* Cart Sidebar */}
      <CartSidebar isOpen={showCartSidebar} onClose={() => setShowCartSidebar(false)} />
      
      {/* Wishlist Sidebar */}
      <WishlistSidebar isOpen={showWishlistSidebar} onClose={() => setShowWishlistSidebar(false)} />
      
      {/* Notification Sidebar */}
      <NotificationSidebar 
        isOpen={showNotifications} 
        onClose={() => setShowNotifications(false)}
        onNotificationRead={loadUnreadCount}
      />
    </>
  )
}

export default Navbar
