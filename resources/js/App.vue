<template>
  <div id="app">
    <nav v-if="isAuthenticated && !isLanding" class="navbar">
      <div class="nav-container">
        <router-link to="/dashboard" class="nav-brand">🗓 Crono2</router-link>

        <button class="nav-hamburger" @click="menuAperto = !menuAperto" :aria-expanded="menuAperto">
          <span></span><span></span><span></span>
        </button>

        <div class="nav-links" :class="{ 'nav-links--open': menuAperto }" @click="menuAperto = false">
          <!-- Dashboard — sempre visibile -->
          <router-link to="/dashboard">Dashboard</router-link>

          <!-- Admin sistema -->
          <template v-if="isAdmin">
            <router-link to="/users">Utenti</router-link>
            <router-link to="/enti">Enti</router-link>
          </template>

          <!-- Admin ente (utente legato a un ente) -->
          <template v-if="enteId">
            <router-link :to="`/admin/${enteId}/eventi`">📋 Eventi</router-link>
            <router-link :to="`/admin/${enteId}/prenotazioni`">🎟 Prenotazioni</router-link>
            <router-link :to="`/admin/${enteId}/tags`">🏷 Tag</router-link>
            <router-link :to="`/admin/${enteId}/luoghi`">📍 Luoghi</router-link>
            <router-link :to="`/admin/${enteId}/serie`">📚 Serie</router-link>
            <router-link :to="`/admin/${enteId}/mail-templates`">✉ Mail</router-link>
            <router-link :to="`/admin/${enteId}/vetrina`">🏪 Vetrina</router-link>
            <router-link v-if="isAdminEnte" :to="`/admin/${enteId}/accessi-log`">🔐 Accessi</router-link>
            <router-link v-if="isAdminEnte" :to="`/admin/${enteId}/notifiche-log`">✉ Log mail</router-link>
            <router-link :to="`/admin/${enteId}/statistiche`">📊 Statistiche</router-link>
            <a v-if="ermesUrl" :href="ermesUrl" target="_blank" rel="noopener" class="nav-ermes-link">📨 Ermes</a>
          </template>

          <!-- Solo ruolo 'utente' -->
          <router-link v-if="isUtente" to="/prenotazioni/mie">Le mie prenotazioni</router-link>

          <button @click="logout" class="btn-logout">Esci</button>
        </div>
      </div>
    </nav>

    <!-- Banner impersonificazione -->
    <div v-if="isImpersonating && !isLanding" class="impersonate-banner">
      <span>👁 Stai impersonificando: <strong>{{ authStore.impersonatingEnte?.nome }}</strong></span>
      <button @click="stopImpersonate" class="btn-stop-impersonate">✕ Termina impersonificazione</button>
    </div>

    <router-view v-if="isLanding" />
    <main v-else>
      <router-view />  
    </main>
  </div>
</template>

<script setup>
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const menuAperto = ref(false)

const isLanding = computed(() => ['Landing', 'VetrinaHome', 'VetrinaEvento', 'Booking'].includes(route.name))

const isAuthenticated = computed(() => authStore.isAuthenticated)
const isAdmin      = computed(() => authStore.user?.role === 'admin')
const isAdminEnte  = computed(() => ['admin', 'admin_ente'].includes(authStore.user?.role))
const isUtente     = computed(() => authStore.user?.role === 'utente')
const isImpersonating = computed(() => authStore.isImpersonating)

// Durante impersonificazione mostra i link ente dell'ente impersonificato
const enteId = computed(() => {
  if (authStore.isImpersonating) return authStore.impersonatingEnte?.id ?? null
  return authStore.user?.ente_id ?? null
})

const ermesUrl = ref(null)

const caricaErmesStatus = async (id) => {
  if (!id) return
  try {
    const res = await api.get(`/enti/${id}/newsletter/ermes-attivo`)
    ermesUrl.value = res.data.attivo ? (res.data.ermes_url || null) : null
  } catch { ermesUrl.value = null }
}

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.user) {
    await authStore.fetchUser()
  }
  if (enteId.value) {
    caricaErmesStatus(enteId.value)
  }
})

