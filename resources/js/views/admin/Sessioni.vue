<template>
  <div>
    <div class="page-header">
      <h1>{{ evento?.titolo ? `✏️ ${evento.titolo}` : '✏️ Modifica evento' }}</h1>
      <div style="display:flex;gap:.6rem;align-items:center">
        <a v-if="urlVetrina" :href="urlVetrina" target="_blank" class="btn btn-outline">👁 Vedi in vetrina</a>
        <router-link :to="`/admin/${enteId}/eventi`" class="btn btn-secondary">← Torna agli eventi</router-link>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="tabs">
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}`" class="tab-btn">📝 Dettagli</router-link>
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}?tab=layout`" class="tab-btn">🖋 Layout</router-link>
      <span class="tab-btn active">🗓 Sessioni</span>
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}?tab=tipologie`" class="tab-btn">🪑 Tipologie posto</router-link>
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}?tab=form`" class="tab-btn">📋 Campi form</router-link>
      <router-link :to="`/admin/${enteId}/eventi/${eventoId}?tab=log`" class="tab-btn">🕑 Log attività</router-link>
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
            <th>In attesa</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="s in sessioni" :key="s.id">
            <tr>
              <td data-label="Inizio">{{ formatDateTime(s.data_inizio) }}</td>
              <td data-label="Fine">{{ formatDateTime(s.data_fine) }}</td>
              <td data-label="Posti totali">{{ s.posti_totali ?? '∞' }}</td>
              <td data-label="Disponibili">{{ s.posti_disponibili ?? '—' }}</td>
              <td data-label="In attesa">
                <span v-if="s.in_lista_attesa > 0" class="badge badge-warning">⏳ {{ s.in_lista_attesa }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td data-label="Azioni" class="actions">
                <button @click="apriModal(s)" class="btn btn-sm btn-primary">Modifica</button>
                <button @click="elimina(s)" class="btn btn-sm btn-danger">Elimina</button>
              </td>
            </tr>
            <tr v-if="s.tipologie_posto?.length" class="row-tipologie">
              <td colspan="6">
                <div class="tipologie-breakdown">
                  <span
                    v-for="tp in s.tipologie_posto" :key="tp.id"
                    :class="['tp-badge', tp.attiva ? '' : 'tp-badge--inattiva']"
                  >
                    <span class="tp-nome">{{ tp.tipologia_posto?.nome ?? '–' }}</span>
                    <span class="tp-disp">{{ tp.posti_disponibili ?? '∞' }} / {{ tp.posti_totali || '∞' }}</span>
                  </span>
                </div>
              </td>
            </tr>
            <tr v-if="s.luoghi?.length" class="row-tipologie">
              <td colspan="6">
                <div class="tipologie-breakdown">
                  <span v-for="l in s.luoghi" :key="l.id" class="tp-badge tp-badge--luogo">
                    📍 {{ l.nome }}
                  </span>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Modal sessione -->
    <div v-if="modal" class="modal-backdrop">
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
              <label class="toggle-label">
                <span class="toggle-text">Prenotabile</span>
                <span class="toggle-wrap">
                  <input type="checkbox" v-model="form.prenotabile" class="toggle-input" />
                  <span class="toggle-slider"></span>
                </span>
                <span class="toggle-state">{{ form.prenotabile ? 'Sì' : 'No' }}</span>
              </label>
            </div>
            <div class="form-group">
              <label class="toggle-label">
                <span class="toggle-text" style="color:#c0392b">Forza chiusura</span>
                <span class="toggle-wrap">
                  <input type="checkbox" v-model="form.forza_non_disponibile" class="toggle-input" />
                  <span class="toggle-slider"></span>
                </span>
                <span class="toggle-state" style="color:#c0392b">{{ form.forza_non_disponibile ? 'Sì' : 'No' }}</span>
              </label>
            </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="toggle-label">
                <span class="toggle-text">Rendi non disponibile se prenotazioni raggiungono</span>
                <span class="toggle-wrap">
                  <input type="checkbox"
                    :checked="form.soglia_chiusura_prenotazioni !== null"
                    @change="form.soglia_chiusura_prenotazioni = $event.target.checked ? 1 : null"
                    class="toggle-input" />
                  <span class="toggle-slider"></span>
                </span>
                <span class="toggle-state">{{ form.soglia_chiusura_prenotazioni !== null ? 'Sì' : 'No' }}</span>
              </label>
              <div v-if="form.soglia_chiusura_prenotazioni !== null" style="margin-top:.5rem">
                <input
                  v-model.number="form.soglia_chiusura_prenotazioni"
                  type="number" min="1" class="input"
                  placeholder="es. 1"
                  style="max-width:120px"
                />
                <span style="font-size:.8rem;color:#888;margin-left:.5rem">prenotazioni attive</span>
              </div>
            </div>
          </div>            <div class="form-group">
              <label class="toggle-label">
                <span class="toggle-text">Attiva lista d'attesa</span>
                <span class="toggle-wrap">
                  <input type="checkbox" v-model="form.attiva_lista_attesa" class="toggle-input" />
                  <span class="toggle-slider"></span>
                </span>
                <span class="toggle-state">{{ form.attiva_lista_attesa ? 'Sì' : 'No' }}</span>
              </label>
            </div>
            <div v-if="form.attiva_lista_attesa" class="form-group">
              <label>Tipo conferma lista d'attesa</label>
              <select v-model="form.tipo_conferma" class="form-control">
                <option value="NESSUNA">Nessuna (solo notifica, niente prenotazione)</option>
                <option value="PRENOTAZIONE_AUTOMATICA">Prenotazione automatica</option>
                <option value="PRENOTAZIONE_DA_CONFERMARE">Prenotazione da confermare dall'utente</option>
              </select>
            </div>
            <div v-if="form.attiva_lista_attesa && form.tipo_conferma === 'PRENOTAZIONE_DA_CONFERMARE'" class="form-group">
              <label>Finestra di conferma (ore)</label>
              <input type="number" v-model.number="form.lista_attesa_finestra_conferma_ore"
                     class="form-control" min="1" placeholder="es. 24" />
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

          <div class="form-group">
            <label class="toggle-label">
              <span class="toggle-text">Mostra posti totali disponibili nell'intestazione della prenotazione</span>
              <span class="toggle-wrap">
                <input type="checkbox" v-model="form.visualizza_disponibili" class="toggle-input" />
                <span class="toggle-slider"></span>
              </span>
              <span class="toggle-state">{{ form.visualizza_disponibili ? 'Sì' : 'No' }}</span>
            </label>
          </div>

          <!-- Luoghi -->
          <div v-if="luoghi.length > 0" class="form-group">
            <label>Luoghi</label>
            <!-- Chip selezionati -->
            <div v-if="form.luogo_ids.length" class="luogo-chips">
              <span v-for="id in form.luogo_ids" :key="id" class="luogo-chip">
                {{ luogoById(id)?.nome ?? id }}
                <button type="button" @click="rimuoviLuogo(id)" class="luogo-chip-rm">&times;</button>
              </span>
            </div>
            <!-- Input ricerca -->
            <div class="luogo-search-wrap" v-click-outside="chiudiDropdown">
              <input
                v-model="luogoFiltro"
                @focus="dropdownAperto = true"
                type="text"
                class="input"
                placeholder="Cerca e aggiungi luogo…"
                autocomplete="off"
              />
              <div v-if="dropdownAperto && luoghiFiltrati.length" class="luogo-dropdown">
                <div
                  v-for="l in luoghiFiltrati"
                  :key="l.id"
                  class="luogo-option"
                  @mousedown.prevent="aggiungiLuogo(l.id)"
                >
                  {{ l.nome }}
                </div>
              </div>
              <div v-if="dropdownAperto && luogoFiltro && luoghiFiltrati.length === 0" class="luogo-dropdown">
                <div class="luogo-option luogo-option--empty">Nessun risultato</div>
              </div>
            </div>
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
import { eventiApi, sessioniApi } from '@/api/eventi'
import { useEnteStore } from '@/stores/ente'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route   = useRoute()
const enteId  = route.params.enteId
const eventoId = route.params.eventoId

const sessioni = ref([])
const evento   = ref(null)
const luoghi   = ref([])
const loading  = ref(false)

const enteStore  = useEnteStore()
const urlVetrina = computed(() => {
  const shop = enteStore.ente?.shop_url
  const slug = evento.value?.slug
  if (!shop || !slug) return null
  return `/vetrina/${shop}/eventi/${slug}`
})
const modal    = ref(false)
const saving   = ref(false)
const errore   = ref('')

const formDefault = () => ({
  id: null, data_inizio: '', data_fine: '', posti_totali: null,
  prenotabile: true,
  forza_non_disponibile: false, attiva_lista_attesa: false,
  soglia_chiusura_prenotazioni: null,
  tipo_conferma: 'NESSUNA', lista_attesa_finestra_conferma_ore: null,
  durata_lock_minuti: null, note_pubbliche: '', visualizza_disponibili: false,
  tipologie_posto: [], luogo_ids: [],
})
const form = reactive(formDefault())

const carica = async () => {
  loading.value = true
  try {
    const [evRes, sRes, lRes] = await Promise.all([
      eventiApi.show(enteId, eventoId),
      sessioniApi.index(enteId, eventoId),
      luoghiApi.index(enteId),
    ])
    evento.value   = evRes.data
    sessioni.value = sRes.data.data ?? sRes.data
    luoghi.value   = lRes.data.data ?? lRes.data
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
      forza_non_disponibile: s.forza_non_disponibile ?? false,
      soglia_chiusura_prenotazioni: s.soglia_chiusura_prenotazioni ?? null,
      attiva_lista_attesa: s.attiva_lista_attesa ?? false,
      tipo_conferma: s.tipo_conferma ?? 'NESSUNA',
      lista_attesa_finestra_conferma_ore: s.lista_attesa_finestra_conferma_ore ?? null,
      durata_lock_minuti: s.durata_lock_minuti ?? null,
      note_pubbliche: s.note_pubbliche ?? '',
      visualizza_disponibili: s.visualizza_disponibili ?? false,
    })
    // Sovrascrive i posti per tipologia con i valori già salvati
    form.tipologie_posto = form.tipologie_posto.map(tp => {
      const saved = (s.tipologie_posto ?? []).find(x => x.tipologia_posto_id === tp.tipologia_posto_id)
      return saved ? { ...tp, posti_totali: saved.posti_totali, attiva: saved.attiva ?? true } : tp
    })
    // Popola i luoghi già associati
    form.luogo_ids = (s.luoghi ?? []).map(l => l.id)
  }

  errore.value = ''
  modal.value  = true
}

