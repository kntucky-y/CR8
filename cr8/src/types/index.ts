export interface User {
  id: number;
  username: string;
  email: string;
  first_name: string;
  last_name: string;
  address?: string;
  phone?: string;
  role: 'user' | 'artist';
  artist_id?: number;
  artist_name?: string;
  artist_status?: 'pending' | 'active' | 'inactive';
}

export interface Review {
  id: number;
  rating: number;
  comments: string;
  created_at: string;
  user_name: string;
}

export interface Product {
  id: number;
  artist_id: number;
  artist_name: string;
  product_name: string;
  product_description?: string;
  description?: string;
  price: number;
  stock?: number;
  quantity: number;
  category?: string;
  category_name?: string;
  image_url?: string;
  image: string;
  status?: 'available' | 'unavailable';
  is_active?: number;
  created_at?: string;
  average_rating?: number;
  review_count?: number;
  base_variant_name?: string;
  variants?: ProductVariant[];
  reviews?: Review[];
}

export interface ProductVariant {
  id: number | string;
  name: string;
  image: string;
  price: number;
  quantity: number;
}

export interface CartItem {
  id: number;
  product_id: number;
  product_name: string;
  price: number;
  quantity: number;
  image_url: string;
  image?: string;
  artist_name: string;
  stock: number;
}

export interface WishlistItem {
  id: number;
  product_id: number;
  product_name: string;
  price: number;
  image_url: string;
  image?: string;
  artist_name: string;
  stock: number;
  status: string;
}

export interface Order {
  id: number;
  user_id: number;
  total_amount: number;
  payment_method: string;
  delivery_method: string;
  delivery_location?: string;
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  created_at: string;
  items: OrderItem[];
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  product_name: string;
  artist_name: string;
  quantity: number;
  price: number;
  image_url: string;
}

export interface Artist {
  id: number;
  artist_name: string;
  status: 'active' | 'inactive';
}

export interface ArtistApplication {
  id: number;
  user_id: number;
  email: string;
  full_name: string;
  artist_name: string;
  contact_number: string;
  portfolio: string;
  product_desc: string;
  status: 'Unread' | 'Under Review' | 'Approved' | 'Rejected';
  created_at: string;
}

export interface Review {
  id: number;
  user_id: number;
  product_id: number;
  product_name: string;
  artist_name: string;
  rating: number;
  comment: string;
  created_at: string;
}

export interface AuthContextType {
  user: User | null;
  login: (email: string, password: string) => Promise<void>;
  register: (userData: RegisterData) => Promise<void>;
  logout: () => void;
  loading: boolean;
}

export interface RegisterData {
  first_name: string;
  last_name: string;
  username: string;
  email: string;
  password: string;
  address?: string;
}

export interface CartContextType {
  cart: CartItem[];
  wishlist: WishlistItem[];
  addToCart: (productId: number, quantity: number) => Promise<void>;
  updateCartQuantity: (productId: number, quantity: number) => Promise<void>;
  removeFromCart: (productId: number) => Promise<void>;
  addToWishlist: (productId: number) => Promise<void>;
  removeFromWishlist: (productId: number) => Promise<void>;
  moveToCart: (productId: number) => Promise<void>;
  clearCart: () => void;
  loadCart: () => Promise<void>;
  loadWishlist: () => Promise<void>;
}
