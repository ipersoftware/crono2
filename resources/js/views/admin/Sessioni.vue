<template>
  <div>
    <div class="page-header">
      <div>
        <router-link :to="`/admin/${enteId}/eventi`" class="back-link">← eventi</router-link>
        <h1>🗓 Sessioni — <em>{{ evento?.titolo }}</em></h1>
      </div>
      <button @click="apriModal()" class="btn btn-primary">+ Nuova sessione</button>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="sessioni.length === 0" class="empty">Nessuna sessione. Creane una!</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Inizio</th>
            <th>Fine</th>
            <th>Stato</th>
            <th>Posti totali</th>
            <th>Prenotati</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in sessioni" :key="s.id">
            <td>{{ formatDateTime(s.inizio_at) }}</td>
            <td>{{ formatDateTime(s.fine_at) }}</td>
            <td><span :class="['badge', `badge-${s.stato?.toLowerCase()}`]">{{ s.stato }}</span></td>
            <td>{{ s.posti_totali ?? '∞' }}</td>
            <td>{{ s.posti_prenotati }}</td>
            <td class="actions">
              <button @click="apriModal(s)" class="btn btn-sm btn-primary">Modifica</button>
              <button @click="elimina(s)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal sessione -->
    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal-box">
        <h2>{{ form.id ? 'Modifica sessione' : 'Nuova sessione' }}</h2>
        <form @submit.prevent="salva">
          <div class="grid-2">
            <div class="form-group">
              <label>Inizio *</label>
              <input v-model="form.inizio_at" type="datetime-local" class="input" required />
            </div>
            <div class="form-group">
              <label>Fine</label>
              <input v-model="form.fine_at" type="datetime-local" class="input" />
            </div>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label>Posti totali (vuoto = illimitati)</label>
              <input v-model.number="form.posti_totali" type="number" min="0" class="input" placeholder="es. 100" />
            </div>
            <div class="form-group">
              <label>Stato</label>
              <select v-model="form.stato" class="input">
                <option value="BOZZA">Bozza</option>
                <option value="APERTA">Aperta</option>
                <option value="CHIUSA">Chiusa</option>
                <option value="ANNULLATA">Annullata</option>
              </select>
            </div>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.controlla_posti_globale" />
                Controlla posti a livello globale
              </label>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.overbooking" />
                Overbooking consentito
              </label>
            </div>
          </div>

          <div class="form-group">
            <label>Note interne</label>
            <textarea v-model="form.note" rows="2" class="input"></textarea>
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
import { eventiApi, sessioniApi } from '@/api/eventi'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route   = useRoute()
const enteId  = route.params.enteId
const eventoId = route.params.eventoId

const sessioni = ref([])
const evento   = ref(null)
const loading  = ref(false)
const modal    = ref(false)
const saving   = ref(false)
const errore   = ref('')

const formDefault = () => ({
  id: null, inizio_at: '', fine_at: '', posti_totali: null,
  stato: 'BOZZA', controlla_posti_globale: true, overbooking: false, note: '',
})
const form = reactive(formDefault())

const carica = async () => {
  loading.value = true
  try {
    const [evRes, sRes] = await Promise.all([
      eventiApi.show(enteId, eventoId),
      sessioniApi.index(enteId, eventoId),
    ])
    evento.value  = evRes.data
    sessioni.value = sRes.data.data ?? sRes.data
  } finally {
    loading.value = false
  }
}

const apriModal = (s = null) => {
  Object.assign(form, formDefault())
  if (s) {
    Object.assign(form, {
      id: s.id,
      inizio_at: s.inizio_at?.slice(0, 16) ?? '',
      fine_at:   s.fine_at?.slice(0, 16) ?? '',
      posti_totali: s.posti_totali,
      stato: s.stato,
      controlla_posti_globale: s.controlla_posti_globale,
      overbooking: s.overbooking,
      note: s.note ?? '',
    })
  }
  errore.value = ''
  modal.value  = true
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    if (form.id) {
      const res = await sessioniApi.update(enteId, eventoId, form.id, form)
      const idx = sessioni.value.findIndex(s => s.id === form.id)
      if (idx !== -1) sessioni.value[idx] = res.data
    } else {
      const res = await sessioniApi.store(enteId, eventoId, form)
      sessioni.value.push(res.data)
    }
    modal.value = false
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    saving.value = false
  }
}

const elimina = async (s) => {
  if (!confirm('Eliminare questa sessione?')) return
  await sessioniApi.destroy(enteId, eventoId, s.id)
  sessioni.value = sessioni.value.filter(x => x.id !== s.id)
}

const formatDateTime = (d) => {
  if (!d) return '–'
  return new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.back-link { font-size: .85rem; color: #3498db; text-decoration: none; display: block; margin-bottom: .25rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.actions { display: flex; gap: .4rem; }
.badge { padding: .22rem .55rem; border-radius: 10px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
.badge-bozza    { background: #eee; color: #555; }
.badge-aperta   { background: #d5f5e3; color: #1a7a45; }
.badge-chiusa   { background: #d6eaf8; color: #1a5276; }
.badge-annullata{ background: #fadbd8; color: #a93226; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-box { background: white; border-radius: 12px; padding: 2rem; width: 600px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
.modal-box h2 { margin-bottom: 1.25rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.25rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }
</style>
