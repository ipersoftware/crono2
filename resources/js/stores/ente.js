import api from '@/api'
import { defineStore } from 'pinia'

export const useEnteStore = defineStore('ente', {
  state: () => ({
    ente: null,
    enti: [],
  }),

  getters: {
    enteId: (state) => state.ente?.id ?? null,
  },

  actions: {
    async fetchEnti() {
      const response = await api.get('/enti')
      this.enti = response.data.data ?? response.data
    },

    async fetchEnte(id) {
      const response = await api.get(`/enti/${id}`)
      this.ente = response.data
    },

    setEnte(ente) {
      this.ente = ente
    },

    clear() {
      this.ente = null
    },
  },
})
