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
                  {{ user.attivo ? 'Attivo' : 'Disattivato' }}
                </span>
              </td>
              <td>
                <button @click="deleteUser(user.id)" class="btn btn-danger btn-sm">
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
          
          <div class="form-group">
            <label>Password</label>
            <input type="password" v-model="newUser.password" required minlength="8" />
          </div>
          
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
          
          <div class="modal-actions">
            <button type="button" @click="showModal = false" class="btn">
              Annulla
            </button>
            <button type="submit" class="btn btn-primary">
              Crea
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
const newUser = ref({
  nome: '',
  cognome: '',
  email: '',
  password: '',
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
  await api.post('/users', newUser.value)
  showModal.value = false
  newUser.value = {
    nome: '',
    cognome: '',
    email: '',
    password: '',
    role: 'utente',
    ente_id: null
  }
  await fetchUsers()
}

const deleteUser = async (id) => {
  if (confirm('Sei sicuro di voler eliminare questo utente?')) {
    await api.delete(`/users/${id}`)
    await fetchUsers()
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
</style>
