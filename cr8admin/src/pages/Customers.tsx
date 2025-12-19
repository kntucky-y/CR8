import { useEffect, useState, useRef } from 'react';
import { customersAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';

interface Customer {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  created_at: string;
  role: string;
}

export default function Customers() {
  const { isDarkMode } = useTheme();
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const shouldRefocus = useRef(false);

  useEffect(() => {
    fetchCustomers();
    
    // Set up polling to refresh customers every 15 seconds
    const intervalId = setInterval(() => {
      fetchCustomers();
    }, 15000); // Refresh every 15 seconds
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, [filter, search]); // Re-create interval when filter or search changes

  // Refocus search input after re-render
  useEffect(() => {
    if (shouldRefocus.current && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [customers]);

  const fetchCustomers = async () => {
    try {
      const response = await customersAPI.getCustomers(filter, search);
      setCustomers(response.data.customers);
    } catch (error) {
      console.error('Failed to fetch customers:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading customers...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`h-screen flex flex-col ${isDarkMode ? 'bg-black' : 'bg-gray-50'}`}>
      <div className={`${isDarkMode ? 'bg-black' : 'bg-gray-50'} p-4 md:p-8 pb-4`}>
        <div className={`rounded-xl shadow p-6 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h1 className={`text-2xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Customers</h1>
          
          <div className="flex flex-col sm:flex-row gap-4">
            <input
              ref={searchInputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by name or email..."
              className={`flex-1 px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
            
            <select value={filter} onChange={(e) => setFilter(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
              <option value="all">All Customers</option>
              <option value="has_orders">With Orders</option>
              <option value="no_orders">No Orders</option>
            </select>
          </div>
        </div>
      </div>

      <div className={`mx-4 md:mx-8 mb-4 md:mb-8 rounded-xl shadow flex-1 overflow-hidden flex flex-col ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
        <div className="overflow-auto flex-1">
        <table className="w-full">
          <thead className={`border-b ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
            <tr>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Name</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Email</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Role</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Joined</th>
            </tr>
          </thead>
          <tbody className={`divide-y ${isDarkMode ? 'divide-gray-800' : 'divide-gray-200'}`}>
            {customers.length > 0 ? (
              customers.map((customer) => (
                <tr key={customer.id} className={isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}>
                  <td className={`px-6 py-4 font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{customer.first_name} {customer.last_name}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{customer.email}</td>
                  <td className="px-6 py-4">
                    <span className={`px-2 py-1 text-xs font-bold rounded ${customer.role === 'artist' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                      {customer.role}
                    </span>
                  </td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{new Date(customer.created_at).toLocaleDateString()}</td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={4} className={`px-6 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  No customers found
                </td>
              </tr>
            )}
          </tbody>
        </table>
        </div>
      </div>
    </section>
  );
}
