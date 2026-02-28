import api from '@/api'

// Tags
export const tagsApi = {
  index: (enteId, params = {}) =>
    api.get(`/enti/${enteId}/tags`, { params }),
  store: (enteId, data) =>
    api.post(`/enti/${enteId}/tags`, data),
  update: (enteId, id, data) =>
    api.put(`/enti/${enteId}/tags/${id}`, data),
  destroy: (enteId, id) =>
    api.delete(`/enti/${enteId}/tags/${id}`),
}

// Luoghi
export const luoghiApi = {
  index: (enteId, params = {}) =>
    api.get(`/enti/${enteId}/luoghi`, { params }),
  show: (enteId, id) =>
    api.get(`/enti/${enteId}/luoghi/${id}`),
  store: (enteId, data) =>
    api.post(`/enti/${enteId}/luoghi`, data),
  update: (enteId, id, data) =>
    api.put(`/enti/${enteId}/luoghi/${id}`, data),
  destroy: (enteId, id) =>
    api.delete(`/enti/${enteId}/luoghi/${id}`),
}

// Serie
export const serieApi = {
  index: (enteId, params = {}) =>
    api.get(`/enti/${enteId}/serie`, { params }),
  store: (enteId, data) =>
    api.post(`/enti/${enteId}/serie`, data),
  update: (enteId, id, data) =>
    api.put(`/enti/${enteId}/serie/${id}`, data),
  destroy: (enteId, id) =>
    api.delete(`/enti/${enteId}/serie/${id}`),
}

// Mail templates
export const mailTemplatesApi = {
  index: (enteId) =>
    api.get(`/enti/${enteId}/mail-templates`),
  show: (enteId, tipo) =>
    api.get(`/enti/${enteId}/mail-templates/${tipo}`),
  store: (enteId, data) =>
    api.post(`/enti/${enteId}/mail-templates`, data),
  update: (enteId, id, data) =>
    api.put(`/enti/${enteId}/mail-templates/${id}`, data),
  destroy: (enteId, id) =>
    api.delete(`/enti/${enteId}/mail-templates/${id}`),
  anteprima: (enteId, id, dati = {}) =>
    api.get(`/enti/${enteId}/mail-templates/${id}/anteprima`, { params: { dati } }),
}
