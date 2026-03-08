import api from '@/api'

export const prenotazioniApi = {
  // Flusso pubblico
  lock: (data) =>
    api.post('/prenotazioni/lock', data),

  rilasciaLock: (token) =>
    api.delete(`/prenotazioni/lock/${token}`),

  store: (data) =>
    api.post('/prenotazioni', data),

  mie: (params = {}) =>
    api.get('/prenotazioni/mie', { params }),

  show: (codice, token = null) =>
    api.get(`/prenotazioni/${codice}`, { params: token ? { token } : {} }),

  annullaUtente: (codice, token = null, motivo = '') =>
    api.delete(`/prenotazioni/${codice}`, { params: token ? { token } : {}, data: { motivo_annullamento: motivo } }),

  // Admin
  indexAdmin: (enteId, params = {}) =>
    api.get(`/enti/${enteId}/prenotazioni`, { params }),

  approva: (enteId, prenotazioneId) =>
    api.patch(`/enti/${enteId}/prenotazioni/${prenotazioneId}/approva`),

  annullaAdmin: (enteId, prenotazioneId, motivo = '') =>
    api.delete(`/enti/${enteId}/prenotazioni/${prenotazioneId}`, { data: { motivo_annullamento: motivo } }),

  // Lista d'attesa
  iscriviListaAttesa: (data) =>
    api.post('/prenotazioni/lista-attesa', data),

  confermaListaAttesa: (token) =>
    api.post(`/lista-attesa/${token}/conferma`),
}
