import { useEffect, useState, useRef } from 'react';
import { artistApplicationsAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';
import { useNotifications } from '../context/NotificationsContext';

interface Application {
  id: number;
  full_name: string;
  product_desc: string;
  submitted_at: string;
  status: string;
  is_archived: number;
}

export default function ArtistApplications() {
  console.log('ArtistApplications component mounting...');
  const { isDarkMode } = useTheme();
  const { refreshNotifications } = useNotifications();
  const [applications, setApplications] = useState<Application[]>([]);
  const [selectedApp, setSelectedApp] = useState<any>(null);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('active');
  const [loading, setLoading] = useState(true);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const shouldRefocus = useRef(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkLoading, setBulkLoading] = useState(false);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [rejectionReason, setRejectionReason] = useState('');
  const [rejectingAppId, setRejectingAppId] = useState<number | null>(null);
  
  console.log('Component state:', { applications, loading, filter, search });

  useEffect(() => {
    fetchApplications();
    
    // Set up polling to refresh applications every 10 seconds
    const intervalId = setInterval(() => {
      fetchApplications();
    }, 10000); // Refresh every 10 seconds
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, []);

  // Auto-search when typing (debounced)
  useEffect(() => {
    shouldRefocus.current = document.activeElement === searchInputRef.current;
    const timer = setTimeout(() => {
      fetchApplications();
    }, 50);
    return () => clearTimeout(timer);
  }, [search, filter]);

  // Refocus search input after re-render
  useEffect(() => {
    if (shouldRefocus.current && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [applications]);

  const fetchApplications = async () => {
    try {
      console.log('Fetching applications with filter:', filter, 'search:', search);
      const response = await artistApplicationsAPI.getApplications(search, filter);
      console.log('API Response:', response);
      console.log('Applications data:', response.data);
      setApplications(response.data.applications || []);
    } catch (error: any) {
      console.error('Failed to fetch applications:', error);
      console.error('Error response:', error.response);
      console.error('Error message:', error.message);
      setApplications([]); // Set empty array on error
      // Show error to user
      alert(`Error loading applications: ${error.response?.data?.error || error.message || 'Unknown error'}`);
    } finally {
      setLoading(false);
    }
  };

  const viewApplication = async (id: number) => {
    try {
      console.log('Fetching application details for ID:', id);
      const response = await artistApplicationsAPI.getApplicationDetails(id);
      console.log('Application details response:', response);
      setSelectedApp(response.data.application);
      // Refresh list to update status
      fetchApplications();
    } catch (error: any) {
      console.error('Failed to fetch application details:', error);
      console.error('Error response:', error.response);
      alert(`Error loading application details: ${error.response?.data?.error || error.message || 'Unknown error'}`);
    }
  };

  const handleStatusUpdate = async (id: number, status: string) => {
    if (status === 'rejected') {
      // Show rejection reason modal
      setRejectingAppId(id);
      setShowRejectModal(true);
      return;
    }
    
    if (!confirm(`Are you sure you want to ${status} this application?`)) return;
    
    try {
      await artistApplicationsAPI.updateStatus(id, status);
      alert(`Application ${status} successfully!`);
      setSelectedApp(null); // Clear selection immediately
      await fetchApplications(); // Refresh list
      refreshNotifications(); // Refresh notification counts immediately
    } catch (error) {
      console.error('Failed to update status:', error);
      alert('Failed to update application status');
    }
  };

  const handleRejectWithReason = async () => {
    if (!rejectionReason.trim()) {
      alert('Please provide a reason for rejection');
      return;
    }

    if (!rejectingAppId) return;

    try {
      await artistApplicationsAPI.updateStatus(rejectingAppId, 'rejected', rejectionReason);
      alert('Application rejected successfully!');
      setShowRejectModal(false);
      setRejectionReason('');
      setRejectingAppId(null);
      setSelectedApp(null);
      await fetchApplications();
      refreshNotifications();
    } catch (error) {
      console.error('Failed to reject application:', error);
      alert('Failed to reject application');
    }
  };

  const handleRestore = async (id: number) => {
    if (!confirm('Restore this application from archive?')) return;
    
    try {
      await artistApplicationsAPI.restoreApplication(id);
      alert('Application restored successfully!');
      setSelectedApp(null); // Clear selection
      await fetchApplications(); // Refresh list
      refreshNotifications(); // Refresh notification counts immediately
    } catch (error: any) {
      console.error('Failed to restore application:', error);
      alert(`Failed to restore application: ${error.response?.data?.error || error.message}`);
    }
  };

  const toggleSelectAll = () => {
    if (selectedIds.length === applications.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(applications.map(app => app.id));
    }
  };

  const toggleSelect = (id: number) => {
    setSelectedIds(prev => 
      prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
    );
  };

  const handleBulkRestore = async () => {
    if (selectedIds.length === 0) {
      alert('Please select items to restore');
      return;
    }

    if (!confirm(`Restore ${selectedIds.length} application(s)?`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=restore_applications', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        setSelectedApp(null);
        await fetchApplications();
        refreshNotifications();
      } else {
        alert(data.error || 'Failed to restore applications');
      }
    } catch (error: any) {
      console.error('Bulk restore failed:', error);
      alert('Failed to restore applications');
    } finally {
      setBulkLoading(false);
    }
  };

  const handleBulkDelete = async () => {
    if (selectedIds.length === 0) {
      alert('Please select items to delete');
      return;
    }

    if (!confirm(`PERMANENTLY DELETE ${selectedIds.length} application(s)? This cannot be undone!`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=delete_applications', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        setSelectedApp(null);
        await fetchApplications();
        refreshNotifications();
      } else {
        alert(data.error || 'Failed to delete applications');
      }
    } catch (error: any) {
      console.error('Bulk delete failed:', error);
      alert('Failed to delete applications');
    } finally {
      setBulkLoading(false);
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading applications...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`h-screen flex flex-col ${isDarkMode ? 'bg-black' : 'bg-gray-50'}`}>
      <div className={`${isDarkMode ? 'bg-black' : 'bg-gray-50'} p-4 md:p-8 pb-4`}>
        <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-4 md:p-6`}>
          <h1 className={`text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-4`}>Artist Applications</h1>
          
          <div className="flex flex-col sm:flex-row gap-3 md:gap-4">
            <select 
              value={filter} 
              onChange={(e) => setFilter(e.target.value)}
              className={`w-full sm:w-auto px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}
            >
              <option value="active">Active Applications</option>
              <option value="archived">Archived</option>
              <option value="all">All</option>
            </select>
            
            <input
              ref={searchInputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by applicant name..."
              className={`flex-1 px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8 pb-4 md:pb-8 flex-1 overflow-hidden">
        {/* Applications List */}
        <div className={`lg:col-span-1 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow flex flex-col overflow-hidden`}>
          <div className={`p-4 ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} border-b flex-shrink-0`}>
            <div className="flex items-center justify-between">
              <h2 className={`font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>All Applications</h2>
              {filter === 'archived' && applications.length > 0 && (
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === applications.length}
                    onChange={toggleSelectAll}
                    className="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                  />
                  <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>All</span>
                </label>
              )}
            </div>
          </div>

          {filter === 'archived' && selectedIds.length > 0 && (
            <div className={`p-3 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-100 border-gray-200'} border-b flex gap-2`}>
              <button
                onClick={handleBulkRestore}
                disabled={bulkLoading}
                className="flex-1 px-3 py-1.5 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700 disabled:opacity-50"
              >
                {bulkLoading ? 'Processing...' : `Restore ${selectedIds.length}`}
              </button>
              <button
                onClick={handleBulkDelete}
                disabled={bulkLoading}
                className="flex-1 px-3 py-1.5 bg-red-600 text-white text-sm font-semibold rounded hover:bg-red-700 disabled:opacity-50"
              >
                {bulkLoading ? 'Processing...' : `Delete ${selectedIds.length}`}
              </button>
            </div>
          )}

          <div className="overflow-y-auto flex-1">
            {applications.map((app) => (
              <div
                key={app.id}
                className={`p-4 ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} border-b ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} ${selectedApp?.id === app.id ? 'message-active' : ''}`}
              >
                <div className="flex items-start gap-3">
                  {filter === 'archived' && (
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(app.id)}
                      onChange={(e) => {
                        e.stopPropagation();
                        toggleSelect(app.id);
                      }}
                      className="mt-1 w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                    />
                  )}
                  <div 
                    className="flex-1 cursor-pointer"
                    onClick={() => viewApplication(app.id)}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <p className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{app.full_name}</p>
                      <span className={`px-2 py-1 text-xs font-bold rounded ${
                        app.status === 'pending' || app.status === 'Unread' || app.status === 'Read' ? 'bg-yellow-100 text-yellow-700' :
                        app.status === 'accepted' ? 'bg-green-100 text-green-700' :
                        app.status === 'rejected' ? 'bg-red-100 text-red-700' :
                        'bg-gray-100 text-gray-700'
                      }`}>
                        {app.status === 'Unread' || app.status === 'Read' ? 'Pending' : app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                      </span>
                    </div>
                    <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} truncate`}>{app.product_desc}</p>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>{new Date(app.submitted_at).toLocaleDateString()}</p>
                    {app.is_archived === 1 && (
                      <span className="inline-block mt-2 px-2 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded">Archived</span>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Application Details */}
        <div className={`lg:col-span-2 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-6 overflow-y-auto`}>
          {selectedApp ? (
            <div>
              <h2 className={`text-xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-4`}>Application Details</h2>
              <div className="space-y-4">
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Full Name</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{selectedApp.full_name}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Email</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{selectedApp.email}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Phone</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{selectedApp.phone}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Portfolio</label>
                  {selectedApp.portfolio ? (
                    <a 
                      href={selectedApp.portfolio} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="text-purple-600 hover:text-purple-700 underline"
                    >
                      {selectedApp.portfolio}
                    </a>
                  ) : (
                    <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>No portfolio provided</p>
                  )}
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Product Description</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{selectedApp.product_desc}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Submitted At</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{new Date(selectedApp.submitted_at).toLocaleString()}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-2`}>Status</label>
                  <span className={`px-3 py-1 rounded text-sm font-bold ${
                    selectedApp.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                    selectedApp.status === 'accepted' ? 'bg-green-100 text-green-700' :
                    selectedApp.status === 'rejected' ? 'bg-red-100 text-red-700' :
                    'bg-gray-100 text-gray-700'
                  }`}>
                    {selectedApp.status.charAt(0).toUpperCase() + selectedApp.status.slice(1)}
                  </span>
                </div>

                {selectedApp.rejection_reason && selectedApp.status === 'Rejected' && (
                  <div className={`p-4 rounded-lg ${isDarkMode ? 'bg-red-900 bg-opacity-30 border border-red-800' : 'bg-red-50 border border-red-200'}`}>
                    <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-red-400' : 'text-red-700'}`}>Rejection Reason</label>
                    <p className={isDarkMode ? 'text-red-300' : 'text-red-800'}>{selectedApp.rejection_reason}</p>
                  </div>
                )}
                
                {selectedApp.is_archived === 1 ? (
                  <div className="pt-4">
                    <button
                      onClick={() => handleRestore(selectedApp.id)}
                      className="w-full px-4 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700"
                    >
                      Restore Application
                    </button>
                  </div>
                ) : (selectedApp.status === 'Unread' || selectedApp.status === 'Read' || selectedApp.status === 'pending') ? (
                  <div className="flex gap-3 pt-4">
                    <button
                      onClick={() => handleStatusUpdate(selectedApp.id, 'accepted')}
                      className="flex-1 px-4 py-2 bg-green-200 text-green-800 font-semibold rounded-md hover:bg-green-300"
                    >
                      Accept Application
                    </button>
                    <button
                      onClick={() => handleStatusUpdate(selectedApp.id, 'rejected')}
                      className="flex-1 px-4 py-2 bg-red-200 text-red-800 font-semibold rounded-md hover:bg-red-300"
                    >
                      Reject Application
                    </button>
                  </div>
                ) : null}
              </div>
            </div>
          ) : (
            <div className={`text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} py-12`}>
              Select an application to view details
            </div>
          )}
        </div>
      </div>

      {/* Rejection Reason Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className={`${isDarkMode ? 'bg-gray-800 text-gray-100' : 'bg-white'} rounded-lg p-6 max-w-md w-full mx-4 shadow-xl`}>
            <h3 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>
              Reject Application
            </h3>
            <p className={`mb-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
              Please provide a reason for rejecting this application:
            </p>
            <textarea
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              placeholder="Enter rejection reason..."
              rows={4}
              maxLength={500}
              className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 ${
                isDarkMode ? 'bg-gray-700 border-gray-600 text-gray-100' : 'border-gray-300'
              }`}
            />
            <p className={`text-sm mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
              {rejectionReason.length}/500 characters
            </p>
            <div className="flex gap-3 mt-4">
              <button
                onClick={() => {
                  setShowRejectModal(false);
                  setRejectionReason('');
                  setRejectingAppId(null);
                }}
                className={`flex-1 px-4 py-2 rounded-md font-semibold ${
                  isDarkMode ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                }`}
              >
                Cancel
              </button>
              <button
                onClick={handleRejectWithReason}
                className="flex-1 px-4 py-2 bg-red-500 text-white font-semibold rounded-md hover:bg-red-600"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
