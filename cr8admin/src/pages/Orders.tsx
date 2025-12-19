import { useEffect, useState, useRef } from 'react';
import { ordersAPI } from '../services/api';
import { useTheme } from '../context/ThemeContext';

interface Order {
  id: number;
  order_no: string;
  total: number;
  created_at: string;
  first_name: string;
  last_name: string;
  delivery_status: string;
  tracking_number?: string;
  proof_path?: string;
  proof_delivery?: string;
  cancel_reason?: string;
  refund_status?: 'Pending' | 'Refunded' | 'Not Required';
  refund_proof?: string;
}

export default function Orders() {
  const { isDarkMode } = useTheme();
  const [orders, setOrders] = useState<Order[]>([]);
  const [status, setStatus] = useState('all');
  const [search, setSearch] = useState('');
  const [initialLoading, setInitialLoading] = useState(true);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const shouldRefocus = useRef(false);
  const [appliedStatus, setAppliedStatus] = useState('all'); // Track applied filter
  const [showTrackingModal, setShowTrackingModal] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [trackingNumber, setTrackingNumber] = useState('');
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [cancelReason, setCancelReason] = useState('');
  const [showProofModal, setShowProofModal] = useState(false);
  const [proofImageUrl, setProofImageUrl] = useState('');
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const [orderDetails, setOrderDetails] = useState<any>(null);
  const [showRefundModal, setShowRefundModal] = useState(false);
  const [refundProofFile, setRefundProofFile] = useState<File | null>(null);
  const [uploadingRefund, setUploadingRefund] = useState(false);

  useEffect(() => {
    fetchOrders();
    
    // Set up polling to refresh orders every 5 seconds
    const intervalId = setInterval(() => {
      fetchOrders();
    }, 5000); // Check every 5 seconds for new proof uploads
    
    // Cleanup interval when component unmounts
    return () => clearInterval(intervalId);
  }, [appliedStatus, search]); // Re-create interval when filter or search changes

  // Refocus search input after re-render
  useEffect(() => {
    if (shouldRefocus.current && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [orders]);

  const fetchOrders = async (statusFilter?: string) => {
    try {
      const filterToUse = statusFilter !== undefined ? statusFilter : appliedStatus;
      const response = await ordersAPI.getOrders(filterToUse, search);
      setOrders(response.data.orders);
    } catch (error) {
      console.error('Failed to fetch orders:', error);
    } finally {
      setInitialLoading(false);
    }
  };

  const handleApplyFilters = () => {
    setAppliedStatus(status);
    fetchOrders(status); // Pass status directly to avoid stale state
  };

  const handleStatusChange = async (orderId: number, newStatus: string, currentTrackingNumber?: string, currentStatus?: string, proofDelivery?: string) => {
    // Validate proof of delivery for Completed status
    if (newStatus === 'Completed' && !proofDelivery) {
      alert('Cannot mark order as Completed: User has not uploaded proof of delivery yet.');
      await fetchOrders(); // Refresh to reset the dropdown
      return;
    }
    
    // Validate tracking number for Out for Delivery status
    if (newStatus === 'Out for Delivery' && !currentTrackingNumber) {
      alert('Please add a tracking number before setting status to "Out for Delivery"');
      return;
    }
    
    // If current status is Out for Delivery, only allow Cancelled or Completed
    if (currentStatus === 'Out for Delivery' && newStatus !== 'Out for Delivery' && newStatus !== 'Completed' && newStatus !== 'Cancelled') {
      alert('Orders that are "Out for Delivery" can only be changed to "Completed" or "Cancelled"');
      await fetchOrders(); // Refresh to reset the dropdown
      return;
    }
    
    // Show cancellation modal if changing to Cancelled
    if (newStatus === 'Cancelled') {
      const order = orders.find(o => o.id === orderId);
      if (order) {
        setShowTrackingModal(false); // Close manage modal first
        setSelectedOrder(order);
        setShowCancelModal(true);
      }
      return;
    }
    
    try {
      await ordersAPI.updateStatus(orderId, newStatus);
      alert('Order status updated successfully!');
      
      // Update selectedOrder if modal is open
      if (selectedOrder && selectedOrder.id === orderId) {
        setSelectedOrder({ ...selectedOrder, delivery_status: newStatus });
      }
      
      await fetchOrders();
    } catch (error) {
      console.error('Failed to update order status:', error);
      alert('Failed to update order status');
    }
  };

  const handleCancelSubmit = async () => {
    if (!cancelReason.trim()) {
      alert('Please select a reason for cancelling this order');
      return;
    }
    
    if (!selectedOrder) return;
    
    try {
      await ordersAPI.cancelOrder(selectedOrder.id, cancelReason);
      alert('Order cancelled successfully!');
      setShowCancelModal(false);
      setCancelReason('');
      setSelectedOrder(null);
      fetchOrders();
    } catch (error) {
      console.error('Failed to cancel order:', error);
      alert('Failed to cancel order');
    }
  };

  const viewOrderDetails = async (orderId: number) => {
    try {
      console.log('Fetching order details for ID:', orderId);
      const response = await ordersAPI.getOrderDetails(orderId);
      console.log('Full API response:', response);
      console.log('Response data:', response.data);
      console.log('Order:', response.data.order);
      console.log('Items:', response.data.items);
      console.log('Delivery:', response.data.delivery);
      
      // Combine all data into orderDetails
      const combinedData = {
        ...response.data.order,
        items: response.data.items,
        delivery: response.data.delivery
      };
      console.log('Combined data:', combinedData);
      
      setOrderDetails(combinedData);
      setShowDetailsModal(true);
    } catch (error) {
      console.error('Failed to fetch order details:', error);
      alert('Failed to load order details');
    }
  };

  const handleOpenTrackingModal = (order: Order) => {
    setSelectedOrder(order);
    setTrackingNumber(order.tracking_number || '');
    setShowTrackingModal(true);
  };

  const generateTrackingNumber = () => {
    const timestamp = Date.now();
    const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `TRCK-${timestamp}${randomNum}`;
  };

  const handleOpenRefundModal = (order: Order) => {
    setSelectedOrder(order);
    setShowRefundModal(true);
    setRefundProofFile(null);
  };

  const handleRefundUpload = async () => {
    if (!selectedOrder || !refundProofFile) {
      alert('Please select a refund proof image');
      return;
    }

    setUploadingRefund(true);
    const formData = new FormData();
    formData.append('order_id', selectedOrder.id.toString());
    formData.append('refund_proof', refundProofFile);

    try {
      await ordersAPI.uploadRefundProof(formData);
      alert('Refund proof uploaded successfully');
      setShowRefundModal(false);
      setRefundProofFile(null);
      await fetchOrders();
    } catch (error) {
      console.error('Failed to upload refund proof:', error);
      alert('Failed to upload refund proof');
    } finally {
      setUploadingRefund(false);
    }
  };

  const handleGenerateTracking = async () => {
    if (!selectedOrder) return;
    
    const newTrackingNumber = generateTrackingNumber();
    setTrackingNumber(newTrackingNumber);
    
    try {
      // Update tracking number only
      await ordersAPI.updateTracking(selectedOrder.id, newTrackingNumber);
      // Update the selected order with new tracking number
      setSelectedOrder({ ...selectedOrder, tracking_number: newTrackingNumber });
      alert('Tracking number generated! You can now change the status to "Out for Delivery" if needed.');
      fetchOrders();
    } catch (error) {
      console.error('Failed to generate tracking number:', error);
      alert('Failed to generate tracking number');
    }
  };

  const getStatusBadge = (status: string) => {
    const colors: Record<string, string> = {
      'For Review': 'bg-yellow-100 text-yellow-700',
      'Processing': 'bg-orange-100 text-orange-700',
      'Out for Delivery': 'bg-indigo-100 text-indigo-700',
      'Completed': 'bg-green-100 text-green-700',
      'Cancelled': 'bg-red-100 text-red-700',
    };
    return <span className={`px-2 py-1 text-xs font-bold rounded ${colors[status] || 'bg-gray-100 text-gray-700'}`}>{status}</span>;
  };

  if (initialLoading) {
    return (
      <div className={`flex items-center justify-center min-h-screen ${isDarkMode ? 'bg-black' : ''}`}>
        <div className="text-center">
          <div className="w-16 h-16 border-4 border-purple-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className={isDarkMode ? 'text-gray-300' : 'text-gray-600'}>Loading orders...</p>
        </div>
      </div>
    );
  }

  return (
    <section className={`h-screen flex flex-col ${isDarkMode ? 'bg-black' : 'bg-gray-50'}`}>
      <div className={`${isDarkMode ? 'bg-black' : 'bg-gray-50'} p-4 md:p-8 pb-4`}>
        <div className={`rounded-xl shadow p-6 ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
          <h1 className={`text-2xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Orders Management</h1>
          
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <select value={status} onChange={(e) => setStatus(e.target.value)} className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}>
              <option value="all">All Orders</option>
              <option value="For Review">For Review</option>
              <option value="Processing">Processing</option>
              <option value="Out for Delivery">Out for Delivery</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>

            <input
              ref={searchInputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by order number..."
              className={`px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700 placeholder-gray-500' : ''}`}
            />

            <button onClick={handleApplyFilters} className="px-6 py-2 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700">
              Apply Filters
            </button>
          </div>
        </div>
      </div>

      <div className={`mx-4 md:mx-8 mb-4 md:mb-8 rounded-xl shadow flex-1 overflow-hidden flex flex-col ${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'}`}>
        <div className="overflow-auto flex-1">
        <table className="w-full">
          <thead className={`border-b ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
            <tr>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Order No</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Customer</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Total</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Date</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Status</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Tracking</th>
              <th className={`px-6 py-3 text-left text-xs font-semibold uppercase ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Actions</th>
            </tr>
          </thead>
          <tbody className={`divide-y ${isDarkMode ? 'divide-gray-800' : 'divide-gray-200'}`}>
            {orders.length > 0 ? (
              orders.map((order) => (
                <tr key={order.id} className={isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}>
                  <td 
                    className="px-6 py-4 font-semibold text-purple-400 cursor-pointer hover:text-purple-500 hover:underline"
                    onClick={() => viewOrderDetails(order.id)}
                    title="Click to view full details"
                  >
                    {order.order_no}
                  </td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{order.first_name} {order.last_name}</td>
                  <td className={`px-6 py-4 font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>₱{Number(order.total).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                  <td className={`px-6 py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{new Date(order.created_at).toLocaleDateString()}</td>
                  <td className="px-6 py-4">{getStatusBadge(order.delivery_status || 'Pending')}</td>
                  <td className="px-6 py-4">
                    {order.tracking_number ? (
                      <span className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{order.tracking_number}</span>
                    ) : (
                      <span className={`text-sm ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Not set</span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex gap-2 flex-wrap">
                      {order.proof_delivery && (
                        <button
                          onClick={() => {
                            setProofImageUrl(order.proof_delivery!);
                            setShowProofModal(true);
                          }}
                          className="px-3 py-1 bg-green-200 text-green-800 text-sm font-semibold rounded hover:bg-green-300 whitespace-nowrap"
                          title="View proof of delivery"
                        >
                          View Proof
                        </button>
                      )}
                      {order.delivery_status === 'Cancelled' && order.refund_status !== 'Refunded' && (
                        <button
                          onClick={() => handleOpenRefundModal(order)}
                          className="px-3 py-1 bg-yellow-200 text-yellow-800 text-sm font-semibold rounded hover:bg-yellow-300 whitespace-nowrap"
                          title="Upload refund proof"
                        >
                          {order.refund_status === 'Pending' ? 'Process Refund' : 'Add Refund'}
                        </button>
                      )}
                      {order.delivery_status === 'Cancelled' && order.refund_proof && (
                        <button
                          onClick={() => {
                            setProofImageUrl(order.refund_proof!);
                            setShowProofModal(true);
                          }}
                          className="px-3 py-1 bg-emerald-200 text-emerald-800 text-sm font-semibold rounded hover:bg-emerald-300 whitespace-nowrap"
                          title="View refund proof"
                        >
                          Refund Proof
                        </button>
                      )}
                      <button
                        onClick={() => handleOpenTrackingModal(order)}
                        className="px-3 py-1 bg-blue-200 text-blue-800 text-sm font-semibold rounded hover:bg-blue-300 whitespace-nowrap"
                      >
                        Manage
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={7} className={`px-6 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  No orders found
                </td>
              </tr>
            )}
          </tbody>
        </table>
        </div>
      </div>

      {/* Manage Order Modal */}
      {showTrackingModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
          <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl p-6 max-w-md w-full mx-4`}>
            <h3 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
              Manage Order
            </h3>
            <div className="mb-4">
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-2`}>
                Order: <span className="font-semibold text-purple-400">{selectedOrder.order_no}</span>
              </p>
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-4`}>
                Customer: <span className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
                  {selectedOrder.first_name} {selectedOrder.last_name}
                </span>
              </p>
              
              {/* Order Status */}
              <div className="mb-4">
                <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Order Status
                </label>
                <select 
                  value={selectedOrder.delivery_status || 'For Review'}
                  onChange={(e) => handleStatusChange(selectedOrder.id, e.target.value, selectedOrder.tracking_number, selectedOrder.delivery_status, selectedOrder.proof_delivery)}
                  className={`w-full px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}
                >
                  <option value="For Review">For Review</option>
                  <option value="Processing">Processing</option>
                  <option value="Out for Delivery">Out for Delivery</option>
                  <option value="Completed">Completed</option>
                  <option value="Cancelled">Cancelled</option>
                </select>
              </div>

              {/* Tracking Number */}
              <div>
                <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Tracking Number
                </label>
                
                {trackingNumber ? (
                  <div className={`p-3 rounded-md border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-300'}`}>
                    <p className={`text-lg font-mono font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
                      {trackingNumber}
                    </p>
                    <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>
                      Tracking number already assigned
                    </p>
                  </div>
                ) : (
                  <div>
                    <p className={`text-sm mb-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
                      No tracking number assigned yet. Click the button below to generate one.
                    </p>
                    <button
                      onClick={handleGenerateTracking}
                      className="w-full px-4 py-3 bg-purple-600 text-white font-semibold rounded-md hover:bg-purple-700"
                    >
                      Generate Tracking Number
                    </button>
                  </div>
                )}
              </div>
            </div>
            
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setShowTrackingModal(false)}
                className={`px-4 py-2 rounded-md ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Cancellation Reason Modal */}
      {showCancelModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
          <div className={`${isDarkMode ? 'bg-gray-900 border border-gray-800' : 'bg-white'} rounded-xl p-6 max-w-md w-full mx-4`}>
            <h3 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
              Cancel Order
            </h3>
            <div className="mb-4">
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-2`}>
                Order: <span className="font-semibold text-purple-400">{selectedOrder.order_no}</span>
              </p>
              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mb-4`}>
                Customer: <span className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
                  {selectedOrder.first_name} {selectedOrder.last_name}
                </span>
              </p>
              
              <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                Reason for Cancellation *
              </label>
              <select
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-purple-400 ${isDarkMode ? 'bg-gray-800 text-white border-gray-700' : ''}`}
              >
                <option value="">Select a reason</option>
                <option value="Customer request">Customer request</option>
                <option value="Payment not received">Payment not received</option>
                <option value="Item out of stock">Item out of stock</option>
                <option value="Fraudulent order">Fraudulent order</option>
                <option value="Duplicate order">Duplicate order</option>
                <option value="Address issue">Address issue</option>
                <option value="Customer unreachable">Customer unreachable</option>
                <option value="Other reasons">Other reasons</option>
              </select>
            </div>
            
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => {
                  setShowCancelModal(false);
                  setCancelReason('');
                  fetchOrders(); // Refresh to reset dropdown
                }}
                className={`px-4 py-2 rounded-md ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
              >
                Cancel
              </button>
              <button
                onClick={handleCancelSubmit}
                className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
              >
                Confirm Cancellation
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Proof of Payment Modal */}
      {showProofModal && (
        <div className="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 p-4">
          <div className={`${isDarkMode ? 'bg-gray-900' : 'bg-white'} rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-auto`}>
            <div className="p-6">
              <h3 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
                Proof of Delivery
              </h3>
              <div className="mb-4">
                <img 
                  src={`https://cr8.dcism.org/${proofImageUrl}`}
                  alt="Proof of Delivery" 
                  className="w-full h-auto rounded-lg border"
                  onError={(e) => {
                    e.currentTarget.src = '/cr8/img/placeholder.png';
                  }}
                />
              </div>
              <div className="flex justify-end gap-3">
                <button
                  onClick={() => {
                    setShowProofModal(false);
                    setProofImageUrl('');
                  }}
                  className={`px-4 py-2 rounded-md ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                >
                  Close
                </button>
                <a
                  href={`https://cr8.dcism.org/${proofImageUrl}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700"
                >
                  Open in New Tab
                </a>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Order Details Modal */}
      {showDetailsModal && orderDetails && (
        <div className="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className={`${isDarkMode ? 'bg-gray-900' : 'bg-white'} rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-auto`}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className={`text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
                  Order Details - {orderDetails?.order_no || 'N/A'}
                </h3>
                <button
                  onClick={() => {
                    setShowDetailsModal(false);
                    setOrderDetails(null);
                  }}
                  className={`text-2xl ${isDarkMode ? 'text-gray-400 hover:text-gray-200' : 'text-gray-500 hover:text-gray-700'}`}
                >
                  ×
                </button>
              </div>

              {/* Customer Information */}
              <div className="mb-6">
                <h4 className={`text-lg font-semibold mb-3 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Customer Information</h4>
                <div className={`grid grid-cols-2 gap-4 p-4 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
                  <div>
                    <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Name:</span>
                    <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{orderDetails?.first_name || ''} {orderDetails?.last_name || ''}</p>
                  </div>
                  <div>
                    <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Email:</span>
                    <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{orderDetails?.email || 'N/A'}</p>
                  </div>
                  <div>
                    <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Phone:</span>
                    <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{orderDetails?.phone || 'N/A'}</p>
                  </div>
                  <div>
                    <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Order Date:</span>
                    <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{orderDetails?.created_at ? new Date(orderDetails.created_at).toLocaleString() : 'N/A'}</p>
                  </div>
                  <div className="col-span-2">
                    <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Delivery Address:</span>
                    <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
                      {orderDetails?.address || 'N/A'}
                    </p>
                  </div>
                </div>
              </div>

              {/* Order Items */}
              <div className="mb-6">
                <h4 className={`text-lg font-semibold mb-3 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Order Items</h4>
                <div className="space-y-3">
                  {orderDetails.items?.map((item: any, index: number) => (
                    <div key={index} className={`flex items-center gap-4 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
                      <img 
                        src={`https://cr8.dcism.org/${item.item_image}`} 
                        alt={item.product_name}
                        className="w-16 h-16 object-cover rounded"
                        onError={(e) => {
                          const target = e.currentTarget;
                          if (!target.dataset.errorHandled) {
                            target.dataset.errorHandled = 'true';
                            target.src = '/img/avatar-placeholder.png';
                          }
                        }}
                      />
                      <div className="flex-grow">
                        <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{item.product_name}</p>
                        {item.variant_name && (
                          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Variant: {item.variant_name}</p>
                        )}
                        <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Artist: {item.artist_name || 'N/A'}</p>
                      </div>
                      <div className="text-right">
                        <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Qty: {item.quantity}</p>
                        <p className={`font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>₱{Number(item.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                      </div>
                    </div>
                  ))}
                </div>
                <div className={`mt-4 p-4 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
                  <div className="flex justify-between items-center">
                    <span className={`text-lg font-semibold ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Total:</span>
                    <span className={`text-2xl font-bold ${isDarkMode ? 'text-purple-400' : 'text-purple-600'}`}>₱{orderDetails?.total ? Number(orderDetails.total).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00'}</span>
                  </div>
                </div>
              </div>

              {/* Payment Information */}
              <div className="mb-6">
                <h4 className={`text-lg font-semibold mb-3 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Payment Information</h4>
                <div className={`p-4 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
                  <div className="mb-2">
                    <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Payment Method:</span>
                    <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{orderDetails.payment_method?.toUpperCase() || 'N/A'}</p>
                  </div>
                  {orderDetails.proof_path && (
                    <div>
                      <span className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Proof of Payment:</span>
                      <div className="mt-2">
                        <img 
                          src={`https://cr8.dcism.org/${orderDetails.proof_path}`}
                          alt="Proof of Payment"
                          className="max-w-md w-full h-auto rounded-lg border cursor-pointer"
                          onClick={() => window.open(`https://cr8.dcism.org/${orderDetails.proof_path}`, '_blank')}
                          onError={(e) => {
                            const target = e.currentTarget;
                            if (!target.dataset.errorHandled) {
                              target.dataset.errorHandled = 'true';
                              target.src = '/img/avatar-placeholder.png';
                            }
                          }}
                        />
                      </div>
                    </div>
                  )}
                  {!orderDetails.proof_path && (
                    <p className={`text-sm italic ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>No proof of payment uploaded</p>
                  )}
                </div>
              </div>

              {/* Delivery Status */}
              {orderDetails.delivery && orderDetails.delivery.length > 0 && (
                <div className="mb-6">
                  <h4 className={`text-lg font-semibold mb-3 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Delivery Status</h4>
                  <div className={`p-4 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
                    {orderDetails.delivery.map((d: any, index: number) => (
                      <div key={index} className={index > 0 ? 'mt-3 pt-3 border-t border-gray-600' : ''}>
                        <div className="flex justify-between items-start">
                          <div>
                            <p className={`font-medium ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>{d.status}</p>
                            {d.tracking_number && (
                              <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Tracking: {d.tracking_number}</p>
                            )}
                            {d.cancel_reason && (
                              <p className={`text-sm text-red-500`}>Reason: {d.cancel_reason}</p>
                            )}
                          </div>
                          <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                            {new Date(d.updated_at).toLocaleString()}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Proof of Delivery */}
              {orderDetails.proof_delivery && (
                <div className="mb-6">
                  <h4 className={`text-lg font-semibold mb-3 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>Proof of Delivery</h4>
                  <div className={`p-4 rounded-lg ${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'}`}>
                    <img 
                      src={`https://cr8.dcism.org/${orderDetails.proof_delivery}`}
                      alt="Proof of Delivery"
                      className="max-w-md w-full h-auto rounded-lg border cursor-pointer"
                      onClick={() => window.open(`https://cr8.dcism.org/${orderDetails.proof_delivery}`, '_blank')}
                      onError={(e) => {
                        const target = e.currentTarget;
                        if (!target.dataset.errorHandled) {
                          target.dataset.errorHandled = 'true';
                          target.src = '/img/avatar-placeholder.png';
                        }
                      }}
                    />
                  </div>
                </div>
              )}

              <div className="flex justify-end gap-3">
                <button
                  onClick={() => {
                    setShowDetailsModal(false);
                    setOrderDetails(null);
                  }}
                  className={`px-4 py-2 rounded-md ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Refund Modal */}
      {showRefundModal && selectedOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className={`rounded-lg shadow-xl p-6 max-w-md w-full ${isDarkMode ? 'bg-gray-900' : 'bg-white'}`}>
            <h3 className={`text-xl font-bold mb-4 ${isDarkMode ? 'text-white' : 'text-gray-800'}`}>
              Process Refund for {selectedOrder.order_no}
            </h3>
            
            <div className="mb-4">
              <p className={`text-sm mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                Total Amount: <span className="font-bold">₱{Number(selectedOrder.total).toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
              </p>
              {selectedOrder.cancel_reason && (
                <p className={`text-sm mb-3 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  Cancellation Reason: <span className="italic">{selectedOrder.cancel_reason}</span>
                </p>
              )}
            </div>

            <div className="mb-4">
              <label className={`block text-sm font-semibold mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                Upload Refund Proof (Screenshot/Receipt)
              </label>
              <input
                type="file"
                accept="image/*"
                onChange={(e) => setRefundProofFile(e.target.files?.[0] || null)}
                className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"
              />
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => {
                  setShowRefundModal(false);
                  setRefundProofFile(null);
                }}
                className={`flex-1 px-4 py-2 rounded-md ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                disabled={uploadingRefund}
              >
                Cancel
              </button>
              <button
                onClick={handleRefundUpload}
                disabled={!refundProofFile || uploadingRefund}
                className="flex-1 px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
              >
                {uploadingRefund ? 'Uploading...' : 'Confirm Refund'}
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
