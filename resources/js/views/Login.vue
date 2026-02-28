<template>
  <div class="login-container">
    <div class="card login-card">
      <h2>Login</h2>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">Email</label>
          <input 
            type="email" 
            id="email" 
            v-model="form.email" 
            required
          />
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input 
            type="password" 
            id="password" 
            v-model="form.password" 
            required
          />
        </div>
        
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
          Accedi
        </button>
      </form>
      
      <div class="register-link">
        <p>Non hai un account? <router-link to="/register">Registrati</router-link></p>
      </div>
      
      <div v-if="keycloakEnabled" class="keycloak-login">
        <hr />
        <div class="btn btn-keycloak" @click="loginWithKeycloak">
          Accedi con Keycloak
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: '',
  password: ''
})

const error = ref('')
const keycloakEnabled = ref(false)

onMounted(async () => {
  const response = await api.get('/auth/provider')
  keycloakEnabled.value = response.data.driver === 'keycloak'
  
  // Check for error in URL params
  const urlParams = new URLSearchParams(window.location.search)
  const errorParam = urlParams.get('error')
  if (errorParam === 'unauthorized_role') {
    error.value = 'Non hai i permessi necessari per accedere a questa applicazione.'
  } else if (errorParam) {
    error.value = 'Errore durante l\'autenticazione. Riprova.'
  }
})

const handleLogin = async () => {
  try {
    error.value = ''
    await authStore.login(form.value)
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Errore durante il login'
  }
}

const loginWithKeycloak = () => {
  window.location.assign('/auth/keycloak')
}
</script>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 80vh;
}

.login-card {
  width: 100%;
  max-width: 400px;
}

.login-card h2 {
  margin-bottom: 1.5rem;
  text-align: center;
}

.btn-block {
  width: 100%;
  margin-top: 1rem;
}

.register-link {
  text-align: center;
  margin-top: 1rem;
}

.register-link a {
  color: #3498db;
  text-decoration: none;
}

.error-message {
  color: #e74c3c;
  margin: 1rem 0;
  padding: 0.75rem;
  background-color: #fadbd8;
  border-radius: 4px;
}

.keycloak-login {
  margin-top: 1.5rem;
}

.keycloak-login hr {
  margin: 1rem 0;
}

.btn-keycloak {
  width: 100%;
  background-color: #4d4d4d;
  color: white;
  display: block;
  text-align: center;
  cursor: pointer;
  text-decoration: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  border: none;
}

.btn-keycloak:hover {
  background-color: #333;
}
</style>
