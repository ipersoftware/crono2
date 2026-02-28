<template>
  <div class="callback-container">
    <div class="card">
      <div v-if="loading" class="loading">
        <p>Autenticazione in corso...</p>
      </div>
      <div v-else-if="error" class="error">
        <h3>Errore di autenticazione</h3>
        <p>{{ error }}</p>
        <router-link to="/login" class="btn btn-primary">Torna al login</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const token = route.query.token
    const errorParam = route.query.error

    if (errorParam) {
      const errorMessages = {
        'no_code': 'Nessun codice di autorizzazione ricevuto',
        'account_disabled': 'Il tuo account è stato disattivato',
        'auth_failed': 'Autenticazione fallita. Riprova.'
      }
      error.value = errorMessages[errorParam] || 'Errore sconosciuto'
      loading.value = false
      return
    }

    if (!token) {
      error.value = 'Nessun token ricevuto'
      loading.value = false
      return
    }

    // Set token in store
    authStore.token = token
    localStorage.setItem('token', token)

    // Fetch user data
    await authStore.fetchUser()

    // Redirect to dashboard or home based on user role
    router.push('/')
  } catch (err) {
    console.error('Auth callback error:', err)
    error.value = 'Errore durante il completamento dell\'autenticazione'
    loading.value = false
  }
})
</script>

<style scoped>
.callback-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 80vh;
}

.card {
  max-width: 400px;
  text-align: center;
}

.loading {
  padding: 2rem;
}

.error {
  padding: 2rem;
}

.error h3 {
  color: #e74c3c;
  margin-bottom: 1rem;
}

.error p {
  margin-bottom: 1.5rem;
}

.btn {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  text-decoration: none;
}
</style>
