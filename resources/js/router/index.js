import { useAuthStore } from '@/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  // ──────────────────────────────────────────────
  // Auth
  // ──────────────────────────────────────────────
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/Register.vue'),
  },
  {
    path: '/auth/callback',
    name: 'AuthCallback',
    component: () => import('@/views/AuthCallback.vue'),
  },

  // ──────────────────────────────────────────────
  // Area autenticata
  // ──────────────────────────────────────────────
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/users',
    name: 'Users',
    component: () => import('@/views/Users.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/enti',
    name: 'Enti',
    component: () => import('@/views/Enti.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },

  // ──────────────────────────────────────────────
  // Admin Ente
  // ──────────────────────────────────────────────
  {
    path: '/admin/:enteId/eventi',
    name: 'AdminEventi',
    component: () => import('@/views/admin/Eventi.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/eventi/nuovo',
    name: 'AdminEventoNuovo',
    component: () => import('@/views/admin/EventoForm.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/eventi/:eventoId',
    name: 'AdminEventoModifica',
    component: () => import('@/views/admin/EventoForm.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/eventi/:eventoId/sessioni',
    name: 'AdminSessioni',
    component: () => import('@/views/admin/Sessioni.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/prenotazioni',
    name: 'AdminPrenotazioni',
    component: () => import('@/views/admin/Prenotazioni.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/tags',
    name: 'AdminTags',
    component: () => import('@/views/admin/Tags.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/luoghi',
    name: 'AdminLuoghi',
    component: () => import('@/views/admin/Luoghi.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/serie',
    name: 'AdminSerie',
    component: () => import('@/views/admin/Serie.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin/:enteId/mail-templates',
    name: 'AdminMailTemplates',
    component: () => import('@/views/admin/MailTemplates.vue'),
    meta: { requiresAuth: true },
  },

  // ──────────────────────────────────────────────
  // Prenotazioni utente
  // ──────────────────────────────────────────────
  {
    path: '/prenotazioni/mie',
    name: 'MiePrenotazioni',
    component: () => import('@/views/utente/MiePrenotazioni.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/prenotazioni/:codice',
    name: 'DettaglioPrenotazione',
    component: () => import('@/views/utente/DettaglioPrenotazione.vue'),
  },

  // ──────────────────────────────────────────────
  // Vetrina pubblica
  // ──────────────────────────────────────────────
  {
    path: '/vetrina/:shopUrl',
    name: 'VetrinaHome',
    component: () => import('@/views/vetrina/Home.vue'),
  },
  {
    path: '/vetrina/:shopUrl/eventi/:slug',
    name: 'VetrinaEvento',
    component: () => import('@/views/vetrina/EventoDettaglio.vue'),
  },
  {
    path: '/vetrina/:shopUrl/prenota/:slug/:sessioneId',
    name: 'Booking',
    component: () => import('@/views/vetrina/Booking.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }
  if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
    next('/')
    return
  }
  next()
})

export default router
