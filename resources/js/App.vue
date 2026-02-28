<template>
  <div id="app">
    <nav v-if="isAuthenticated" class="navbar">
      <div class="nav-container">
        <router-link to="/" class="nav-brand">🗓 Crono2</router-link>

        <div class="nav-links">
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
          </template>

          <!-- Utente autenticato -->
          <router-link to="/prenotazioni/mie">Le mie prenotazioni</router-link>

          <button @click="logout" class="btn-logout">Esci</button>
        </div>
      </div>
    </nav>

    <!-- Banner impersonificazione -->
    <div v-if="isImpersonating" class="impersonate-banner">
      <span>👁 Stai impersonificando: <strong>{{ authStore.impersonatingEnte?.nome }}</strong></span>
      <button @click="stopImpersonate" class="btn-stop-impersonate">✕ Termina impersonificazione</button>
    </div>

    <main>
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)
const isAdmin = computed(() => authStore.user?.role === 'admin')
const isImpersonating = computed(() => authStore.isImpersonating)

// Durante impersonificazione mostra i link ente dell'ente impersonificato
const enteId = computed(() => {
  if (authStore.isImpersonating) return authStore.impersonatingEnte?.id ?? null
  return authStore.user?.ente_id ?? null
})

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.user) {
    await authStore.fetchUser()
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
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-container h1 {
  font-size: 1.5rem;
}

.nav-links {
  display: flex;
  gap: 1.5rem;
  align-items: center;
}

.nav-links a {
  color: white;
  text-decoration: none;
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
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 1rem;
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
