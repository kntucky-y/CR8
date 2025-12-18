import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { CartItem, WishlistItem, CartContextType } from '../types'
import { useAuth } from './AuthContext'
import api from '../services/api'

const CartContext = createContext<CartContextType | undefined>(undefined)

export const useCart = () => {
  const context = useContext(CartContext)
  if (!context) {
    throw new Error('useCart must be used within a CartProvider')
  }
  return context
}

export const CartProvider = ({ children }: { children: ReactNode }) => {
  const [cart, setCart] = useState<CartItem[]>([])
  const [wishlist, setWishlist] = useState<WishlistItem[]>([])
  const { user } = useAuth()

  useEffect(() => {
    if (user) {
      loadCart()
      loadWishlist()
    } else {
      setCart([])
      setWishlist([])
    }
  }, [user])

  const loadCart = async () => {
    try {
      const response = await api.get('/cart?action=load')
      if (response.data.success) {
        setCart(response.data.cart)
      }
    } catch (error) {
      console.error('Failed to load cart:', error)
    }
  }

  const loadWishlist = async () => {
    try {
      const response = await api.get('/wishlist?action=load')
      if (response.data.success) {
        setWishlist(response.data.wishlist)
      }
    } catch (error) {
      console.error('Failed to load wishlist:', error)
    }
  }

  const addToCart = async (productId: number, quantity: number) => {
    try {
      const response = await api.post('/cart?action=add', { product_id: productId, quantity })
      if (response.data.success) {
        await loadCart()
      }
    } catch (error) {
      throw error
    }
  }

  const updateCartQuantity = async (productId: number, quantity: number) => {
    try {
      const response = await api.post('/cart?action=update', { product_id: productId, quantity })
      if (response.data.success) {
        await loadCart()
      }
    } catch (error) {
      throw error
    }
  }

  const removeFromCart = async (productId: number) => {
    try {
      const response = await api.post('/cart?action=remove', { product_id: productId })
      if (response.data.success) {
        await loadCart()
      }
    } catch (error) {
      throw error
    }
  }

  const addToWishlist = async (productId: number) => {
    try {
      const response = await api.post('/wishlist?action=add', { product_id: productId })
      if (response.data.success) {
        await loadWishlist()
      }
    } catch (error) {
      throw error
    }
  }

  const removeFromWishlist = async (productId: number) => {
    try {
      const response = await api.post('/wishlist?action=remove', { product_id: productId })
      if (response.data.success) {
        await loadWishlist()
      }
    } catch (error) {
      throw error
    }
  }

  const moveToCart = async (productId: number) => {
    try {
      await addToCart(productId, 1)
      await removeFromWishlist(productId)
    } catch (error) {
      throw error
    }
  }

  const clearCart = () => {
    setCart([])
  }

  return (
    <CartContext.Provider value={{
      cart,
      wishlist,
      addToCart,
      updateCartQuantity,
      removeFromCart,
      addToWishlist,
      removeFromWishlist,
      moveToCart,
      clearCart,
      loadCart,
      loadWishlist
    }}>
      {children}
    </CartContext.Provider>
  )
}
