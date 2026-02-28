import api from '@/api'

export const vetrinaApi = {
  index: (shopUrl) =>
    api.get(`/vetrina/${shopUrl}`),

  eventi: (shopUrl, params = {}) =>
    api.get(`/vetrina/${shopUrl}/eventi`, { params }),

  evento: (shopUrl, slug) =>
    api.get(`/vetrina/${shopUrl}/eventi/${slug}`),

  serie: (shopUrl) =>
    api.get(`/vetrina/${shopUrl}/serie`),

  tags: (shopUrl) =>
    api.get(`/vetrina/${shopUrl}/tags`),
}
