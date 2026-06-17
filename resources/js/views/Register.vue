<template>
  <div class="register-container">
    <div class="card register-card">
      <h2>Registrazione</h2>
      <form @submit.prevent="handleRegister">
        <div class="form-group">
          <label for="nome">Nome</label>
          <input type="text" id="nome" v-model="form.nome" required />
        </div>

        <div class="form-group">
          <label for="cognome">Cognome</label>
          <input type="text" id="cognome" v-model="form.cognome" required />
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" v-model="form.email" required />
        </div>

        <div class="form-group">
          <label for="telefono">Telefono</label>
          <input type="tel" id="telefono" v-model="form.telefono" />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" v-model="form.password" required minlength="8" />
        </div>

        <div class="form-group">
          <label for="password_confirmation">Conferma Password</label>
          <input type="password" id="password_confirmation" v-model="form.password_confirmation" required minlength="8" />
        </div>

        <!-- GDPR / Trattamento dati personali -->
        <div class="gdpr-box">
          <details class="gdpr-details">
            <summary class="gdpr-summary">
              Informativa sulla Privacy (GDPR Reg. UE 2016/679)
            </summary>
            <div v-if="privacyLoading" class="gdpr-loading">Caricamento informativa…</div>
            <iframe v-else-if="privacyHtml" class="gdpr-body" :srcdoc="privacyIframeSrc" frameborder="0" scrolling="auto"></iframe>
            <p v-else class="gdpr-fallback">
              Trattamento dei dati personali secondo il GDPR Regolamento Europeo UE 2016/679.
            </p>
          </details>
          <label class="gdpr-option" :class="{ 'gdpr-option--selected': privacyOk === true }">
            <input type="radio" :value="true" v-model="privacyOk" /> Acconsento
          </label>
          <label class="gdpr-option" :class="{ 'gdpr-option--selected': privacyOk === false }">
            <input type="radio" :value="false" v-model="privacyOk" /> Non acconsento
          </label>
        </div>

        <div v-if="error" class="error-message">{{ error }}</div>

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
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router    = useRouter()
const authStore = useAuthStore()

const form = ref({
  nome: '',
  cognome: '',
  email: '',
  telefono: '',
  password: '',
  password_confirmation: '',
})

const error         = ref('')
const privacyOk     = ref(null)
const privacyHtml   = ref('')
const privacyLoading = ref(false)

const privacyIframeSrc = computed(() =>
  `<!DOCTYPE html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:.75rem;font-size:.82rem;line-height:1.55;color:#333}</style></head><body>${privacyHtml.value}</body></html>`
)

onMounted(() => {
  privacyLoading.value = true
  api.get('/auth/privacy')
    .then(r => { privacyHtml.value = r.data.contenuto_body ?? '' })
    .catch(() => {})
    .finally(() => { privacyLoading.value = false })
})

const handleRegister = async () => {
  error.value = ''

  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Le password non corrispondono.'
    return
  }

  if (privacyOk.value !== true) {
    error.value = 'Devi acconsentire al trattamento dei dati personali per registrarti.'
    return
  }

  try {
    await authStore.register({ ...form.value, privacy_ok: true })
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Errore durante la registrazione.'
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

/* GDPR */
.gdpr-box {
  margin: 1.25rem 0;
  border: 1px solid #d0d0d0;
  border-radius: 6px;
  overflow: hidden;
}

.gdpr-details {
  background: #f8f8f8;
}

.gdpr-summary {
  cursor: pointer;
  padding: .65rem .9rem;
  font-weight: 600;
  font-size: .88rem;
  list-style: none;
  user-select: none;
}

.gdpr-summary::-webkit-details-marker { display: none; }
.gdpr-summary::before { content: '▶ '; font-size: .75rem; }
details[open] .gdpr-summary::before { content: '▼ '; }

.gdpr-body {
  width: 100%;
  height: 200px;
  border: none;
  border-top: 1px solid #e0e0e0;
  display: block;
}

.gdpr-loading,
.gdpr-fallback {
  padding: .75rem .9rem;
  font-size: .85rem;
  color: #555;
  margin: 0;
}

.gdpr-option {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .55rem .9rem;
  cursor: pointer;
  font-size: .9rem;
  border-top: 1px solid #e8e8e8;
  transition: background .15s;
}

.gdpr-option:hover { background: #f0f0f0; }
.gdpr-option--selected { background: #eaf4ff; font-weight: 600; }
</style>
