import { useEffect, useState, useRef } from 'react';
import { artistsAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';

interface Artist {
  id: number;
  artist_name: string;
  email: string;
  join_date: string;
  product_count: number;
  is_archived: number;
  user_id?: number;
  needs_artist_entry?: number;
}

export default function Artists() {
  const { isDarkMode } = useTheme();
  const [artists, setArtists] = useState<Artist[]>([]);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('active');
  const [loading, setLoading] = useState(true);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const shouldRefocus = useRef(false);
  const [showRevokeModal, setShowRevokeModal] = useState(false);
  const [selectedArtistId, setSelectedArtistId] = useState<number | null>(null);
  const [selectedArtistName, setSelectedArtistName] = useState('');
  const [revokeReason, setRevokeReason] = useState('');
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkLoading, setBulkLoading] = useState(false);

  useEffect(() => {
    fetchArtists();
    
    // Set up polling to refresh artists every 15 seconds
    const intervalId = setInterval(() => {
      fetchArtists();
    }, 15000); // Refresh every 15 seconds
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, [search, filter]); // Re-create interval when search or filter changes

  // Refocus search input after re-render
  useEffect(() => {
    if (shouldRefocus.current && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [artists]);

  const fetchArtists = async () => {
    try {
      const response = await artistsAPI.getArtists(search, filter);
      setArtists(response.data.artists);
    } catch (error) {
      console.error('Failed to fetch artists:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleRevokeArtist = async (artistId: number, artistName: string) => {
    setSelectedArtistId(artistId);
    setSelectedArtistName(artistName);
    setShowRevokeModal(true);
  };

  const handleRevokeSubmit = async () => {
    if (!revokeReason.trim()) {
      alert('Please provide a reason for revoking artist privileges');
      return;
    }

    try {
      await artistsAPI.revokeArtist(selectedArtistId!, revokeReason);
      alert('Artist archived successfully!');
      setShowRevokeModal(false);
      setRevokeReason('');
      setSelectedArtistId(null);
      setSelectedArtistName('');
      fetchArtists();
    } catch (error) {
      console.error('Failed to revoke artist:', error);
      alert('Failed to revoke artist privileges');
    }
  };

  const handleRestoreArtist = async (artistId: number, artistName: string) => {
    if (!confirm(`Are you sure you want to restore ${artistName}'s artist privileges?`)) return;
    
    try {
      await artistsAPI.restoreArtist(artistId);
      alert('Artist restored successfully!');
      fetchArtists();
    } catch (error) {
      console.error('Failed to restore artist:', error);
      alert('Failed to restore artist');
    }
  };

  const handleDeleteArtist = async (artistId: number, artistName: string) => {
    if (!confirm(`Are you sure you want to permanently delete ${artistName}? This action cannot be undone.`)) return;
    
    try {
      await artistsAPI.deleteArtist(artistId);
      alert('Artist deleted successfully!');
      fetchArtists();
    } catch (error) {
      console.error('Failed to delete artist:', error);
      alert('Failed to delete artist');
    }
  };

  const handleCreateArtistEntry = async (userId: number, artistName: string) => {
    if (!confirm(`Create artist entry for ${artistName}? This will allow them to manage products.`)) return;
    
    try {
      await artistsAPI.createArtistEntry(userId);
      alert('Artist entry created successfully!');
      fetchArtists();
    } catch (error) {
      console.error('Failed to create artist entry:', error);
      alert('Failed to create artist entry');
    }
  };

  const toggleSelectAll = () => {
    if (selectedIds.length === artists.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(artists.map(artist => artist.id));
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

    if (!confirm(`Restore ${selectedIds.length} artist(s)?`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=restore_artists', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        await fetchArtists();
      } else {
        alert(data.error || 'Failed to restore artists');
      }
    } catch (error: any) {
      console.error('Bulk restore failed:', error);
      alert('Failed to restore artists');
    } finally {
      setBulkLoading(false);
    }
  };

  const handleBulkDelete = async () => {
    if (selectedIds.length === 0) {
      alert('Please select items to delete');
      return;
    }

    if (!confirm(`PERMANENTLY DELETE ${selectedIds.length} artist(s)? This cannot be undone!`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=delete_artists', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        await fetchArtists();
      } else {
        alert(data.error || 'Failed to delete artists');
      }
    } catch (error: any) {
      console.error('Bulk delete failed:', error);
      alert('Failed to delete artists');
    } finally {
      setBulkLoading(false);
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading artists...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`h-screen flex flex-col ${isDarkMode ? 'bg-black' : 'bg-gray-50'}`}>
      <div className={`${isDarkMode ? 'bg-black' : 'bg-gray-50'} p-4 md:p-8 pb-4`}>
        <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-4 md:p-6`}>
          <h1 className={`text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-4`}>Artists Management</h1>
          
          <div className="flex flex-col sm:flex-row gap-4">
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value)}
              className={`w-full sm:w-auto px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}
            >
              <option value="active">Active Artists</option>
              <option value="archived">Archived</option>
              <option value="all">All</option>
            </select>
            
            <input
              ref={searchInputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by artist name or email..."
              className={`flex-1 px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
          </div>
        </div>
      </div>

      <div className={`mx-4 md:mx-8 mb-4 md:mb-8 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow flex-1 overflow-hidden flex flex-col`}>
        {filter === 'archived' && selectedIds.length > 0 && (
          <div className={`p-3 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-100 border-gray-200'} border-b flex gap-2`}>
            <button
              onClick={handleBulkRestore}
              disabled={bulkLoading}
              className="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {bulkLoading ? 'Processing...' : `Restore ${selectedIds.length} Selected`}
            </button>
            <button
              onClick={handleBulkDelete}
              disabled={bulkLoading}
              className="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded hover:bg-red-700 disabled:opacity-50"
            >
              {bulkLoading ? 'Processing...' : `Delete ${selectedIds.length} Selected`}
            </button>
          </div>
        )}
        
        <div className="overflow-auto flex-1">
        <table className="w-full">
          <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
            <tr>
              {filter === 'archived' && (
                <th className={`px-4 py-3 text-left`}>
                  <input
                    type="checkbox"
                    checked={selectedIds.length === artists.length && artists.length > 0}
                    onChange={toggleSelectAll}
                    className="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                  />
                </th>
              )}
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Artist Name</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Email</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Products</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Joined</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} uppercase`}>Actions</th>
            </tr>
          </thead>
          <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'}`}>
            {artists.length > 0 ? (
              artists.map((artist) => (
                <tr key={artist.id} className={isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}>
                  {filter === 'archived' && (
                    <td className="px-4 py-4">
                      <input
                        type="checkbox"
                        checked={selectedIds.includes(artist.id)}
                        onChange={() => toggleSelect(artist.id)}
                        className="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                      />
                    </td>
                  )}
                  <td className="px-6 py-4 font-semibold text-purple-700">{artist.artist_name}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{artist.email}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{artist.product_count}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{new Date(artist.join_date).toLocaleDateString()}</td>
                  <td className="px-6 py-4">
                    {artist.needs_artist_entry === 1 ? (
                      <button
                        onClick={() => handleCreateArtistEntry(artist.user_id!, artist.artist_name)}
                        className="px-3 py-1 bg-green-200 text-green-800 text-sm font-semibold rounded hover:bg-green-300"
                      >
                        Create Artist Entry
                      </button>
                    ) : artist.is_archived == 1 ? (
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleRestoreArtist(artist.id, artist.artist_name)}
                          className="px-3 py-1 bg-blue-200 text-blue-800 text-sm font-semibold rounded hover:bg-blue-300"
                        >
                          Restore
                        </button>
                        <button
                          onClick={() => handleDeleteArtist(artist.id, artist.artist_name)}
                          className="px-3 py-1 bg-red-200 text-red-800 text-sm font-semibold rounded hover:bg-red-300"
                        >
                          Delete
                        </button>
                      </div>
                    ) : (
                      <button
                        onClick={() => handleRevokeArtist(artist.id, artist.artist_name)}
                        className="px-3 py-1 bg-red-200 text-red-800 text-sm font-semibold rounded hover:bg-red-300"
                      >
                        Revoke
                      </button>
                    )}
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={5} className={`px-6 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  No artists found
                </td>
              </tr>
            )}
          </tbody>
        </table>
        </div>
      </div>

      {/* Revoke Modal */}
      {showRevokeModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow-2xl max-w-md w-full p-6`}>
            <h2 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Revoke Artist Privileges</h2>
            <p className={`mb-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
              You are about to revoke <span className="font-bold">{selectedArtistName}</span>'s artist privileges. 
              Please provide a reason. The artist will be able to see this reason.
            </p>
            
            <div className="mb-4">
              <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-700'}`}>Reason for Revocation</label>
              <select
                value={revokeReason}
                onChange={(e) => setRevokeReason(e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : 'border-gray-300'}`}
              >
                <option value="">Select a reason...</option>
                <option value="Violated community guidelines">Violated community guidelines</option>
                <option value="Multiple policy violations">Multiple policy violations</option>
                <option value="Inappropriate content">Inappropriate content</option>
                <option value="Copyright infringement">Copyright infringement</option>
                <option value="Fraudulent activity">Fraudulent activity</option>
                <option value="Poor quality products">Poor quality products</option>
                <option value="Inactive account">Inactive account</option>
                <option value="Artist request">Artist request</option>
              </select>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => {
                  setShowRevokeModal(false);
                  setRevokeReason('');
                  setSelectedArtistId(null);
                  setSelectedArtistName('');
                }}
                className={`flex-1 px-4 py-2 rounded-md font-semibold ${isDarkMode ? 'bg-gray-800 text-white hover:bg-gray-700' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'}`}
              >
                Cancel
              </button>
              <button
                onClick={handleRevokeSubmit}
                className="flex-1 px-4 py-2 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700"
              >
                Revoke
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
