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

  annullaUtente: (codice, token = null) =>
    api.delete(`/prenotazioni/${codice}`, { params: token ? { token } : {} }),

  // Admin
  indexAdmin: (enteId, params = {}) =>
    api.get(`/enti/${enteId}/prenotazioni`, { params }),

  approva: (enteId, prenotazioneId) =>
    api.patch(`/enti/${enteId}/prenotazioni/${prenotazioneId}/approva`),

  annullaAdmin: (enteId, prenotazioneId) =>
    api.delete(`/enti/${enteId}/prenotazioni/${prenotazioneId}`),
}
