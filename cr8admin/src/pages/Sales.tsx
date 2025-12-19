import { useEffect, useState } from 'react';
import { salesAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';

interface SalesProduct {
  product_name: string;
  artist_name: string;
  variant_name: string;
  item_image: string;
  item_price: number;
  total_sold: number;
  total_revenue: number;
}

export default function Sales() {
  const { isDarkMode } = useTheme();
  const [products, setProducts] = useState<SalesProduct[]>([]);
  const [artists, setArtists] = useState<any[]>([]);
  const [artistCard, setArtistCard] = useState<any>(null);
  const [artistId, setArtistId] = useState('all');
  const [sort, setSort] = useState('revenue_desc');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSalesData();
    
    // Set up polling to refresh sales every 15 seconds
    const intervalId = setInterval(() => {
      fetchSalesData();
    }, 15000); // Refresh every 15 seconds
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, [artistId, sort]); // Re-create interval when filters change

  const fetchSalesData = async () => {
    try {
      const response = await salesAPI.getSalesData(artistId, sort);
      setProducts(response.data.products);
      setArtists(response.data.artists);
      setArtistCard(response.data.artist_card_data);
    } catch (error) {
      console.error('Failed to fetch sales data:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading sales data...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`p-4 md:p-8 ${isDarkMode ? 'bg-black min-h-screen' : ''}`}>
      {/* Artist Card */}
      <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-6 mb-6`}>
        <h3 className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} font-semibold`}>
          {artistId !== 'all' ? 'Artist Sales (Completed)' : 'Top Artist (Completed Sales)'}
        </h3>
        <p className="text-2xl font-bold text-purple-700 mt-2">{artistCard?.artist_name || 'N/A'}</p>
        <p className={`text-sm ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
          ₱{Number(artistCard?.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2})} in sales
        </p>
      </div>

      {/* Filters */}
      <div className={`sticky top-0 z-10 ${isDarkMode ? 'bg-black' : 'bg-gray-50'} pb-4 -mx-4 md:-mx-8 px-4 md:px-8 mb-6`}>
        <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-4 md:p-6`}>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
          <select value={artistId} onChange={(e) => setArtistId(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
            <option value="all">All Artists</option>
            {artists.map((artist) => (
              <option key={artist.id} value={artist.id}>{artist.artist_name}</option>
            ))}
          </select>

          <select value={sort} onChange={(e) => setSort(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
            <option value="revenue_desc">Revenue (High to Low)</option>
            <option value="revenue_asc">Revenue (Low to High)</option>
            <option value="sold_desc">Units Sold (High to Low)</option>
            <option value="sold_asc">Units Sold (Low to High)</option>
          </select>

          <button onClick={fetchSalesData} className="w-full sm:w-auto sm:col-span-2 md:col-span-1 px-6 py-2 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700">
            Apply Filters
          </button>
          </div>
        </div>
      </div>

      {/* Sales Table */}
      <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow overflow-x-auto`}>
        <table className="w-full">
          <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
            <tr>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Product</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Artist</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Variant</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Price</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Units Sold</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Total Revenue</th>
            </tr>
          </thead>
          <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
            {products.length > 0 ? (
              products.map((product, index) => (
                <tr key={index} className={isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      <img src={`https://cr8.dcism.org/${product.item_image}`} alt={product.product_name} className="w-12 h-12 object-cover rounded" onError={(e) => { (e.target as HTMLImageElement).src = '/img/avatar-placeholder.png'; }} />
                      <span className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{product.product_name}</span>
                    </div>
                  </td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{product.artist_name}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{product.variant_name}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>₱{Number(product.item_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                  <td className={`px-6 py-4 font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{product.total_sold}</td>
                  <td className="px-6 py-4 font-bold text-green-600">₱{Number(product.total_revenue).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={6} className={`px-6 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  No sales data found
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}
