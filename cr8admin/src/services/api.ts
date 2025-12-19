import axios from 'axios';

const API_BASE_URL = 'https://cr8admin.dcism.org/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const authAPI = {
  login: (username: string, password: string) =>
    api.post('/auth.php', { username, password }),
  logout: () => api.post('/auth.php?action=logout'),
  checkSession: () => api.get('/auth.php?action=check'),
};

export const dashboardAPI = {
  getData: (artist_id?: string, period?: string) =>
    api.get('/dashboard.php', { params: { artist_id, period } }),
};

export const ordersAPI = {
  getOrders: (status?: string, search?: string) =>
    api.get('/orders.php', { params: { status, search } }),
  getOrderDetails: (id: number) => api.get(`/orders.php?id=${id}`),
  updateStatus: (order_id: number, status: string, tracking_number?: string) =>
    api.post('/orders.php?action=update_status', { order_id, status, tracking_number }),
  updateTracking: (order_id: number, tracking_number: string) =>
    api.post('/orders.php?action=update_tracking', { order_id, tracking_number }),
  cancelOrder: (order_id: number, cancel_reason: string) =>
    api.post('/orders.php?action=cancel_order', { order_id, cancel_reason }),
  uploadRefundProof: (formData: FormData) =>
    api.post('/orders.php?action=upload_refund', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
};

export const inventoryAPI = {
  getInventory: (params?: {
    artist_id?: string;
    stock?: string;
    search?: string;
    status?: string;
  }) => api.get('/inventory.php', { params }),
  updateProduct: (id: number, data: { quantity?: number; is_active?: number; deactivation_reason?: string }) =>
    api.post('/inventory.php?action=update', { id, ...data }),
};

export const customersAPI = {
  getCustomers: (filter?: string, search?: string) =>
    api.get('/customers.php', { params: { filter, search } }),
  getCustomerDetails: (id: number) => api.get(`/customers.php?id=${id}`),
};

export const artistsAPI = {
  getArtists: (search?: string, filter?: string) => api.get('/artists.php', { params: { search, filter } }),
  getArtistDetails: (id: number) => api.get(`/artists.php?id=${id}`),
  revokeArtist: (artist_id: number, revoke_reason: string) =>
    api.post('/artists.php?action=revoke', { artist_id, revoke_reason }),
  restoreArtist: (artist_id: number) =>
    api.post('/artists.php?action=restore', { artist_id }),
  deleteArtist: (artist_id: number) =>
    api.post('/artists.php?action=delete', { artist_id }),
  createArtistEntry: (user_id: number) =>
    api.post('/artists.php?action=create_entry', { user_id }),
};

export const artistApplicationsAPI = {
  getApplications: (search?: string, filter?: string) => api.get('/artist_applications.php', { params: { search, filter } }),
  getApplicationDetails: (id: number) =>
    api.get(`/artist_applications.php?id=${id}`),
  updateStatus: (application_id: number, status: string, rejection_reason?: string) =>
    api.post('/artist_applications.php?action=update_status', {
      application_id,
      status,
      rejection_reason,
    }),
  restoreApplication: (application_id: number) =>
    api.post('/artist_applications.php?action=restore', { application_id }),
};

export const inboxAPI = {
  getMessages: (search?: string, filter?: string) => api.get('/inbox.php', { params: { search, filter } }),
  getMessageDetails: (id: number) => api.get(`/inbox.php?id=${id}`),
  deleteMessage: (id: number) => api.delete(`/inbox.php?id=${id}`),
  archiveMessage: (id: number) => api.post('/inbox.php?action=archive', { id }),
  restoreMessage: (id: number) => api.post('/inbox.php?action=restore', { id }),
};

export const salesAPI = {
  getSalesData: (artist_id?: string, sort?: string) =>
    api.get('/sales.php', { params: { artist_id, sort } }),
};

export const reportsAPI = {
  getArtists: () => api.get('/reports.php'),
};

export const adminManagementAPI = {
  getAdmins: () => api.get('/admin_management.php'),
  addAdmin: (username: string, password: string) =>
    api.post('/admin_management.php?action=add', { username, password }),
  deleteAdmin: (id: number) => api.delete(`/admin_management.php?id=${id}`),
};

export const notificationsAPI = {
  getCounts: () => api.get('/notifications.php'),
};

export default api;
