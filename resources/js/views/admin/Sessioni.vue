<template>
  <div>
    <div class="page-header">
      <h1>{{ evento ? `✏️ Modifica evento` : 'Sessioni' }}</h1>
      <router-link :to="`/admin/${enteId}/eventi`" class="btn btn-secondary">← Torna agli eventi</router-link>
    </div>

    <!-- Tab navigation -->
    <div class="tabs">
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}`" class="tab-btn">📝 Dettagli</router-link>
      <span class="tab-btn active">🗓 Sessioni</span>
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}?tab=tipologie`" class="tab-btn">🪑 Tipologie posto</router-link>
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}?tab=form`" class="tab-btn">📋 Campi form</router-link>
    </div>

    <div class="page-subheader">
      <span>🗓 Sessioni — <em>{{ evento?.titolo }}</em></span>
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
            <th>Posti totali</th>
            <th>Disponibili</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in sessioni" :key="s.id">
            <td>{{ formatDateTime(s.data_inizio) }}</td>
            <td>{{ formatDateTime(s.data_fine) }}</td>
            <td>{{ s.posti_totali ?? '∞' }}</td>
            <td>{{ s.posti_disponibili ?? '—' }}</td>
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
              <input v-model="form.data_inizio" type="datetime-local" class="input" required />
            </div>
            <div class="form-group">
              <label>Fine</label>
              <input v-model="form.data_fine" type="datetime-local" class="input" />
            </div>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label>Posti totali (0 o vuoto = illimitati)</label>
              <input v-model.number="form.posti_totali" type="number" min="0" class="input" placeholder="0 = illimitati" />
            </div>
            <div class="form-group">
              <label>Durata lock prenotazione (minuti)</label>
              <input v-model.number="form.durata_lock_minuti" type="number" min="1" class="input" placeholder="es. 15" />
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
                <input type="checkbox" v-model="form.prenotabile" />
                Prenotabile
              </label>
            </div>
          </div>

          <!-- Posti per tipologia (mostrati solo se l'evento ha tipologie) -->
          <div v-if="form.tipologie_posto.length > 0" class="form-group">
            <label>Posti per tipologia</label>
            <table class="table-tipologie">
              <thead>
                <tr><th>Tipologia</th><th>Posti totali (0=∞)</th><th>Attiva</th></tr>
              </thead>
              <tbody>
                <tr v-for="tp in form.tipologie_posto" :key="tp.tipologia_posto_id">
                  <td>{{ tp.nome }}</td>
                  <td><input v-model.number="tp.posti_totali" type="number" min="0" class="input input-sm" /></td>
                  <td style="text-align:center"><input type="checkbox" v-model="tp.attiva" /></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="form-group">
            <label>Note pubbliche</label>
            <textarea v-model="form.note_pubbliche" rows="2" class="input"></textarea>
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
  id: null, data_inizio: '', data_fine: '', posti_totali: null,
  controlla_posti_globale: true, prenotabile: true,
  durata_lock_minuti: null, note_pubbliche: '',
  tipologie_posto: [],
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

  // Inizializza tipologie da evento con valori di default
  form.tipologie_posto = (evento.value?.tipologie_posto ?? []).map(t => ({
    tipologia_posto_id: t.id,
    nome: t.nome,
    posti_totali: 0,
    attiva: true,
  }))

  if (s) {
    Object.assign(form, {
      id: s.id,
      data_inizio: s.data_inizio?.slice(0, 16) ?? '',
      data_fine:   s.data_fine?.slice(0, 16) ?? '',
      posti_totali: s.posti_totali,
      controlla_posti_globale: s.controlla_posti_globale,
      prenotabile: s.prenotabile ?? true,
      durata_lock_minuti: s.durata_lock_minuti ?? null,
      note_pubbliche: s.note_pubbliche ?? '',
    })
    // Sovrascrive i posti per tipologia con i valori già salvati
    form.tipologie_posto = form.tipologie_posto.map(tp => {
      const saved = (s.tipologie_posto ?? []).find(x => x.tipologia_posto_id === tp.tipologia_posto_id)
      return saved ? { ...tp, posti_totali: saved.posti_totali, attiva: saved.attiva ?? true } : tp
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
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .75rem; }
.page-subheader { display: flex; justify-content: space-between; align-items: center; margin: 1.25rem 0 1rem; font-size: 1.1rem; font-weight: 600; }
.page-subheader em { font-weight: 400; }
.tabs { display: flex; gap: .5rem; margin-bottom: 1.25rem; border-bottom: 2px solid #eee; padding-bottom: .5rem; }
.tab-btn {
  padding: .5rem 1.1rem; border: none; border-radius: 6px 6px 0 0;
  background: #f4f4f4; color: #555; cursor: pointer; font-size: .9rem;
  text-decoration: none; display: inline-block; transition: background .15s;
}
.tab-btn:hover { background: #e0e0e0; }
.tab-btn.active { background: #3498db; color: white; font-weight: 600; }
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
.table-tipologie { width: 100%; border-collapse: collapse; font-size: .88rem; margin-top: .25rem; }
.table-tipologie th, .table-tipologie td { padding: .35rem .5rem; border-bottom: 1px solid #eee; }
.table-tipologie th { font-weight: 600; text-align: left; background: #f8f9fa; }
.input-sm { width: 90px; padding: .3rem .5rem; }
</style>
