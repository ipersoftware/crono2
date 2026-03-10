<template>
  <div>
    <div class="header">
      <h1>Gestione Utenti</h1>
      <button @click="showModal = true" class="btn btn-primary">
        + Nuovo Utente
      </button>
    </div>
    
    <div class="card">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Email</th>
              <th>Ruolo</th>
              <th>Ente</th>
              <th>Stato</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>{{ user.cognome }} {{ user.nome }}</td>
              <td>{{ user.email }}</td>
              <td>{{ user.role }}</td>
              <td>{{ user.ente?.nome || '-' }}</td>
              <td>
                <span :class="['badge', user.attivo ? 'badge-success' : 'badge-danger']">
                  {{ user.attivo ? 'Attivo' : 'Sospeso' }}
                </span>
              </td>
              <td class="actions-cell">
                <button @click="toggleAttivo(user)" :class="['btn btn-sm', user.attivo ? 'btn-warning' : 'btn-success']" :title="user.attivo ? 'Sospendi accesso' : 'Riattiva accesso'">
                  {{ user.attivo ? 'Sospendi' : 'Riattiva' }}
                </button>
                <button @click="resetPassword(user.id)" class="btn btn-secondary btn-sm" title="Invia email di reset credenziali">
                  Reset credenziali
                </button>
                <button @click="deleteUser(user.id)" class="btn btn-danger btn-sm" title="Elimina utente">
                  Elimina
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Modal Nuovo Utente -->
    <div v-if="showModal" class="modal">
      <div class="modal-content">
        <h2>Nuovo Utente</h2>
        <form @submit.prevent="createUser">
          <div class="form-group">
            <label>Nome</label>
            <input v-model="newUser.nome" required />
          </div>
          
          <div class="form-group">
            <label>Cognome</label>
            <input v-model="newUser.cognome" required />
          </div>
          
          <div class="form-group">
            <label>Email</label>
            <input type="email" v-model="newUser.email" required />
          </div>
          
          <p class="form-note">ℹ️ La password temporanea verrà generata automaticamente e inviata via email all'utente.</p>
          
          <div class="form-group">
            <label>Ruolo</label>
            <select v-model="newUser.role" required>
              <option value="utente">Utente</option>
              <option value="operatore_ente">Operatore Ente</option>
              <option value="admin_ente">Admin Ente</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          
          <div class="form-group">
            <label>Ente</label>
            <select v-model="newUser.ente_id">
              <option :value="null">Nessuno</option>
              <option v-for="ente in enti" :key="ente.id" :value="ente.id">
                {{ ente.nome }}
              </option>
            </select>
          </div>
          
          <div v-if="formError" class="form-error">
            {{ formError }}
          </div>

          <div class="modal-actions">
            <button type="button" @click="showModal = false; formError = null" class="btn">
              Annulla
            </button>
            <button type="submit" class="btn btn-primary" :disabled="formLoading">
              {{ formLoading ? 'Creazione...' : 'Crea' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import api from '@/api'
import { onMounted, ref } from 'vue'

const users = ref([])
const enti = ref([])
const showModal = ref(false)
const formError = ref(null)
const formLoading = ref(false)
const newUser = ref({
  nome: '',
  cognome: '',
  email: '',
  role: 'utente',
  ente_id: null
})

const fetchUsers = async () => {
  const response = await api.get('/users')
  users.value = response.data.data
}

const fetchEnti = async () => {
  const response = await api.get('/enti')
  enti.value = response.data.data
}

const createUser = async () => {
  formError.value = null
  formLoading.value = true
  try {
    await api.post('/users', newUser.value)
    showModal.value = false
    newUser.value = {
      nome: '',
      cognome: '',
      email: '',
      role: 'utente',
      ente_id: null
    }
    await fetchUsers()
  } catch (err) {
    const data = err.response?.data
    if (err.response?.status === 422 && data?.errors) {
      formError.value = Object.values(data.errors).flat().join(' ')
    } else {
      formError.value = data?.message || 'Errore durante la creazione dell\'utente.'
    }
  } finally {
    formLoading.value = false
  }
}

const deleteUser = async (id) => {
  if (confirm('Sei sicuro di voler eliminare questo utente?')) {
    try {
      await api.delete(`/users/${id}`)
      await fetchUsers()
    } catch (err) {
      alert(err.response?.data?.message || 'Errore durante l\'eliminazione dell\'utente.')
    }
  }
}

const resetPassword = async (id) => {
  if (confirm('Inviare email di reset credenziali all\'utente?')) {
    try {
      await api.post(`/users/${id}/reset-password`)
      alert('Email di reset inviata con successo.')
    } catch (err) {
      alert(err.response?.data?.message || 'Errore durante il reset delle credenziali.')
    }
  }
}

const toggleAttivo = async (user) => {
  const azione = user.attivo ? 'sospendere' : 'riattivare'
  if (confirm(`Sei sicuro di voler ${azione} l'accesso di ${user.nome} ${user.cognome}?`)) {
    try {
      const res = await api.patch(`/users/${user.id}/toggle-attivo`)
      // Aggiorna localmente senza refetch completo
      const idx = users.value.findIndex(u => u.id === user.id)
      if (idx !== -1) users.value[idx] = res.data.user
    } catch (err) {
      alert(err.response?.data?.message || `Errore durante l\'operazione.`)
    }
  }
}

onMounted(() => {
  fetchUsers()
  fetchEnti()
})
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.table-container {
  overflow-x: auto;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.actions-cell {
  display: flex;
  gap: 0.375rem;
  flex-wrap: wrap;
  align-items: center;
}

.btn-warning {
  background-color: #f39c12;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-warning:hover {
  background-color: #d68910;
}

.btn-success {
  background-color: #2ecc71;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-success:hover {
  background-color: #27ae60;
}

.btn-secondary {
  background-color: #95a5a6;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-secondary:hover {
  background-color: #7f8c8d;
}

.form-note {
  font-size: 0.85rem;
  color: #555;
  background: #eaf4ff;
  border-left: 3px solid #1a56db;
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  margin-bottom: 1rem;
}

.badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.875rem;
}

.badge-success {
  background-color: #2ecc71;
  color: white;
}

.badge-danger {
  background-color: #e74c3c;
  color: white;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
}

.form-error {
  background-color: #fde8e8;
  color: #c0392b;
  border: 1px solid #e74c3c;
  border-radius: 4px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}
</style>
