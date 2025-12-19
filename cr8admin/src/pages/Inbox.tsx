import { useEffect, useState, useRef } from 'react';
import { inboxAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';
import { useNotifications } from '../context/NotificationsContext';

interface Message {
  id: number;
  name: string;
  email: string;
  subject: string;
  message: string;
  created_at: string;
  status: string;
  is_archived?: number;
}

export default function Inbox() {
  const { isDarkMode } = useTheme();
  const { refreshNotifications } = useNotifications();
  const [messages, setMessages] = useState<Message[]>([]);
  const [selectedMessage, setSelectedMessage] = useState<Message | null>(null);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('active');
  const [loading, setLoading] = useState(true);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const shouldRefocus = useRef(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkLoading, setBulkLoading] = useState(false);

  useEffect(() => {
    fetchMessages();
    
    // Set up polling to refresh messages every 5 seconds
    const intervalId = setInterval(() => {
      fetchMessages();
    }, 5000); // Check every 5 seconds for new messages
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, [search, filter]); // Re-create interval when search or filter changes

  // Clear selections when filter changes
  useEffect(() => {
    setSelectedIds([]);
  }, [filter]);

  // Refocus search input after re-render
  useEffect(() => {
    if (shouldRefocus.current && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [messages]);

  const fetchMessages = async () => {
    try {
      const response = await inboxAPI.getMessages(search, filter);
      setMessages(response.data.messages);
    } catch (error) {
      console.error('Failed to fetch messages:', error);
    } finally {
      setLoading(false);
    }
  };

  const viewMessage = async (id: number) => {
    try {
      const response = await inboxAPI.getMessageDetails(id);
      setSelectedMessage(response.data.message);
      // Refresh list to update status
      fetchMessages();
    } catch (error) {
      console.error('Failed to fetch message details:', error);
    }
  };

  const archiveMessage = async (id: number) => {
    if (confirm('Are you sure you want to archive this message?')) {
      try {
        await inboxAPI.archiveMessage(id);
        setSelectedMessage(null);
        fetchMessages();
        refreshNotifications(); // Refresh notification counts immediately
      } catch (error) {
        console.error('Failed to archive message:', error);
      }
    }
  };

  const restoreMessage = async (id: number) => {
    if (confirm('Are you sure you want to restore this message?')) {
      try {
        await inboxAPI.restoreMessage(id);
        setSelectedMessage(null);
        fetchMessages();
        refreshNotifications(); // Refresh notification counts immediately
      } catch (error) {
        console.error('Failed to restore message:', error);
      }
    }
  };

  const toggleSelectAll = () => {
    if (selectedIds.length === messages.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(messages.map(msg => msg.id));
    }
  };

  const toggleSelect = (id: number) => {
    setSelectedIds(prev => 
      prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
    );
  };

  const handleBulkArchive = async () => {
    if (selectedIds.length === 0) {
      alert('Please select items to archive');
      return;
    }

    if (!confirm(`Archive ${selectedIds.length} message(s)?`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=archive_messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        setSelectedMessage(null);
        await fetchMessages();
        refreshNotifications();
      } else {
        alert(data.error || 'Failed to archive messages');
      }
    } catch (error: any) {
      console.error('Bulk archive failed:', error);
      alert('Failed to archive messages');
    } finally {
      setBulkLoading(false);
    }
  };

  const handleBulkRestore = async () => {
    if (selectedIds.length === 0) {
      alert('Please select items to restore');
      return;
    }

    if (!confirm(`Restore ${selectedIds.length} message(s)?`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=restore_messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        setSelectedMessage(null);
        await fetchMessages();
        refreshNotifications();
      } else {
        alert(data.error || 'Failed to restore messages');
      }
    } catch (error: any) {
      console.error('Bulk restore failed:', error);
      alert('Failed to restore messages');
    } finally {
      setBulkLoading(false);
    }
  };

  const handleBulkDelete = async () => {
    if (selectedIds.length === 0) {
      alert('Please select items to delete');
      return;
    }

    if (!confirm(`PERMANENTLY DELETE ${selectedIds.length} message(s)? This cannot be undone!`)) return;

    setBulkLoading(true);
    try {
      const response = await fetch('https://cr8admin.dcism.org/api/bulk_operations.php?action=delete_messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: selectedIds })
      });

      const data = await response.json();
      if (response.ok) {
        alert(data.message);
        setSelectedIds([]);
        setSelectedMessage(null);
        await fetchMessages();
        refreshNotifications();
      } else {
        alert(data.error || 'Failed to delete messages');
      }
    } catch (error: any) {
      console.error('Bulk delete failed:', error);
      alert('Failed to delete messages');
    } finally {
      setBulkLoading(false);
    }
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading messages...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`h-screen flex flex-col ${isDarkMode ? 'bg-black' : 'bg-gray-50'}`}>
      <div className={`${isDarkMode ? 'bg-black' : 'bg-gray-50'} p-4 md:p-8 pb-4`}>
        <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-4 md:p-6`}>
          <h1 className={`text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'} mb-4`}>Inbox</h1>
          
          <div className="flex flex-col sm:flex-row gap-4">
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value)}
              className={`w-full sm:w-auto px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}
            >
              <option value="active">Active Messages</option>
              <option value="archived">Archived</option>
              <option value="all">All</option>
            </select>
            
            <input
              ref={searchInputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by name or email..."
              className={`flex-1 px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8 pb-4 md:pb-8 flex-1 overflow-hidden">
        {/* Messages List */}
        <div className={`lg:col-span-1 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow flex flex-col overflow-hidden`}>
          <div className={`p-4 ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} border-b flex-shrink-0`}>
            <div className="flex items-center justify-between">
              <h2 className={`font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>All Messages</h2>
              {messages.length > 0 && (
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === messages.length}
                    onChange={toggleSelectAll}
                    className="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                  />
                  <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>All</span>
                </label>
              )}
            </div>
          </div>

          {selectedIds.length > 0 && (
            <div className={`p-3 ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-100 border-gray-200'} border-b flex gap-2`}>
              {filter === 'archived' ? (
                <>
                  <button
                    onClick={handleBulkRestore}
                    disabled={bulkLoading}
                    className="flex-1 px-3 py-1.5 bg-blue-300 text-blue-900 text-sm font-semibold rounded hover:bg-blue-400 disabled:opacity-50"
                  >
                    {bulkLoading ? 'Processing...' : `Restore ${selectedIds.length}`}
                  </button>
                  <button
                    onClick={handleBulkDelete}
                    disabled={bulkLoading}
                    className="flex-1 px-3 py-1.5 bg-rose-300 text-rose-900 text-sm font-semibold rounded hover:bg-rose-400 disabled:opacity-50"
                  >
                    {bulkLoading ? 'Processing...' : `Delete ${selectedIds.length}`}
                  </button>
                </>
              ) : (
                <button
                  onClick={handleBulkArchive}
                  disabled={bulkLoading}
                  className="w-full px-3 py-1.5 bg-slate-300 text-slate-900 text-sm font-semibold rounded hover:bg-slate-400 disabled:opacity-50"
                >
                  {bulkLoading ? 'Processing...' : `Archive ${selectedIds.length}`}
                </button>
              )}
            </div>
          )}
          <div className="overflow-y-auto flex-1">
            {messages.map((msg) => (
              <div
                key={msg.id}
                className={`p-4 ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} border-b ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} ${selectedMessage?.id === msg.id ? 'message-active' : ''}`}
              >
                <div className="flex items-start gap-3">
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(msg.id)}
                    onChange={(e) => {
                      e.stopPropagation();
                      toggleSelect(msg.id);
                    }}
                    className="mt-1 w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                  />
                  <div 
                    className="flex-1 cursor-pointer"
                    onClick={() => viewMessage(msg.id)}
                  >
                    <p className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{msg.name}</p>
                    <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} font-semibold truncate`}>{msg.subject}</p>
                    <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} truncate`}>{msg.message}</p>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>{new Date(msg.created_at).toLocaleDateString()}</p>
                    {msg.status === 'Unread' && (
                      <span className="inline-block mt-2 px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded">Unread</span>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Message Details */}
        <div className={`lg:col-span-2 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl shadow p-6 overflow-y-auto`}>
          {selectedMessage ? (
            <div>
              <div className="flex justify-between items-start mb-4">
                <h2 className={`text-xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Message Details</h2>
                <div className="flex gap-2">
                  <a
                    href={`mailto:${selectedMessage.email}?subject=Re: ${encodeURIComponent(selectedMessage.subject)}`}
                    className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 font-semibold"
                  >
                    Reply
                  </a>
                  {selectedMessage.is_archived === 1 ? (
                    <button
                      onClick={() => restoreMessage(selectedMessage.id)}
                      className="px-4 py-2 bg-blue-300 text-blue-900 rounded-md hover:bg-blue-400 font-semibold"
                    >
                      Restore
                    </button>
                  ) : (
                    <button
                      onClick={() => archiveMessage(selectedMessage.id)}
                      className="px-4 py-2 bg-slate-300 text-slate-900 rounded-md hover:bg-slate-400 font-semibold"
                    >
                      Archive
                    </button>
                  )}
                </div>
              </div>
              <div className="space-y-4">
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>From</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{selectedMessage.name}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Email</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{selectedMessage.email}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Subject</label>
                  <p className={`${isDarkMode ? 'text-gray-300' : 'text-gray-800'} font-semibold`}>{selectedMessage.subject}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Message</label>
                  <p className={`${isDarkMode ? 'text-gray-300' : 'text-gray-800'} whitespace-pre-wrap`}>{selectedMessage.message}</p>
                </div>
                <div>
                  <label className={`block text-sm font-semibold ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Received</label>
                  <p className={isDarkMode ? 'text-gray-300' : 'text-gray-800'}>{new Date(selectedMessage.created_at).toLocaleString()}</p>
                </div>
              </div>
            </div>
          ) : (
            <div className={`text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} py-12`}>
              Select a message to view details
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
