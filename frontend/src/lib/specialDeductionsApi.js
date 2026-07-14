import { api } from './api';

export const specialDeductionsApi = {
  getAll: () => api('/special-deductions', { method: 'GET' }),
  create: (data) => api('/special-deductions', { method: 'POST', body: data }),
  delete: (id) => api(`/special-deductions/${id}`, { method: 'DELETE' }),
};
