<template>
  <div>
    <div class="card">
      <h1>Benvenuto in Ermes</h1>
      <div v-if="user" class="user-info">
        <p><strong>Nome:</strong> {{ user.nome }} {{ user.cognome }}</p>
        <p><strong>Email:</strong> {{ user.email }}</p>
        <p><strong>Ruolo:</strong> {{ user.role }}</p>
        <p v-if="user.ente"><strong>Ente:</strong> {{ user.ente.nome }}</p>
      </div>
      
      <div class="quick-links">
        <h2>Menu Rapido</h2>
        <div class="links-grid">
          <router-link v-if="isAdmin" to="/users" class="link-card">
            <h3>👥 Utenti</h3>
            <p>Gestisci gli utenti del sistema</p>
          </router-link>
          
          <router-link v-if="isAdmin" to="/enti" class="link-card">
            <h3>🏢 Enti</h3>
            <p>Gestisci gli enti</p>
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

onMounted(async () => {
  if (!authStore.user) {
    await authStore.fetchUser()
  }
  user.value = authStore.user
})
</script>

<style scoped>
h1 {
  margin-bottom: 1.5rem;
}

.user-info {
  background-color: #f8f9fa;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 2rem;
}

.user-info p {
  margin: 0.5rem 0;
}

.quick-links h2 {
  margin-bottom: 1rem;
}

.links-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1rem;
}

.link-card {
  background-color: #3498db;
  color: white;
  padding: 1.5rem;
  border-radius: 8px;
  text-decoration: none;
  transition: transform 0.2s, box-shadow 0.2s;
}

.link-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.link-card h3 {
  margin: 0 0 0.5rem 0;
}

.link-card p {
  margin: 0;
  opacity: 0.9;
  font-size: 0.9rem;
}
</style>
