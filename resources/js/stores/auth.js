import api from '@/api'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    // Impersonificazione: token admin originale e ente impersonificato
    adminToken: localStorage.getItem('adminToken') || null,
    impersonatingEnte: JSON.parse(localStorage.getItem('impersonatingEnte') || 'null'),
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin',
    isImpersonating: (state) => !!state.impersonatingEnte,
  },

  actions: {
    async login(credentials) {
      const response = await api.post('/auth/login', credentials)
      this.token = response.data.token
      this.user = response.data.user
      localStorage.setItem('token', this.token)
    },

    async register(userData) {
      const response = await api.post('/auth/register', userData)
      this.token = response.data.token
      this.user = response.data.user
      localStorage.setItem('token', this.token)
    },

    async logout() {
      // Se si è in impersonificazione, terminarla prima
      if (this.isImpersonating) {
        await this.stopImpersonate()
      }
      try {
        const response = await api.post('/auth/logout')
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        return response.data.keycloak_logout_url || null
      } catch (error) {
        console.error('Logout error:', error)
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        return null
      }
    },

    async fetchUser() {
      const response = await api.get('/auth/me')
      this.user = response.data.user
      if (response.data.impersonating_ente) {
        this.impersonatingEnte = response.data.impersonating_ente
        localStorage.setItem('impersonatingEnte', JSON.stringify(this.impersonatingEnte))
      }
    },

    /**
     * Avvia impersonificazione di un ente.
     * Salva il token admin originale e sostituisce il token corrente
     * con quello di impersonificazione restituito dal backend.
     */
    async impersonateEnte(enteId) {
      const response = await api.post(`/enti/${enteId}/impersonate`)
      const { token, impersonating_ente } = response.data

      // Salva token admin originale
      this.adminToken = this.token
      localStorage.setItem('adminToken', this.adminToken)

      // Sostituisci token con quello di impersonificazione
      this.token = token
      localStorage.setItem('token', token)

      this.impersonatingEnte = impersonating_ente
      localStorage.setItem('impersonatingEnte', JSON.stringify(impersonating_ente))
    },

    /**
     * Termina impersonificazione: revoca il token di impersonificazione
     * e ripristina il token admin originale.
     */
    async stopImpersonate() {
      try {
        await api.delete('/auth/impersonate')
      } catch (e) {
        // Il token potrebbe essere già scaduto; si procede comunque al ripristino
        console.warn('Errore revoca token impersonificazione:', e)
      }

      // Ripristina token admin
      this.token = this.adminToken
      localStorage.setItem('token', this.adminToken)

      this.adminToken = null
      this.impersonatingEnte = null
      localStorage.removeItem('adminToken')
      localStorage.removeItem('impersonatingEnte')

      // Ricarica dati utente con token admin
      await this.fetchUser()
    },
  },
})