const logout = async () => {
  const keycloakLogoutUrl = await authStore.logout()
  if (keycloakLogoutUrl) {
    window.location.href = keycloakLogoutUrl
  } else {
    window.location.replace('/login')
  }
}

const stopImpersonate = async () => {
  await authStore.stopImpersonate()
  router.push('/')
}
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  background-color: #f5f5f5;
}

#app {
  min-height: 100vh;
}

.navbar {
  background-color: #2c3e50;
  color: white;
  padding: 1rem 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.nav-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 0 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: nowrap;
  gap: 1rem;
}

.nav-brand {
  color: white !important;
  text-decoration: none;
  font-weight: 700;
  font-size: 1.1rem;
  white-space: nowrap;
  flex-shrink: 0;
  opacity: 1;
}

.nav-brand:hover {
  opacity: .8;
}

.nav-links {
  display: flex;
  gap: .75rem;
  align-items: center;
  flex-shrink: 1;
  overflow-x: auto;
  scrollbar-width: none;
}
.nav-links::-webkit-scrollbar { display: none; }

.nav-links a {
  color: white;
  text-decoration: none;
  font-size: .875rem;
  white-space: nowrap;
  transition: opacity 0.2s;
}

.nav-links a:hover {
  opacity: 0.8;
}

.nav-links a.router-link-active {
  border-bottom: 2px solid white;
}

.btn-logout {
  background-color: #e74c3c;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-logout:hover {
  background-color: #c0392b;
}

.nav-ermes-link {
  color: #f39c12;
  text-decoration: none;
  font-weight: 600;
  padding: 0.4rem 0.75rem;
  border-radius: 4px;
  border: 1px solid #f39c12;
  transition: background-color 0.2s, color 0.2s;
}

.nav-ermes-link:hover {
  background-color: #f39c12;
  color: white;
}

.nav-hamburger {
  display: none;
  flex-direction: column;
  justify-content: space-between;
  width: 28px;
  height: 20px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}
.nav-hamburger span {
  display: block;
  height: 3px;
  background: white;
  border-radius: 2px;
  transition: opacity .2s;
}

@media (max-width: 768px) {
  .nav-hamburger { display: flex; }

  .nav-links {
    display: none;
    flex-direction: column;
    align-items: flex-start;
    gap: 0;
    width: 100%;
    padding: .5rem 0 .75rem;
    border-top: 1px solid rgba(255,255,255,.15);
    margin-top: .25rem;
  }
  .nav-links--open { display: flex; }

  .nav-links a, .nav-links button {
    width: 100%;
    padding: .65rem .5rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
    font-size: 1rem;
  }
  .nav-links a.router-link-active {
    border-bottom-color: rgba(255,255,255,.08);
    background: rgba(255,255,255,.08);
    border-radius: 4px;
  }
  .btn-logout {
    border-radius: 4px;
    margin-top: .25rem;
    text-align: left;
  }

  .impersonate-banner {
    flex-direction: column;
    gap: .5rem;
    text-align: center;
    padding: .75rem 1rem;
  }
}

.impersonate-banner {
  background-color: #f39c12;
  color: white;
  padding: 0.6rem 1rem;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1.5rem;
  font-size: 0.95rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

.btn-stop-impersonate {
  background-color: #c0392b;
  color: white;
  border: none;
  padding: 0.3rem 0.75rem;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.85rem;
  transition: background-color 0.2s;
}

.btn-stop-impersonate:hover {
  background-color: #922b21;
}

main {
  max-width: 1600px;
  margin: 2rem auto;
  padding: 0 1.25rem;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  transition: background-color 0.2s;
}

.btn-primary {
  background-color: #3498db;
  color: white;
}

.btn-primary:hover {
  background-color: #2980b9;
}

.btn-success {
  background-color: #2ecc71;
  color: white;
}

.btn-danger {
  background-color: #e74c3c;
  color: white;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 1rem;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.table th,
.table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.table th {
  background-color: #f8f9fa;
  font-weight: 600;
}
</style>
