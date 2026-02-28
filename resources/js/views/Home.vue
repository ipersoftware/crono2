<template>
  <div>
    <div class="card">
      <h1>👋 Benvenuto in Crono2</h1>

      <div v-if="user" class="user-info">
        <p><strong>Nome:</strong> {{ user.nome }} {{ user.cognome }}</p>
        <p><strong>Email:</strong> {{ user.email }}</p>
        <p><strong>Ruolo:</strong> {{ user.role }}</p>
        <p v-if="user.ente"><strong>Ente:</strong> {{ user.ente.nome }}</p>
      </div>

      <div class="quick-links">
        <h2>Menu rapido</h2>
        <div class="links-grid">

          <!-- Admin ente -->
          <template v-if="enteId">
            <router-link :to="`/admin/${enteId}/eventi`" class="link-card blue">
              <h3>📋 Eventi</h3>
              <p>Gestisci gli eventi del tuo ente</p>
            </router-link>
            <router-link :to="`/admin/${enteId}/prenotazioni`" class="link-card green">
              <h3>🎟 Prenotazioni</h3>
              <p>Visualizza e gestisci le prenotazioni</p>
            </router-link>
            <router-link :to="`/admin/${enteId}/luoghi`" class="link-card orange">
              <h3>📍 Luoghi</h3>
              <p>Gestisci le sedi degli eventi</p>
            </router-link>
            <router-link :to="`/admin/${enteId}/tags`" class="link-card purple">
              <h3>🏷 Tag</h3>
              <p>Categorie e filtri degli eventi</p>
            </router-link>
          </template>

          <!-- Super admin -->
          <template v-if="isAdmin">
            <router-link to="/users" class="link-card gray">
              <h3>👥 Utenti</h3>
              <p>Gestisci gli utenti del sistema</p>
            </router-link>
            <router-link to="/enti" class="link-card gray">
              <h3>🏢 Enti</h3>
              <p>Gestisci gli enti</p>
            </router-link>
          </template>

          <!-- Utente base -->
          <router-link to="/prenotazioni/mie" class="link-card teal">
            <h3>🗓 Le mie prenotazioni</h3>
            <p>Visualizza le tue prenotazioni</p>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, onMounted, ref } from 'vue'

const authStore = useAuthStore()
const user = ref(null)

const isAdmin = computed(() => authStore.user?.role === 'admin')
const enteId = computed(() => authStore.user?.ente_id ?? null)

onMounted(async () => {
  if (!authStore.user) await authStore.fetchUser()
  user.value = authStore.user
})
</script>

<style scoped>
h1 { margin-bottom: 1.5rem; }
.user-info {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 2rem;
}
.user-info p { margin: .4rem 0; }
.quick-links h2 { margin-bottom: 1rem; }
.links-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1rem;
}
.link-card {
  padding: 1.4rem;
  border-radius: 10px;
  text-decoration: none;
  color: white;
  transition: transform .15s, box-shadow .15s;
  display: block;
}
.link-card:hover { transform: translateY(-3px); box-shadow: 0 6px 14px rgba(0,0,0,.15); }
.link-card h3 { margin: 0 0 .4rem 0; }
.link-card p  { margin: 0; opacity: .9; font-size: .875rem; }
.blue   { background: #3498db; }
.green  { background: #27ae60; }
.orange { background: #e67e22; }
.purple { background: #8e44ad; }
.teal   { background: #16a085; }
.gray   { background: #7f8c8d; }
</style>
