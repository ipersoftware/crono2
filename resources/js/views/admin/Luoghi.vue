<template>
  <div>
    <div class="page-header">
      <h1>📍 Luoghi</h1>
      <button @click="apriModal()" class="btn btn-primary">+ Nuovo luogo</button>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="luoghi.length === 0" class="empty">Nessun luogo. Aggiungine uno.</div>
      <table v-else class="table">
        <thead>
          <tr><th>Nome</th><th>Indirizzo</th><th>Stato</th><th>Azioni</th></tr>
        </thead>
        <tbody>
          <tr v-for="l in luoghi" :key="l.id">
            <td>{{ l.nome }}</td>
            <td class="muted">{{ l.indirizzo }}</td>
            <td>
              <span :class="['badge', l.stato === 'ATTIVO' ? 'badge-attivo' : 'badge-inattivo']">
                {{ l.stato }}
              </span>
            </td>
            <td class="actions">
              <button @click="apriModal(l)" class="btn btn-sm btn-primary">Modifica</button>
              <button @click="elimina(l)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal-box">
        <h2>{{ form.id ? 'Modifica luogo' : 'Nuovo luogo' }}</h2>
        <form @submit.prevent="salva">
          <div class="form-group">
            <label>Nome *</label>
            <input v-model="form.nome" required class="input" />
          </div>
          <div class="form-group">
            <label>Indirizzo</label>
            <input v-model="form.indirizzo" class="input" />
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label>Latitudine</label>
              <input v-model.number="form.lat" type="number" step="any" class="input" />
            </div>
            <div class="form-group">
              <label>Longitudine</label>
              <input v-model.number="form.lng" type="number" step="any" class="input" />
            </div>
          </div>
          <div class="form-group">
            <label>URL Google Maps</label>
            <input v-model="form.maps_url" class="input" placeholder="https://maps.google.com/…" />
          </div>
          <div class="form-group">
            <label>Stato</label>
            <select v-model="form.stato" class="input">
              <option value="ATTIVO">Attivo</option>
              <option value="INATTIVO">Inattivo</option>
            </select>
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
import { luoghiApi } from '@/api/admin'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const luoghi  = ref([])
const loading = ref(false)
const modal   = ref(false)
const saving  = ref(false)
const errore  = ref('')
const form    = reactive({ id: null, nome: '', indirizzo: '', lat: null, lng: null, maps_url: '', stato: 'ATTIVO' })

const carica = async () => {
  loading.value = true
  try {
    const res = await luoghiApi.index(enteId)
    luoghi.value = res.data.data ?? res.data
  } finally { loading.value = false }
}

const apriModal = (l = null) => {
  Object.assign(form, { id: null, nome: '', indirizzo: '', lat: null, lng: null, maps_url: '', stato: 'ATTIVO' })
  if (l) Object.assign(form, { id: l.id, nome: l.nome, indirizzo: l.indirizzo, lat: l.lat, lng: l.lng, maps_url: l.maps_url, stato: l.stato })
  errore.value = ''
  modal.value  = true
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    if (form.id) {
      const res = await luoghiApi.update(enteId, form.id, form)
      const idx = luoghi.value.findIndex(l => l.id === form.id)
      if (idx !== -1) luoghi.value[idx] = res.data
    } else {
      const res = await luoghiApi.store(enteId, form)
      luoghi.value.push(res.data)
    }
    modal.value = false
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore.'
  } finally { saving.value = false }
}

const elimina = async (l) => {
  if (!confirm(`Eliminare il luogo "${l.nome}"?`)) return
  await luoghiApi.destroy(enteId, l.id)
  luoghi.value = luoghi.value.filter(x => x.id !== l.id)
}

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.muted { color: #999; font-size: .85rem; }
.actions { display: flex; gap: .4rem; }
.badge { padding: .22rem .55rem; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.badge-attivo  { background: #d5f5e3; color: #1a7a45; }
.badge-inattivo{ background: #eee; color: #555; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-box { background: white; border-radius: 12px; padding: 2rem; width: 520px; max-width: 95vw; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.25rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }
</style>