const salva = async () => {
  errore.value = ''

  // Controllo coerenza posti totali vs tipologie
  const totaleSessione = form.posti_totali
  if (totaleSessione > 0 && form.tipologie_posto.length > 0) {
    const tipologieAttive = form.tipologie_posto.filter(tp => tp.attiva)
    const tutteLimitate   = tipologieAttive.every(tp => tp.posti_totali > 0)
    if (tutteLimitate) {
      const sommaTipologie = tipologieAttive.reduce((acc, tp) => acc + (tp.posti_totali || 0), 0)
      if (sommaTipologie > totaleSessione) {
        errore.value = `La somma dei posti per tipologia (${sommaTipologie}) supera il totale della sessione (${totaleSessione}).`
        return
      }
    }
  }

  saving.value = true
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

// --- Luoghi selector ---
const luogoFiltro    = ref('')
const dropdownAperto = ref(false)

const luogoById = (id) => luoghi.value.find(l => l.id === id)

const luoghiFiltrati = computed(() => {
  const q = luogoFiltro.value.toLowerCase().trim()
  return luoghi.value.filter(l =>
    !form.luogo_ids.includes(l.id) &&
    (q === '' || l.nome.toLowerCase().includes(q))
  )
})

const aggiungiLuogo = (id) => {
  if (!form.luogo_ids.includes(id)) form.luogo_ids.push(id)
  luogoFiltro.value = ''
  dropdownAperto.value = false
}

const rimuoviLuogo = (id) => {
  form.luogo_ids = form.luogo_ids.filter(x => x !== id)
}

const chiudiDropdown = () => { dropdownAperto.value = false }

// Direttiva v-click-outside
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value() }
    document.addEventListener('click', el._clickOutside)
  },
  unmounted(el) { document.removeEventListener('click', el._clickOutside) },
}
// --- fine Luoghi selector ---

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
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .5rem; }
.page-subheader { display: flex; justify-content: space-between; align-items: center; margin: 1.25rem 0 1rem; font-size: 1.1rem; font-weight: 600; flex-wrap: wrap; gap: .5rem; }
.page-subheader em { font-weight: 400; }
.tabs { display: flex; gap: .5rem; margin-bottom: 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px; }
.tabs::-webkit-scrollbar { display: none; }
.tab-btn {
  padding: .5rem 1.2rem; border: 2px solid #ddd; border-radius: 6px;
  background: white; color: #555; cursor: pointer; font-size: .9rem;
  text-decoration: none; display: inline-block; transition: border-color .15s, color .15s;
  white-space: nowrap; flex-shrink: 0;
}
.tab-btn:hover { border-color: #bbb; color: #2c3e50; }
.tab-btn.active { border-color: #3498db; color: #3498db; font-weight: 600; background: white; }
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

/* Sotto-riga tipologie in lista sessioni */
.row-tipologie td { padding: .3rem 1rem .6rem; background: #f8f9fb; border-top: none; }
.tipologie-breakdown { display: flex; flex-wrap: wrap; gap: .4rem; }
.tp-badge { display: inline-flex; align-items: center; gap: .35rem; background: #e8f0fe; border: 1px solid #c5d5f7; border-radius: 20px; padding: .15rem .65rem; font-size: .8rem; }
.tp-badge--inattiva { background: #f0f0f0; border-color: #ccc; color: #999; }
.tp-nome { font-weight: 600; }
.tp-disp { color: #555; }
.tp-badge--inattiva .tp-nome,
.tp-badge--inattiva .tp-disp { color: #aaa; }
.tp-badge--luogo { background: #fef9e7; border-color: #f9e79f; color: #7d6608; }

/* Toggle switch */
.toggle-label { display: flex; align-items: center; gap: .75rem; cursor: pointer; user-select: none; }
.toggle-text   { flex: 1; font-size: .9rem; }
.toggle-state  { font-size: .8rem; color: #888; min-width: 1.8rem; text-align: left; }
.toggle-wrap   { position: relative; display: inline-block; width: 40px; height: 22px; flex-shrink: 0; }
.toggle-input  { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider { position: absolute; inset: 0; background: #ccc; border-radius: 22px; transition: background .2s; }
.toggle-slider::before { content: ''; position: absolute; width: 16px; height: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; }
.toggle-input:checked + .toggle-slider { background: #3498db; }
.toggle-input:checked + .toggle-slider::before { transform: translateX(18px); }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.25rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }
.btn-outline { background: white; color: #3498db; border: 1.5px solid #3498db; border-radius: 6px; padding: .4rem 1rem; cursor: pointer; text-decoration: none; font-size: .9rem; }
.btn-outline:hover { background: #eaf4fd; }
.table-tipologie { width: 100%; border-collapse: collapse; font-size: .88rem; margin-top: .25rem; }
.table-tipologie th, .table-tipologie td { padding: .35rem .5rem; border-bottom: 1px solid #eee; }
.table-tipologie th { font-weight: 600; text-align: left; background: #f8f9fa; }
.input-sm { width: 90px; padding: .3rem .5rem; }
.luogo-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .4rem; }
.luogo-chip { display: inline-flex; align-items: center; gap: .3rem; background: #e8f0fe; border: 1px solid #c5d5f7; border-radius: 20px; padding: .15rem .55rem .15rem .65rem; font-size: .82rem; font-weight: 500; }
.luogo-chip-rm { border: none; background: none; cursor: pointer; color: #5a7abf; font-size: 1rem; line-height: 1; padding: 0; margin-left: .1rem; }
.luogo-chip-rm:hover { color: #c0392b; }
.luogo-search-wrap { position: relative; }
.luogo-dropdown { position: absolute; top: calc(100% + 2px); left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.1); max-height: 200px; overflow-y: auto; z-index: 200; }
.luogo-option { padding: .48rem .75rem; font-size: .9rem; cursor: pointer; }
.luogo-option:hover { background: #f0f6ff; }
.luogo-option--empty { color: #aaa; cursor: default; }
.luogo-option--empty:hover { background: none; }

@media (max-width: 640px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
  .table tr {
    border: 1px solid #e8eaed;
    border-radius: 8px;
    margin-bottom: .75rem;
    padding: .5rem .75rem;
    background: white;
  }
  .table td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .5rem;
    padding: .4rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: .9rem;
  }
  .table td:last-child { border-bottom: none; }
  .table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #777;
    font-size: .78rem;
    white-space: nowrap;
  }
  .actions { justify-content: flex-end; }
  .grid-2 { grid-template-columns: 1fr; }
  .modal-actions { flex-direction: column-reverse; }
  .modal-actions button { width: 100%; }
}
</style>
