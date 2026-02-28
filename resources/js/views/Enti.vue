<template>
  <div>
    <div class="header">
      <h1>Gestione Enti</h1>
      <div style="display:flex; gap:0.75rem;">
        <button @click="apriModaleGovernance" class="btn btn-secondary">
          ↓ Importa da Governance
        </button>
        <button @click="showModal = true" class="btn btn-primary">
          + Nuovo Ente
        </button>
      </div>
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
              <td class="actions-cell">
                <button
                  v-if="!authStore.isImpersonating"
                  @click="impersonaEnte(ente)"
                  :disabled="!ente.attivo"
                  class="btn btn-warning btn-sm"
                  title="Impersona ente"
                >
                  👁 Impersona
                </button>
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

    <!-- Modal Importa da Governance -->
    <div v-if="showGovernanceModal" class="modal">
      <div class="modal-content">
        <h2>Importa Enti da Governance</h2>

        <!-- Ricerca -->
        <div class="form-group">
          <label>Cerca ente</label>
          <input
            v-model="govSearch"
            @input="cercaGovernance"
            placeholder="Nome, codice fiscale…"
          />
        </div>

        <div v-if="govLoading" class="gov-loading">Caricamento…</div>

        <div v-else-if="govEnti.length === 0 && govSearch.length > 0" class="gov-empty">
          Nessun ente trovato.
        </div>

        <div v-else-if="govEnti.length > 0">
          <!-- Seleziona tutti -->
          <div class="gov-select-all">
            <label>
              <input
                type="checkbox"
                :checked="tuttiSelezionati"
                @change="toggleTutti"
              />
              Seleziona tutti ({{ govEnti.length }})
            </label>
          </div>

          <!-- Lista enti -->
          <div class="gov-list">
            <label v-for="ente in govEnti" :key="ente.governance_id" class="gov-item">
              <input
                type="checkbox"
                :value="ente.governance_id"
                v-model="govSelezionati"
              />
              <span>
                <strong>{{ ente.nome }}</strong>
                <small v-if="ente.codice_fiscale"> — {{ ente.codice_fiscale }}</small>
                <small v-if="ente.citta"> — {{ ente.citta }}</small>
              </span>
            </label>
          </div>
        </div>

        <div v-if="govMessaggio" :class="['gov-msg', govErrore ? 'gov-msg-error' : 'gov-msg-success']">
          {{ govMessaggio }}
        </div>

        <div class="modal-actions">
          <button type="button" @click="chiudiGovernance" class="btn">Chiudi</button>
          <button
            @click="eseguiImportazione"
            :disabled="govSelezionati.length === 0 || govImporting"
            class="btn btn-primary"
          >
            {{ govImporting ? 'Importazione…' : `Importa (${govSelezionati.length})` }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

// ───────────────────────── Enti base ─────────────────────────
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

// ───────────────────────── Impersonificazione ─────────────────────────
const impersonaEnte = async (ente) => {
  if (!confirm(`Vuoi impersonificare l'ente "${ente.nome}"?`)) return
  try {
    await authStore.impersonateEnte(ente.id)
    // Redirige alla home admin dell'ente impersonificato
    router.push(`/admin/${ente.id}/eventi`)
  } catch (e) {
    alert('Errore durante l\'impersonificazione: ' + (e.response?.data?.message || e.message))
  }
}

// ───────────────────────── Import da Governance ─────────────────────────
const showGovernanceModal = ref(false)
const govSearch = ref('')
const govEnti = ref([])
const govSelezionati = ref([])
const govLoading = ref(false)
const govImporting = ref(false)
const govMessaggio = ref('')
const govErrore = ref(false)

let govSearchTimer = null

const tuttiSelezionati = computed(
  () => govEnti.value.length > 0 && govSelezionati.value.length === govEnti.value.length
)

const toggleTutti = () => {
  if (tuttiSelezionati.value) {
    govSelezionati.value = []
  } else {
    govSelezionati.value = govEnti.value.map((e) => e.governance_id)
  }
}

const apriModaleGovernance = () => {
  showGovernanceModal.value = true
  govSearch.value = ''
  govEnti.value = []
  govSelezionati.value = []
  govMessaggio.value = ''
  govErrore.value = false
  caricaGovernance()
}

const chiudiGovernance = () => {
  showGovernanceModal.value = false
}

const caricaGovernance = async () => {
  govLoading.value = true
  try {
    const params = govSearch.value ? { search: govSearch.value } : {}
    const response = await api.get('/enti/governance/disponibili', { params })
    govEnti.value = Array.isArray(response.data) ? response.data : (response.data.data ?? [])
  } catch (e) {
    govMessaggio.value = 'Errore nel caricamento degli enti da Governance.'
    govErrore.value = true
  } finally {
    govLoading.value = false
  }
}

const cercaGovernance = () => {
  clearTimeout(govSearchTimer)
  govSearchTimer = setTimeout(() => {
    govSelezionati.value = []
    caricaGovernance()
  }, 400)
}

const eseguiImportazione = async () => {
  if (govSelezionati.value.length === 0) return
  govImporting.value = true
  govMessaggio.value = ''
  try {
    const response = await api.post('/enti/governance/importa', {
      governance_ids: govSelezionati.value
    })
    const { importati, errori } = response.data
    govMessaggio.value = `Importati: ${importati.length}${errori.length > 0 ? `, Errori: ${errori.length}` : ''}`
    govErrore.value = errori.length > 0
    govSelezionati.value = []
    await fetchEnti()
    await caricaGovernance()
  } catch (e) {
    govMessaggio.value = 'Errore durante l\'importazione: ' + (e.response?.data?.message || e.message)
    govErrore.value = true
  } finally {
    govImporting.value = false
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

.actions-cell {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background-color: #5a6268;
}

.btn-warning {
  background-color: #f39c12;
  color: white;
}

.btn-warning:hover {
  background-color: #d68910;
}

.btn-warning:disabled {
  background-color: #f8c471;
  cursor: not-allowed;
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
  max-width: 640px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
}

/* Governance modal */
.gov-loading,
.gov-empty {
  padding: 1rem 0;
  color: #666;
  font-style: italic;
}

.gov-select-all {
  padding: 0.5rem 0;
  border-bottom: 1px solid #eee;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.gov-list {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 0.5rem;
  margin-bottom: 1rem;
}

.gov-item {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.4rem 0.25rem;
  border-bottom: 1px solid #f5f5f5;
  cursor: pointer;
}

.gov-item:last-child {
  border-bottom: none;
}

.gov-item span {
  flex: 1;
}

.gov-item small {
  color: #888;
}

.gov-msg {
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  margin-bottom: 0.75rem;
}

.gov-msg-success {
  background-color: #d4edda;
  color: #155724;
}

.gov-msg-error {
  background-color: #f8d7da;
  color: #721c24;
}
</style>
