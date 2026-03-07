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
              <td data-label="Nome">{{ ente.nome }}</td>
              <td data-label="Codice Fiscale">{{ ente.codice_fiscale }}</td>
              <td data-label="Email">{{ ente.email }}</td>
              <td data-label="Città">{{ ente.citta || '-' }}</td>
              <td data-label="Stato">
                <span :class="['badge', ente.attivo ? 'badge-success' : 'badge-danger']">
                  {{ ente.attivo ? 'Attivo' : 'Disattivato' }}
                </span>
              </td>
              <td data-label="Azioni" class="actions-cell">
                <button
                  v-if="!authStore.isImpersonating"
                  @click="impersonaEnte(ente)"
                  :disabled="!ente.attivo"
                  class="btn btn-warning btn-sm"
                  title="Impersona ente"
                >
                  👁 Impersona
                </button>
                <button
                  @click="sincronizzaTemplate(ente)"
                  :disabled="sincronizzandoId === ente.id"
                  class="btn btn-info btn-sm"
                  title="Copia/aggiorna i template mail di sistema per questo ente"
                >
                  {{ sincronizzandoId === ente.id ? '⏳…' : '📧 Template mail' }}
                </button>
                <button
                  @click="apriPrivacyModal(ente)"
                  class="btn btn-secondary btn-sm"
                  title="Configura URL informativa privacy"
                >
                  🔒 Privacy URL
                </button>
                <button
                  @click="toggleFormContatti(ente)"
                  :class="['btn', 'btn-sm', ente.form_contatti_attivo ? 'btn-success' : 'btn-secondary']"
                  :title="ente.form_contatti_attivo ? 'Disabilita form contatti vetrina' : 'Abilita form contatti vetrina'"
                >
                  {{ ente.form_contatti_attivo ? '✉️ Contatti ON' : '✉️ Contatti OFF' }}
                </button>
                <button
                  v-if="ente.governance_id"
                  @click="aggiornaAnagraficaGovernance(ente)"
                  :disabled="aggiornandoId === ente.id"
                  class="btn btn-gov btn-sm"
                  title="Aggiorna nome, email, indirizzo, ecc. dal database Governance"
                >
                  {{ aggiornandoId === ente.id ? '⏳…' : '🔄 Governance' }}
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

    <!-- Modal Privacy URL -->
    <div v-if="privacyModal.show" class="modal">
      <div class="modal-content">
        <h2>🔒 Privacy URL — {{ privacyModal.ente?.nome }}</h2>
        <p class="modal-desc">URL della pagina informativa sulla privacy (link &ldquo;Maggiori informazioni&rdquo; mostrato in fase di prenotazione).</p>
        <div class="form-group">
          <label>URL Informativa Privacy</label>
          <input
            v-model="privacyModal.url"
            type="url"
            class="input"
            placeholder="https://..."
          />
        </div>
        <div v-if="privacyModal.errore" class="modal-error">{{ privacyModal.errore }}</div>
        <div class="modal-actions">
          <button type="button" @click="privacyModal.show = false" class="btn">Annulla</button>
          <button type="button" @click="salvaPrivacyUrl" :disabled="privacyModal.saving" class="btn btn-primary">
            {{ privacyModal.saving ? 'Salvataggio…' : 'Salva' }}
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

// ───────────────────────── Sincronizza template mail ─────────────────────────
const sincronizzandoId = ref(null)

