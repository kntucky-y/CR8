import { useEffect, useState, useRef } from 'react';
import { inventoryAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';

interface Product {
  id: number;
  product_name: string;
  base_variant_name: string;
  image: string;
  quantity: number;
  is_active: number;
  artist_name: string;
}

export default function Inventory() {
  const { isDarkMode } = useTheme();
  const [products, setProducts] = useState<Product[]>([]);
  const [artists, setArtists] = useState<any[]>([]);
  const [artistId, setArtistId] = useState('all');
  const [stock, setStock] = useState('all');
  const [status, setStatus] = useState('active');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const shouldRefocus = useRef(false);
  const [showDeactivateModal, setShowDeactivateModal] = useState(false);
  const [selectedProductId, setSelectedProductId] = useState<number | null>(null);
  const [deactivationReason, setDeactivationReason] = useState('');

  useEffect(() => {
    fetchInventory();
    
    // Set up polling to refresh inventory every 3 seconds
    const intervalId = setInterval(() => {
      fetchInventory();
    }, 3000);
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, [artistId, stock, status]);

  // Auto-search when typing (debounced)
  useEffect(() => {
    shouldRefocus.current = document.activeElement === searchInputRef.current;
    const timer = setTimeout(() => {
      fetchInventory();
    }, 150); // Wait 150ms after user stops typing
    
    return () => clearTimeout(timer);
  }, [search]);

  // Refocus search input after re-render
  useEffect(() => {
    if (shouldRefocus.current && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [products]);

  const fetchInventory = async () => {
    try {
      const response = await inventoryAPI.getInventory({ artist_id: artistId, stock, status, search });
      setProducts(response.data.products);
      setArtists(response.data.artists);
    } catch (error) {
      console.error('Error fetching inventory:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateQuantity = async (productId: number, newQuantity: number) => {
    if (newQuantity < 0) return;
    try {
      await inventoryAPI.updateProduct(productId, { quantity: newQuantity });
      fetchInventory();
    } catch (error) {
      console.error('Error updating quantity:', error);
      alert('Failed to update quantity');
    }
  };

  const handleToggleStatus = async (productId: number, currentStatus: number) => {
    if (currentStatus === 1) {
      // If product is active, show deactivation modal
      setSelectedProductId(productId);
      setShowDeactivateModal(true);
    } else {
      // If product is inactive, activate it
      try {
        await inventoryAPI.updateProduct(productId, { is_active: 1, deactivation_reason: '' });
        fetchInventory();
      } catch (error) {
        console.error('Error updating status:', error);
        alert('Failed to update status');
      }
    }
  };

  const handleDeactivateSubmit = async () => {
    if (!deactivationReason.trim()) {
      alert('Please provide a reason for deactivation');
      return;
    }

    try {
      await inventoryAPI.updateProduct(selectedProductId!, { is_active: 0, deactivation_reason: deactivationReason });
      setShowDeactivateModal(false);
      setDeactivationReason('');
      setSelectedProductId(null);
      fetchInventory();
    } catch (error) {
      console.error('Error deactivating product:', error);
      alert('Failed to deactivate product');
    }
  };

  const handleFilter = () => {
    fetchInventory();
  };

  const getStockBadge = (quantity: number) => {
    if (quantity <= 0) return <span className="px-2 py-1 bg-red-100 text-red-700 text-xs font-bold rounded">Out of Stock</span>;
    if (quantity <= 10) return <span className="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded">Low Stock</span>;
    return <span className="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">In Stock</span>;
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading inventory...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`p-4 md:p-8 ${isDarkMode ? 'bg-black' : ''}`}>
      <div className={`sticky top-0 z-10 ${isDarkMode ? 'bg-black' : 'bg-gray-50'} pb-4 -mx-4 md:-mx-8 px-4 md:px-8 mb-6`}>
        <div className={`rounded-xl shadow p-6 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h1 className={`text-2xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Inventory Management</h1>
          
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
            <select value={artistId} onChange={(e) => setArtistId(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
              <option value="all">All Artists</option>
              {artists.map((artist) => (
                <option key={artist.id} value={artist.id}>{artist.artist_name}</option>
              ))}
            </select>

            <select value={stock} onChange={(e) => setStock(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
              <option value="all">All Stock Levels</option>
              <option value="instock">In Stock</option>
              <option value="lowstock">Low Stock</option>
              <option value="outofstock">Out of Stock</option>
            </select>

            <select value={status} onChange={(e) => setStatus(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
              <option value="active">Active Products</option>
              <option value="inactive">Inactive Products</option>
              <option value="all">All Products</option>
            </select>

            <input
              ref={searchInputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search products..."
              className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
          </div>

          <button onClick={handleFilter} className="px-6 py-2 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700">
            Apply Filters
          </button>
        </div>
      </div>

      <div className={`rounded-xl shadow overflow-x-auto ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
        <table className="w-full">
          <thead className={`border-b ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
            <tr>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Product</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Artist</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Variant</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Quantity</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Stock Status</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Status</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Actions</th>
            </tr>
          </thead>
          <tbody className={`divide-y ${isDarkMode ? 'divide-gray-800' : 'divide-gray-200'}`}>
            {products.length > 0 ? (
              products.map((product) => (
                <tr key={product.id} className={isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      <img src={`https://cr8.dcism.org/${product.image}`} alt={product.product_name} className="w-12 h-12 object-cover rounded" onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }} />
                      <span className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{product.product_name}</span>
                    </div>
                  </td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{product.artist_name}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{product.base_variant_name}</td>
                  <td className="px-6 py-4">
                    <input
                      type="number"
                      min="0"
                      value={product.quantity}
                      onChange={(e) => handleUpdateQuantity(product.id, parseInt(e.target.value) || 0)}
                      className={`w-20 px-2 py-1 border rounded focus:outline-purple-400 font-bold ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : 'bg-white text-gray-700 border-gray-300'}`}
                    />
                  </td>
                  <td className="px-6 py-4">{getStockBadge(product.quantity)}</td>
                  <td className="px-6 py-4">
                    {product.is_active === 1 ? (
                      <span className="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">Active</span>
                    ) : (
                      <span className="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">Inactive</span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <button
                      onClick={() => handleToggleStatus(product.id, product.is_active)}
                      className={`px-3 py-1 text-xs font-semibold rounded ${product.is_active === 1 ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'}`}
                    >
                      {product.is_active === 1 ? 'Deactivate' : 'Activate'}
                    </button>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={7} className={`px-6 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  No products found
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      
      {/* Deactivation Modal */}
      {showDeactivateModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow-2xl max-w-md w-full p-6`}>
            <h2 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Deactivate Product</h2>
            <p className={`mb-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Please provide a reason for deactivating this product. The artist will be able to see this reason.</p>
            
            <div className="mb-4">
              <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-700'}`}>Reason for Deactivation</label>
              <select
                value={deactivationReason}
                onChange={(e) => setDeactivationReason(e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : 'border-gray-300'}`}
              >
                <option value="">Select a reason...</option>
                <option value="Violates community guidelines">Violates community guidelines</option>
                <option value="Inappropriate content">Inappropriate content</option>
                <option value="Copyright infringement">Copyright infringement</option>
                <option value="Poor quality">Poor quality</option>
                <option value="Duplicate product">Duplicate product</option>
                <option value="Out of stock - Artist request">Out of stock - Artist request</option>
                <option value="Other policy violation">Other policy violation</option>
              </select>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => {
                  setShowDeactivateModal(false);
                  setDeactivationReason('');
                  setSelectedProductId(null);
                }}
                className={`flex-1 px-4 py-2 rounded-md font-semibold ${isDarkMode ? 'bg-gray-800 text-white hover:bg-gray-700' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'}`}
              >
                Cancel
              </button>
              <button
                onClick={handleDeactivateSubmit}
                className="flex-1 px-4 py-2 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700"
              >
                Deactivate
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
