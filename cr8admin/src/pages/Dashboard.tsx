import { useEffect, useState } from 'react';
import { dashboardAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';
import { Line } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

interface DashboardData {
  cards: {
    app_count: number;
    unread_count: number;
    sales_total: number;
    top_seller: {artist_name: string; total_revenue: number} | null;
    top_product: {product_name: string; total_sold: number} | null;
  };
  latest_sales: Array<{
    artist_name: string;
    product_name: string;
    sale_total: number;
    created_at: string;
  }>;
  chart_data: Array<{label: string; revenue: number}>;
  artists: Array<{id: number; artist_name: string}>;
}

export default function Dashboard() {
  const { isDarkMode } = useTheme();
  const [data, setData] = useState<DashboardData | null>(null);
  const [artistId, setArtistId] = useState('all');
  const [period, setPeriod] = useState('month');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Fetch on initial load
    fetchDashboardData();
    
    // Set up polling to refresh dashboard every 10 seconds
    const intervalId = setInterval(() => {
      fetchDashboardData();
    }, 10000); // Refresh every 10 seconds
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, []);

  const fetchDashboardData = async () => {
    try {
      const response = await dashboardAPI.getData(artistId, period);
      setData(response.data);
    } catch (error) {
      console.error('Failed to fetch dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading || !data) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading dashboard...</p>
        </div>
      </div>
    );
  }

  const chartData = {
    labels: data.chart_data.map((d) => d.label),
    datasets: [
      {
        label: 'Revenue (₱)',
        data: data.chart_data.map((d) => d.revenue),
        borderColor: 'rgb(126, 34, 206)',
        backgroundColor: 'rgba(126, 34, 206, 0.1)',
        tension: 0.4,
      },
    ],
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        position: 'top' as const,
        labels: {
          color: isDarkMode ? '#d1d5db' : '#374151',
        },
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          color: isDarkMode ? '#9ca3af' : '#6b7280',
        },
        grid: {
          color: isDarkMode ? '#374151' : '#e5e7eb',
        },
      },
      x: {
        ticks: {
          color: isDarkMode ? '#9ca3af' : '#6b7280',
        },
        grid: {
          color: isDarkMode ? '#374151' : '#e5e7eb',
        },
      },
    },
  };

  return (
    <section className={`p-4 md:p-8 ${isDarkMode ? 'bg-black' : ''}`}>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        {/* Revenue Card */}
        <div className={`rounded-xl shadow p-6 flex flex-col justify-between ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h3 className={`font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Total Revenue</h3>
          <p className="text-3xl font-bold text-green-600 mt-2">
            ₱{Number(data.cards.sales_total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
          </p>
        </div>

        {/* Top Selling Artist Card */}
        <div className={`rounded-xl shadow p-6 flex flex-col justify-between ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h3 className={`font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Top Selling Artist</h3>
          <p className="text-2xl font-bold text-purple-700 mt-2 truncate" title={data.cards.top_seller?.artist_name || 'N/A'}>
            {data.cards.top_seller?.artist_name || 'N/A'}
          </p>
          <p className="text-sm text-gray-400">
            ₱{Number(data.cards.top_seller?.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} in sales
          </p>
        </div>

        {/* Top Selling Product Card */}
        <div className={`rounded-xl shadow p-6 flex flex-col justify-between ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h3 className={`font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Top Selling Product</h3>
          <p className="text-2xl font-bold text-blue-600 mt-2 truncate" title={data.cards.top_product?.product_name || 'N/A'}>
            {data.cards.top_product?.product_name || 'N/A'}
          </p>
          <p className="text-sm text-gray-400">{Number(data.cards.top_product?.total_sold || 0).toLocaleString()} units sold</p>
        </div>

        {/* Artist Applications Card */}
        <div className={`rounded-xl shadow p-6 flex flex-col justify-between ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h3 className={`font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Artist Applications</h3>
          <p className="text-3xl font-bold text-yellow-600 mt-2">{data.cards.app_count}</p>
          <p className="text-sm text-gray-400">
            Unread: <span className="font-bold">{data.cards.unread_count}</span>
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 lg:gap-8">
        {/* Revenue Chart */}
        <div className={`lg:col-span-2 rounded-xl shadow p-4 md:p-6 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <div className={`sticky top-0 z-10 ${isDarkMode ? 'bg-gray-900' : 'bg-white'} pb-4 -mx-4 md:-mx-6 px-4 md:px-6 mb-4`}>
            <div className="flex flex-col gap-4">
              <h2 className={`text-lg md:text-xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Revenue Overview</h2>
              <form className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <select
                  value={artistId}
                  onChange={(e) => setArtistId(e.target.value)}
                  className={`w-full sm:w-auto px-3 py-2 border rounded-md text-sm focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : 'bg-gray-100 border-gray-200'}`}
                >
                  <option value="all">All Artists</option>
                  {data.artists.map((artist) => (
                    <option key={artist.id} value={artist.id}>
                      {artist.artist_name}
                    </option>
                  ))}
                </select>
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                  className={`w-full sm:w-auto px-3 py-2 border rounded-md text-sm focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : 'bg-gray-100 border-gray-200'}`}
                >
                  <option value="week">This Week</option>
                  <option value="month">This Month</option>
                  <option value="year">This Year</option>
                </select>
                <button
                  type="button"
                  onClick={fetchDashboardData}
                  className="w-full sm:w-auto px-4 py-2 bg-purple-600 text-white font-semibold rounded-md text-sm hover:bg-purple-700"
                >
                  Apply
                </button>
              </form>
            </div>
          </div>
          <div style={{ height: '300px' }}>
            <Line data={chartData} options={chartOptions} />
          </div>
        </div>

        {/* Latest Sales */}
        <div className={`rounded-xl shadow p-4 md:p-6 overflow-x-auto ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h2 className={`text-lg md:text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Latest Items Sold</h2>
          <ul className="space-y-3">
            {data.latest_sales.length > 0 ? (
              data.latest_sales.map((sale, index) => (
                <li key={index} className={`flex items-center justify-between py-2 border-b last:border-b-0 min-w-0 ${isDarkMode ? 'border-gray-800' : ''}`}>
                  <div className="min-w-0">
                    <p className={`font-semibold text-sm truncate ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`} title={sale.product_name}>
                      {sale.product_name}
                    </p>
                    <p className={`text-xs truncate ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>by {sale.artist_name}</p>
                  </div>
                  <div className="text-right flex-shrink-0">
                    <p className="font-bold text-green-600 text-sm">
                      ₱{Number(sale.sale_total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </p>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{new Date(sale.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</p>
                  </div>
                </li>
              ))
            ) : (
              <li className="text-gray-400 text-sm">No recent sales to display.</li>
            )}
          </ul>
        </div>
      </div>
    </section>
  );
}
