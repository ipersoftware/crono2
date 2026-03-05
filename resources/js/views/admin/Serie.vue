<template>
  <div>
    <div class="page-header">
      <h1>📚 Serie</h1>
      <button @click="apriModal()" class="btn btn-primary">+ Nuova serie</button>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="serie.length === 0" class="empty">Nessuna serie. Le serie raggruppano più eventi correlati.</div>
      <table v-else class="table">
        <thead>
          <tr><th>Titolo</th><th>Descrizione</th><th>N° eventi</th><th>Azioni</th></tr>
        </thead>
        <tbody>
          <tr v-for="s in serie" :key="s.id">
            <td data-label="Titolo">{{ s.titolo }}</td>
            <td data-label="Descrizione" class="muted">{{ s.descrizione?.slice(0, 80) }}{{ s.descrizione?.length > 80 ? '…' : '' }}</td>
            <td data-label="N° eventi">{{ s.eventi_count ?? '–' }}</td>
            <td data-label="Azioni" class="actions">
              <button @click="apriModal(s)" class="btn btn-sm btn-primary">Modifica</button>
              <button @click="elimina(s)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal-box">
        <h2>{{ form.id ? 'Modifica serie' : 'Nuova serie' }}</h2>
        <form @submit.prevent="salva">
          <div class="form-group">
            <label>Titolo *</label>
            <input v-model="form.titolo" required class="input" />
          </div>
          <div class="form-group">
            <label>Descrizione</label>
            <textarea v-model="form.descrizione" rows="3" class="input"></textarea>
          </div>
          <div v-if="errore" class="alert-error">{{ errore }}</div>
          <div class="modal-actions">
            <button type="button" @click="modal = false" class="btn btn-secondary">Annulla</button>
            <button type="submit" :disabled="saving" class="btn btn-primary">
              {{ saving ? 'Salvataggio…' : 'Salva' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { serieApi } from '@/api/admin'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const serie   = ref([])
const loading = ref(false)
const modal   = ref(false)
const saving  = ref(false)
const errore  = ref('')
const form    = reactive({ id: null, titolo: '', descrizione: '' })

const carica = async () => {
  loading.value = true
  try {
    const res = await serieApi.index(enteId)
    serie.value = res.data.data ?? res.data
  } finally { loading.value = false }
}

const apriModal = (s = null) => {
  Object.assign(form, { id: null, titolo: '', descrizione: '' })
  if (s) Object.assign(form, { id: s.id, titolo: s.titolo, descrizione: s.descrizione ?? '' })
  errore.value = ''
  modal.value  = true
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    if (form.id) {
      const res = await serieApi.update(enteId, form.id, form)
      const idx = serie.value.findIndex(s => s.id === form.id)
      if (idx !== -1) serie.value[idx] = res.data
    } else {
      const res = await serieApi.store(enteId, form)
      serie.value.push(res.data)
    }
    modal.value = false
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore.'
  } finally { saving.value = false }
}

const elimina = async (s) => {
  if (!confirm(`Eliminare la serie "${s.titolo}"?`)) return
  await serieApi.destroy(enteId, s.id)
  serie.value = serie.value.filter(x => x.id !== s.id)
}

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .5rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.muted { color: #999; font-size: .85rem; max-width: 300px; }
.actions { display: flex; gap: .4rem; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-box { background: white; border-radius: 12px; padding: 2rem; width: 460px; max-width: 95vw; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.25rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }

@media (max-width: 640px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
  .table tr { border: 1px solid #e8eaed; border-radius: 8px; margin-bottom: .75rem; padding: .5rem .75rem; background: white; }
  .table td { display: flex; justify-content: space-between; align-items: center; gap: .5rem; padding: .4rem 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
  .table td:last-child { border-bottom: none; }
  .table td::before { content: attr(data-label); font-weight: 600; color: #777; font-size: .78rem; white-space: nowrap; }
  .actions { justify-content: flex-end; }
  .modal-actions { flex-direction: column-reverse; }
  .modal-actions button { width: 100%; }
}
</style>