const sincronizzaTemplate = async (ente) => {
  if (!confirm(`Vuoi copiare/aggiornare i template mail di sistema per "${ente.nome}"?\nI template esistenti verranno sovrascritti con i valori di default.`)) return
  sincronizzandoId.value = ente.id
  try {
    const res = await api.post(`/enti/${ente.id}/sincronizza-template`)
    alert(res.data.message)
  } catch (e) {
    alert('Errore: ' + (e.response?.data?.message || e.message))
  } finally {
    sincronizzandoId.value = null
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

// ──────────────────────────── Privacy URL ────────────────────────────
const privacyModal = ref({ show: false, ente: null, url: '', errore: '', saving: false })

const apriPrivacyModal = (ente) => {
  privacyModal.value = { show: true, ente, url: ente.privacy_url ?? '', errore: '', saving: false }
}

const salvaPrivacyUrl = async () => {
  privacyModal.value.errore = ''
  privacyModal.value.saving = true
  try {
    await api.put(`/enti/${privacyModal.value.ente.id}`, { privacy_url: privacyModal.value.url || null })
    // Aggiorna locale
    const idx = enti.value.findIndex(e => e.id === privacyModal.value.ente.id)
    if (idx !== -1) enti.value[idx].privacy_url = privacyModal.value.url || null
    privacyModal.value.show = false
  } catch (e) {
    privacyModal.value.errore = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    privacyModal.value.saving = false
  }
}

// ──────────────────────────── Aggiorna anagrafica da Governance ────────────────────────────
const aggiornandoId = ref(null)

const aggiornaAnagraficaGovernance = async (ente) => {
  if (!confirm(`Aggiornare i dati anagrafici di "${ente.nome}" con i valori presenti in Governance?\n(nome, email, telefono, indirizzo, città, provincia, CAP)`)) return
  aggiornandoId.value = ente.id
  try {
    const res = await api.post(`/enti/${ente.id}/aggiorna-da-governance`)
    alert(res.data.message)
    await fetchEnti()
  } catch (e) {
    alert('Errore: ' + (e.response?.data?.message || e.message))
  } finally {
    aggiornandoId.value = null
  }
}

// ──────────────────────────── Form contatti ────────────────────────────
const toggleFormContatti = async (ente) => {
  const nuovoValore = !ente.form_contatti_attivo
  try {
    await api.put(`/enti/${ente.id}`, { form_contatti_attivo: nuovoValore })
    const idx = enti.value.findIndex(e => e.id === ente.id)
    if (idx !== -1) enti.value[idx].form_contatti_attivo = nuovoValore
  } catch (e) {
    alert('Errore durante l\'aggiornamento: ' + (e.response?.data?.message ?? e.message))
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

/* ── Responsive mobile ── */
@media (max-width: 768px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }

  .header > div {
    width: 100%;
    flex-direction: column;
  }

  .header .btn {
    width: 100%;
    text-align: center;
  }

  .table-container {
    overflow-x: unset;
  }

  .table thead {
    display: none;
  }

  .table,
  .table tbody,
  .table tr,
  .table td {
    display: block;
    width: 100%;
  }

  .table tr {
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 1rem;
    padding: 0.5rem 0.25rem;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
  }

  .table td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    gap: 0.5rem;
  }

  .table td:last-child {
    border-bottom: none;
  }

  .table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #555;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    min-width: 110px;
    flex-shrink: 0;
  }

  .actions-cell {
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .actions-cell::before {
    align-self: flex-start;
    margin-top: 0.2rem;
  }

  .btn-sm {
    font-size: 0.8rem;
    padding: 0.3rem 0.5rem;
  }
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

.btn-info {
  background-color: #3498db;
  color: white;
}

.btn-info:hover {
  background-color: #2980b9;
}

.btn-info:disabled {
  background-color: #85c1e9;
  cursor: not-allowed;
}

.btn-gov {
  background-color: #6f42c1;
  color: white;
}

.btn-gov:hover {
  background-color: #5a32a3;
}

.btn-gov:disabled {
  background-color: #b39ddb;
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

.modal-desc { font-size: .88rem; color: #555; margin: 0 0 1rem; }
.modal-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .6rem .8rem; margin-top: .5rem; font-size: .88rem; }
.input { width: 100%; padding: .45rem .6rem; border: 1px solid #ccc; border-radius: 5px; font-size: .95rem; box-sizing: border-box; }
</style>
