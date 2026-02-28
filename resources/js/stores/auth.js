import api from '@/api'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role === 'admin'
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
      try {
        const response = await api.post('/auth/logout')
        
        // Clear local state
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        
        console.log('Logout completed, token removed:', !localStorage.getItem('token'))
        
        // Return keycloak logout URL if provided
        return response.data.keycloak_logout_url || null
      } catch (error) {
        console.error('Logout error:', error)
        // Clear local state even if API call fails
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        return null
      }
    },

    async fetchUser() {
      const response = await api.get('/auth/me')
      this.user = response.data.user
    }
  }
})
