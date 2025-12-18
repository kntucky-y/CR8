import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

interface SalesData {
  total_sales: number;
  total_orders: number;
  products_sold: number;
}

const SalesReports = () => {
  const [salesData, setSalesData] = useState<SalesData | null>(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    fetchSalesData();
  }, []);

  const fetchSalesData = async () => {
    try {
      const response = await fetch('/api/artist_sales.php', {
        credentials: 'include'
      });
      const data = await response.json();
      if (data.success) {
        setSalesData(data.data);
      }
    } catch (error) {
      console.error('Error fetching sales data:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-cream py-8">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-4xl font-outfit font-bold text-dark-purple">Sales Reports</h1>
          <button
            onClick={() => navigate('/dashboard')}
            className="px-4 py-2 bg-gray-200 text-dark-purple rounded-lg hover:bg-gray-300"
          >
            Back to Dashboard
          </button>
        </div>

        {loading ? (
          <p>Loading sales data...</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-white rounded-lg shadow-md p-6">
              <h3 className="text-lg font-outfit text-gray-600 mb-2">Total Sales</h3>
              <p className="text-3xl font-bold text-purple">
                ₱{salesData?.total_sales.toFixed(2) || '0.00'}
              </p>
            </div>
            
            <div className="bg-white rounded-lg shadow-md p-6">
              <h3 className="text-lg font-outfit text-gray-600 mb-2">Total Orders</h3>
              <p className="text-3xl font-bold text-purple">
                {salesData?.total_orders || 0}
              </p>
            </div>
            
            <div className="bg-white rounded-lg shadow-md p-6">
              <h3 className="text-lg font-outfit text-gray-600 mb-2">Products Sold</h3>
              <p className="text-3xl font-bold text-purple">
                {salesData?.products_sold || 0}
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default SalesReports;
