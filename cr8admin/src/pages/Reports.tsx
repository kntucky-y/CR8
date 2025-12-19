import { useEffect, useState } from 'react';
import { reportsAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';

export default function Reports() {
  const { isDarkMode } = useTheme();
  const [artists, setArtists] = useState<any[]>([]);
  const [selectedArtist, setSelectedArtist] = useState('all');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchArtists();
  }, []);

  const fetchArtists = async () => {
    try {
      setLoading(true);
      const response = await reportsAPI.getArtists();
      setArtists(response.data.artists);
    } catch (error) {
      console.error('Failed to fetch artists:', error);
    } finally {
      setLoading(false);
    }
  };

  const downloadReport = (type: 'sales' | 'inventory') => {
    const artistParam = type === 'sales' ? `&artist_id=${selectedArtist}` : '';
    window.location.href = `https://cr8admin.dcism.org/api/generate_report.php?type=${type}${artistParam}`;
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading reports...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`p-4 md:p-8 ${isDarkMode ? 'bg-black min-h-screen' : ''}`}>
      <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-6`}>
        <h1 className={`text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-6`}>Reports</h1>
        <p className={`${isDarkMode ? 'text-gray-300' : 'text-gray-600'} mb-4`}>Generate sales and inventory reports for analysis.</p>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className={`${isDarkMode ? 'border-gray-700' : 'border-gray-200'} border rounded-lg p-6`}>
            <h3 className={`text-lg font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-2`}>Sales Report</h3>
            <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm mb-4`}>Generate comprehensive sales reports by date range or artist.</p>
            <select 
              value={selectedArtist}
              onChange={(e) => setSelectedArtist(e.target.value)}
              className={`w-full px-3 py-2 border rounded-md focus:outline-purple-400 mb-3 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}
            >
              <option value="all">All Artists</option>
              {artists.map((artist) => (
                <option key={artist.id} value={artist.id}>{artist.artist_name}</option>
              ))}
            </select>
            <button 
              onClick={() => downloadReport('sales')}
              className="w-full px-4 py-2 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700"
            >
              Download Sales Report (CSV)
            </button>
          </div>

          <div className={`${isDarkMode ? 'border-gray-700' : 'border-gray-200'} border rounded-lg p-6`}>
            <h3 className={`text-lg font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-2`}>Inventory Report</h3>
            <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm mb-4`}>Generate inventory reports to track stock levels.</p>
            <button 
              onClick={() => downloadReport('inventory')}
              className="w-full px-4 py-2 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700"
            >
              Download Inventory Report (CSV)
            </button>
          </div>
        </div>
      </div>
    </section>
  );
}
