<template>
  <div>
    <div class="header">
      <h1>Gestione Enti</h1>
      <button @click="showModal = true" class="btn btn-primary">
        + Nuovo Ente
      </button>
    </div>
    
    <div class="card">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Codice Fiscale</th>
              <th>Email</th>
              <th>Città</th>
              <th>Stato</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ente in enti" :key="ente.id">
              <td>{{ ente.nome }}</td>
              <td>{{ ente.codice_fiscale }}</td>
              <td>{{ ente.email }}</td>
              <td>{{ ente.citta || '-' }}</td>
              <td>
                <span :class="['badge', ente.attivo ? 'badge-success' : 'badge-danger']">
                  {{ ente.attivo ? 'Attivo' : 'Disattivato' }}
                </span>
              </td>
              <td>
                <button @click="deleteEnte(ente.id)" class="btn btn-danger btn-sm">
                  Elimina
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Modal Nuovo Ente -->
    <div v-if="showModal" class="modal">
      <div class="modal-content">
        <h2>Nuovo Ente</h2>
        <form @submit.prevent="createEnte">
          <div class="form-group">
            <label>Nome *</label>
            <input v-model="newEnte.nome" required />
          </div>
          
          <div class="form-group">
            <label>Codice Fiscale *</label>
            <input v-model="newEnte.codice_fiscale" required maxlength="16" />
          </div>
          
          <div class="form-group">
            <label>Partita IVA</label>
            <input v-model="newEnte.partita_iva" maxlength="11" />
          </div>
          
          <div class="form-group">
            <label>Email *</label>
            <input type="email" v-model="newEnte.email" required />
          </div>
          
          <div class="form-group">
            <label>Telefono</label>
            <input v-model="newEnte.telefono" />
          </div>
          
          <div class="form-group">
            <label>Indirizzo</label>
            <input v-model="newEnte.indirizzo" />
          </div>
          
          <div class="form-group">
            <label>Città</label>
            <input v-model="newEnte.citta" />
          </div>
          
          <div class="form-group">
            <label>Provincia</label>
            <input v-model="newEnte.provincia" maxlength="2" />
          </div>
          
          <div class="form-group">
            <label>CAP</label>
            <input v-model="newEnte.cap" maxlength="5" />
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

const enti = ref([])
const showModal = ref(false)
const newEnte = ref({
  nome: '',
  codice_fiscale: '',
  partita_iva: '',
  email: '',
  telefono: '',
  indirizzo: '',
  citta: '',
  provincia: '',
  cap: '',
  attivo: true
})

const fetchEnti = async () => {
  const response = await api.get('/enti')
  enti.value = response.data.data
}

const createEnte = async () => {
  await api.post('/enti', newEnte.value)
  showModal.value = false
  newEnte.value = {
    nome: '',
    codice_fiscale: '',
    partita_iva: '',
    email: '',
    telefono: '',
    indirizzo: '',
    citta: '',
    provincia: '',
    cap: '',
    attivo: true
  }
  await fetchEnti()
}

const deleteEnte = async (id) => {
  if (confirm('Sei sicuro di voler eliminare questo ente?')) {
    await api.delete(`/enti/${id}`)
    await fetchEnti()
  }
}

onMounted(() => {
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
  max-width: 600px;
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
