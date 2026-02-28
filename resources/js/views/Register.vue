<template>
  <div class="register-container">
    <div class="card register-card">
      <h2>Registrazione</h2>
      <form @submit.prevent="handleRegister">
        <div class="form-group">
          <label for="nome">Nome</label>
          <input 
            type="text" 
            id="nome" 
            v-model="form.nome" 
            required
          />
        </div>
        
        <div class="form-group">
          <label for="cognome">Cognome</label>
          <input 
            type="text" 
            id="cognome" 
            v-model="form.cognome" 
            required
          />
        </div>
        
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
          <label for="telefono">Telefono</label>
          <input 
            type="tel" 
            id="telefono" 
            v-model="form.telefono"
          />
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input 
            type="password" 
            id="password" 
            v-model="form.password" 
            required
            minlength="8"
          />
        </div>
        
        <div class="form-group">
          <label for="password_confirmation">Conferma Password</label>
          <input 
            type="password" 
            id="password_confirmation" 
            v-model="form.password_confirmation" 
            required
            minlength="8"
          />
        </div>
        
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
          Registrati
        </button>
      </form>
      
      <div class="login-link">
        <p>Hai già un account? <router-link to="/login">Accedi</router-link></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  nome: '',
  cognome: '',
  email: '',
  telefono: '',
  password: '',
  password_confirmation: ''
})

const error = ref('')

const handleRegister = async () => {
  try {
    error.value = ''
    
    if (form.value.password !== form.value.password_confirmation) {
      error.value = 'Le password non corrispondono'
      return
    }
    
    await authStore.register(form.value)
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Errore durante la registrazione'
  }
}
</script>

<style scoped>
.register-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 80vh;
}

.register-card {
  width: 100%;
  max-width: 500px;
}

.register-card h2 {
  margin-bottom: 1.5rem;
  text-align: center;
}

.btn-block {
  width: 100%;
  margin-top: 1rem;
}

.login-link {
  text-align: center;
  margin-top: 1rem;
}

.login-link a {
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
</style>
