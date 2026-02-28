import api from '@/api'

export const eventiApi = {
  // Lista eventi di un ente
  index: (enteId, params = {}) =>
    api.get(`/enti/${enteId}/eventi`, { params }),

  // Singolo evento
  show: (enteId, eventoId) =>
    api.get(`/enti/${enteId}/eventi/${eventoId}`),

  // Crea evento
  store: (enteId, data) =>
    api.post(`/enti/${enteId}/eventi`, data),

  // Aggiorna evento
  update: (enteId, eventoId, data) =>
    api.put(`/enti/${enteId}/eventi/${eventoId}`, data),

  // Elimina evento
  destroy: (enteId, eventoId) =>
    api.delete(`/enti/${enteId}/eventi/${eventoId}`),

  // Azioni stato
  pubblica: (enteId, eventoId) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/pubblica`),
  sospendi: (enteId, eventoId) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/sospendi`),
  annulla: (enteId, eventoId, data = {}) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/annulla`, data),
}

export const sessioniApi = {
  index: (enteId, eventoId, params = {}) =>
    api.get(`/enti/${enteId}/eventi/${eventoId}/sessioni`, { params }),
  show: (enteId, eventoId, sessioneId) =>
    api.get(`/enti/${enteId}/eventi/${eventoId}/sessioni/${sessioneId}`),
  store: (enteId, eventoId, data) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/sessioni`, data),
  update: (enteId, eventoId, sessioneId, data) =>
    api.put(`/enti/${enteId}/eventi/${eventoId}/sessioni/${sessioneId}`, data),
  destroy: (enteId, eventoId, sessioneId) =>
    api.delete(`/enti/${enteId}/eventi/${eventoId}/sessioni/${sessioneId}`),
}

export const tipologiePostoApi = {
  index: (enteId, eventoId) =>
    api.get(`/enti/${enteId}/eventi/${eventoId}/tipologie-posto`),
  store: (enteId, eventoId, data) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/tipologie-posto`, data),
  update: (enteId, eventoId, id, data) =>
    api.put(`/enti/${enteId}/eventi/${eventoId}/tipologie-posto/${id}`, data),
  destroy: (enteId, eventoId, id) =>
    api.delete(`/enti/${enteId}/eventi/${eventoId}/tipologie-posto/${id}`),
}

export const campiFormApi = {
  index: (enteId, eventoId) =>
    api.get(`/enti/${enteId}/eventi/${eventoId}/campi-form`),
  store: (enteId, eventoId, data) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/campi-form`, data),
  update: (enteId, eventoId, id, data) =>
    api.put(`/enti/${enteId}/eventi/${eventoId}/campi-form/${id}`, data),
  destroy: (enteId, eventoId, id) =>
    api.delete(`/enti/${enteId}/eventi/${eventoId}/campi-form/${id}`),
  riordina: (enteId, eventoId, ordine) =>
    api.post(`/enti/${enteId}/eventi/${eventoId}/campi-form/riordina`, { ordine }),
}
