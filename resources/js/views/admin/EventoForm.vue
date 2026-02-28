<template>
  <div>
    <div class="page-header">
      <h1>{{ isNuovo ? '+ Nuovo evento' : '✏️ Modifica evento' }}</h1>
      <router-link :to="`/admin/${enteId}/eventi`" class="btn btn-secondary">← Torna agli eventi</router-link>
    </div>

    <!-- Tab navigation -->
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="['tab-btn', { active: tabAttivo === tab.key }]"
        @click="cambiaTab(tab.key)"
        :disabled="isNuovo && tab.key !== 'dettagli'"
      >{{ tab.label }}</button>
    </div>

    <!-- TAB: Dettagli base -->
    <div v-if="tabAttivo === 'dettagli'" class="card">
      <div v-if="loading" style="padding:2rem;text-align:center;color:#aaa">Caricamento…</div>
      <form @submit.prevent="salva" :style="loading ? 'opacity:0;pointer-events:none' : ''">
        <div class="grid-2">
          <div class="form-group">
            <label>Titolo *</label>
            <input v-model="form.titolo" required class="input" placeholder="Titolo evento" />
          </div>
          <div class="form-group">
            <label>Stato</label>
            <select v-model="form.stato" class="input">
              <option value="BOZZA">Bozza</option>
              <option value="PUBBLICATO">Pubblicato</option>
              <option value="SOSPESO">Sospeso</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Descrizione breve</label>
          <textarea v-model="form.descrizione_breve" rows="2" class="input" placeholder="Breve descrizione (max 300 caratteri)"></textarea>
        </div>

        <div class="form-group">
          <label>Descrizione completa</label>
          <textarea v-model="form.descrizione" rows="5" class="input" placeholder="Descrizione completa HTML/testo"></textarea>
        </div>

        <div class="grid-3">
          <div class="form-group">
            <label>Serie</label>
            <select v-model="form.serie_id" class="input">
              <option :value="null">— nessuna —</option>
              <option v-for="s in serie" :key="s.id" :value="s.id">{{ s.titolo }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Max posti per prenotazione</label>
            <input v-model.number="form.posti_max_per_prenotazione" type="number" min="1" class="input" />
          </div>
          <div class="form-group">
            <label>Cancellazione consentita (ore prima)</label>
            <input v-model.number="form.cancellazione_consentita_ore" type="number" min="0" class="input" placeholder="0 = non consentita" />
          </div>
        </div>

        <!-- Date visibilità e prenotabilità -->
        <div class="grid-2">
          <div class="form-group">
            <label>Visibile dal</label>
            <input v-model="form.visibile_dal" type="datetime-local" class="input" />
          </div>
          <div class="form-group">
            <label>Visibile al</label>
            <input v-model="form.visibile_al" type="datetime-local" class="input" />
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Prenotabile dal</label>
            <input v-model="form.prenotabile_dal" type="datetime-local" class="input" />
          </div>
          <div class="form-group">
            <label>Prenotabile al</label>
            <input v-model="form.prenotabile_al" type="datetime-local" class="input" />
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.richiede_approvazione" />
              Richiede approvazione operatore
            </label>
          </div>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.consenti_prenotazione_guest" />
              Consenti prenotazioni guest (senza account)
            </label>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.consenti_multi_sessione" />
              Consenti prenotazione di più sessioni
            </label>
          </div>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.mostra_disponibilita" />
              Mostra disponibilità in vetrina
            </label>
          </div>
        </div>

        <!-- Tag -->
        <div class="form-group">
          <label>Tag</label>
          <div class="tags-flex">
            <label v-for="tag in tags" :key="tag.id" class="tag-check">
              <input type="checkbox" :value="tag.id" v-model="form.tag_ids" />
              <span :style="{ background: tag.colore || '#3498db' }" class="tag-badge">{{ tag.nome }}</span>
            </label>
          </div>
        </div>

        <div v-if="errore" class="alert-error">{{ errore }}</div>

        <div class="form-actions">
          <button type="submit" :disabled="saving" class="btn btn-primary">
            {{ saving ? 'Salvataggio…' : (isNuovo ? 'Crea evento' : 'Salva modifiche') }}
          </button>
        </div>
      </form>
    </div>

    <!-- TAB: Tipologie posto -->
    <div v-if="tabAttivo === 'tipologie'" class="card">
      <div class="section-header">
        <h2>Tipologie di posto</h2>
        <button @click="apriDialogTipologia()" class="btn btn-primary btn-sm">+ Aggiungi</button>
      </div>

      <div v-if="tipologie.length === 0" class="empty">Nessuna tipologia. Aggiungine una per gestire i posti.</div>

      <table v-else class="table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Costo (€)</th>
            <th>Gratuita</th>
            <th>Min posti</th>
            <th>Max posti</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(t, i) in tipologie" :key="t.id ?? i">
            <td>{{ t.nome }}</td>
            <td>{{ t.gratuita ? '—' : `€ ${Number(t.costo).toFixed(2)}` }}</td>
            <td>{{ t.gratuita ? '✓' : '' }}</td>
            <td>{{ t.min_prenotabili ?? '—' }}</td>
            <td>{{ t.max_prenotabili ?? '—' }}</td>
            <td class="actions-cell">
              <button @click="apriDialogTipologia(t, i)" class="btn btn-sm btn-secondary">Modifica</button>
              <button @click="eliminaTipologia(t, i)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TAB: Campi form -->
    <div v-if="tabAttivo === 'form'" class="card">
      <div class="section-header">
        <h2>Campi del modulo di prenotazione</h2>
        <button @click="apriDialogCampo()" class="btn btn-primary btn-sm">+ Aggiungi campo</button>
      </div>

      <div v-if="campi.length === 0" class="empty">Nessun campo. Il modulo utilizzerà solo i dati base (nome, email).</div>

      <table v-else class="table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Etichetta</th>
            <th>Obbligatorio</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(c, i) in campi" :key="c.id ?? i">
            <td><span class="tipo-badge">{{ c.tipo }}</span></td>
            <td>{{ c.etichetta }}</td>
            <td>{{ c.obbligatorio ? '✓' : '' }}</td>
            <td class="actions-cell">
              <button @click="apriDialogCampo(c, i)" class="btn btn-sm btn-secondary">Modifica</button>
              <button @click="eliminaCampo(c, i)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Dialog Tipologia -->
    <div v-if="dialogTipologia.aperto" class="modal-overlay" @click.self="dialogTipologia.aperto = false">
      <div class="modal-dialog">
        <h3>{{ dialogTipologia.indice === null ? 'Nuova tipologia' : 'Modifica tipologia' }}</h3>
        <div class="form-group">
          <label>Nome *</label>
          <input v-model="dialogTipologia.form.nome" class="input" placeholder="es. Ordinario, Ridotto…" />
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Costo (€)</label>
            <input v-model.number="dialogTipologia.form.costo" type="number" step="0.01" min="0" class="input" :disabled="dialogTipologia.form.gratuita" />
          </div>
          <div class="form-group" style="display:flex;align-items:center;gap:.5rem;margin-top:1.5rem">
            <input type="checkbox" id="chk-gratuita" v-model="dialogTipologia.form.gratuita" @change="dialogTipologia.form.gratuita && (dialogTipologia.form.costo = 0)" />
            <label for="chk-gratuita" style="margin:0;cursor:pointer">Gratuita</label>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label>Min prenotabili</label>
            <input v-model.number="dialogTipologia.form.min_prenotabili" type="number" min="1" class="input" placeholder="lascia vuoto = nessun limite" />
          </div>
          <div class="form-group">
            <label>Max prenotabili</label>
            <input v-model.number="dialogTipologia.form.max_prenotabili" type="number" min="1" class="input" placeholder="lascia vuoto = nessun limite" />
          </div>
        </div>
        <div v-if="dialogTipologia.errore" class="alert-error">{{ dialogTipologia.errore }}</div>
        <div class="dialog-actions">
          <button @click="dialogTipologia.aperto = false" class="btn">Annulla</button>
          <button @click="salvaTipologia" :disabled="dialogTipologia.saving" class="btn btn-primary">
            {{ dialogTipologia.saving ? 'Salvataggio…' : 'Salva' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Dialog Campo form -->
    <div v-if="dialogCampo.aperto" class="modal-overlay" @click.self="dialogCampo.aperto = false">
      <div class="modal-dialog">
        <h3>{{ dialogCampo.indice === null ? 'Nuovo campo' : 'Modifica campo' }}</h3>
        <div class="form-group">
          <label>Tipo</label>
          <select v-model="dialogCampo.form.tipo" class="input">
            <option v-for="tipo in tipiCampo" :key="tipo" :value="tipo">{{ tipo }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Etichetta *</label>
          <input v-model="dialogCampo.form.etichetta" class="input" placeholder="es. Data di nascita" />
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
          <input type="checkbox" id="chk-obbligatorio" v-model="dialogCampo.form.obbligatorio" />
          <label for="chk-obbligatorio" style="margin:0;cursor:pointer">Obbligatorio</label>
        </div>
        <div v-if="dialogCampo.errore" class="alert-error">{{ dialogCampo.errore }}</div>
        <div class="dialog-actions">
          <button @click="dialogCampo.aperto = false" class="btn">Annulla</button>
          <button @click="salvaCampo" :disabled="dialogCampo.saving" class="btn btn-primary">
            {{ dialogCampo.saving ? 'Salvataggio…' : 'Salva' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { serieApi, tagsApi } from '@/api/admin'
import { campiFormApi, eventiApi, tipologiePostoApi } from '@/api/eventi'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route  = useRoute()
const router = useRouter()
const enteId  = computed(() => route.params.enteId)
const eventoId = computed(() => route.params.eventoId)
const isNuovo = computed(() => !route.params.eventoId)

const tabs = [
  { key: 'dettagli',  label: '📝 Dettagli'       },
  { key: 'sessioni',  label: '🗓 Sessioni'        },
  { key: 'tipologie', label: '🪑 Tipologie posto' },
  { key: 'form',      label: '📋 Campi form'      },
]

const tabAttivo = ref(route.query.tab || 'dettagli')

const cambiaTab = (key) => {
  if (key === 'sessioni') {
    router.push(`/admin/${enteId.value}/eventi/${eventoId.value}/sessioni`)
    return
  }
  tabAttivo.value = key
  router.replace({ query: { ...route.query, tab: key } })
}

const form = reactive({
  titolo: '', descrizione_breve: '', descrizione: '',
  stato: 'BOZZA', serie_id: null,
  posti_max_per_prenotazione: 1, cancellazione_consentita_ore: 0,
  richiede_approvazione: false, consenti_prenotazione_guest: true,
  consenti_multi_sessione: false, mostra_disponibilita: true,
  visibile_dal: '', visibile_al: '', prenotabile_dal: '', prenotabile_al: '',
  tag_ids: [],
})
const serie = ref([])
const tags  = ref([])
const tipologie = ref([])
const campi = ref([])
const saving = ref(false)
const loading = ref(false)
const errore = ref('')
const tipiCampo = ['TEXT','TEXTAREA','SELECT','CHECKBOX','RADIO','DATE','EMAIL','PHONE','NUMBER']

const caricaDati = async () => {
  errore.value = ''
  loading.value = true
  try {
    // Carica tags e serie (non bloccanti se falliscono)
    try {
      const [tagsRes, serieRes] = await Promise.all([
        tagsApi.index(enteId.value),
        serieApi.index(enteId.value),
      ])
      tags.value  = tagsRes.data.data ?? tagsRes.data
      serie.value = serieRes.data.data ?? serieRes.data
    } catch (e) {
      console.warn('Errore caricamento tags/serie:', e)
    }

    if (!isNuovo.value) {
      const evRes = await eventiApi.show(enteId.value, eventoId.value)
      const ev = evRes.data
      form.titolo                    = ev.titolo ?? ''
      form.descrizione_breve         = ev.descrizione_breve ?? ''
      form.descrizione               = ev.descrizione ?? ''
      form.stato                     = ev.stato ?? 'BOZZA'
      form.serie_id                  = ev.serie_id ?? null
      form.posti_max_per_prenotazione = ev.posti_max_per_prenotazione ?? 1
      form.cancellazione_consentita_ore = ev.cancellazione_consentita_ore ?? 0
      form.richiede_approvazione     = !!ev.richiede_approvazione
      form.consenti_prenotazione_guest = ev.consenti_prenotazione_guest ?? true
      form.consenti_multi_sessione   = !!ev.consenti_multi_sessione
      form.mostra_disponibilita      = ev.mostra_disponibilita ?? true
      form.visibile_dal              = ev.visibile_dal ? ev.visibile_dal.slice(0, 16) : ''
      form.visibile_al               = ev.visibile_al ? ev.visibile_al.slice(0, 16) : ''
      form.prenotabile_dal           = ev.prenotabile_dal ? ev.prenotabile_dal.slice(0, 16) : ''
      form.prenotabile_al            = ev.prenotabile_al ? ev.prenotabile_al.slice(0, 16) : ''
      form.tag_ids                   = ev.tags?.map(t => t.id) ?? []

      try {
        const [tipRes, campiRes] = await Promise.all([
          tipologiePostoApi.index(enteId.value, eventoId.value),
          campiFormApi.index(enteId.value, eventoId.value),
        ])
        tipologie.value = tipRes.data
        campi.value     = campiRes.data
      } catch (e) {
        console.warn('Errore caricamento tipologie/campi:', e)
      }
    }
  } catch (e) {
    errore.value = e.response?.data?.message ?? `Errore caricamento evento: ${e.message}`
  } finally {
    loading.value = false
  }
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    if (isNuovo.value) {
      await eventiApi.store(enteId.value, form)
      router.push(`/admin/${enteId.value}/eventi`)
    } else {
      await eventiApi.update(enteId.value, eventoId.value, form)
    }
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    saving.value = false
  }
}

// --- Tipologie — dialog ---
const dialogTipologia = reactive({
  aperto: false,
  indice: null,       // null = nuovo, number = modifica
  saving: false,
  errore: '',
  form: { nome: '', costo: 0, gratuita: false, min_prenotabili: null, max_prenotabili: null },
})

const apriDialogTipologia = (t = null, i = null) => {
  dialogTipologia.indice = i
  dialogTipologia.errore = ''
  dialogTipologia.form = t
    ? { id: t.id, nome: t.nome, costo: t.costo ?? 0, gratuita: !!t.gratuita, min_prenotabili: t.min_prenotabili ?? null, max_prenotabili: t.max_prenotabili ?? null }
    : { nome: '', costo: 0, gratuita: false, min_prenotabili: null, max_prenotabili: null }
  dialogTipologia.aperto = true
}

const salvaTipologia = async () => {
  if (!dialogTipologia.form.nome) { dialogTipologia.errore = 'Il nome è obbligatorio.'; return }
  dialogTipologia.saving = true
  dialogTipologia.errore = ''
  try {
    const payload = { ...dialogTipologia.form }
    if (payload.id) {
      const res = await tipologiePostoApi.update(enteId.value, eventoId.value, payload.id, payload)
      tipologie.value[dialogTipologia.indice] = res.data
    } else {
      const res = await tipologiePostoApi.store(enteId.value, eventoId.value, payload)
      tipologie.value.push(res.data)
    }
    dialogTipologia.aperto = false
  } catch (e) {
    dialogTipologia.errore = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    dialogTipologia.saving = false
  }
}

const eliminaTipologia = async (t, i) => {
  if (!confirm(`Eliminare la tipologia "${t.nome}"?`)) return
  if (t.id) await tipologiePostoApi.destroy(enteId.value, eventoId.value, t.id)
  tipologie.value.splice(i, 1)
}

// --- Campi form — dialog ---
const dialogCampo = reactive({
  aperto: false,
  indice: null,
  saving: false,
  errore: '',
  form: { tipo: 'TEXT', etichetta: '', obbligatorio: false },
})

const apriDialogCampo = (c = null, i = null) => {
  dialogCampo.indice = i
  dialogCampo.errore = ''
  dialogCampo.form = c
    ? { id: c.id, tipo: c.tipo, etichetta: c.etichetta, obbligatorio: !!c.obbligatorio }
    : { tipo: 'TEXT', etichetta: '', obbligatorio: false }
  dialogCampo.aperto = true
}

const salvaCampo = async () => {
  if (!dialogCampo.form.etichetta) { dialogCampo.errore = 'L\'etichetta è obbligatoria.'; return }
  dialogCampo.saving = true
  dialogCampo.errore = ''
  try {
    const payload = { ...dialogCampo.form }
    if (payload.id) {
      const res = await campiFormApi.update(enteId.value, eventoId.value, payload.id, payload)
      campi.value[dialogCampo.indice] = res.data
    } else {
      const res = await campiFormApi.store(enteId.value, eventoId.value, payload)
      campi.value.push(res.data)
    }
    dialogCampo.aperto = false
  } catch (e) {
    dialogCampo.errore = e.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    dialogCampo.saving = false
  }
}

const eliminaCampo = async (c, i) => {
  if (!confirm(`Eliminare il campo "${c.etichetta}"?`)) return
  if (c.id) await campiFormApi.destroy(enteId.value, eventoId.value, c.id)
  campi.value.splice(i, 1)
}

onMounted(caricaDati)

// Ricarica dati se l'eventoId cambia senza smontare il componente
watch(() => route.params.eventoId, (newId) => {
  if (newId) caricaDati()
})
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.tabs { display: flex; gap: .5rem; margin-bottom: 1rem; }
.tab-btn { padding: .5rem 1.2rem; border: 2px solid #ddd; border-radius: 6px; background: white; cursor: pointer; font-size: .9rem; }
.tab-btn.active { border-color: #3498db; color: #3498db; font-weight: 600; }
.tab-btn:disabled { opacity: .4; cursor: not-allowed; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; }
.form-actions { margin-top: 1.5rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.tags-flex { display: flex; flex-wrap: wrap; gap: .5rem; }
.tag-check { display: flex; align-items: center; gap: .3rem; cursor: pointer; }
.tag-badge { padding: .2rem .7rem; border-radius: 12px; color: white; font-size: .8rem; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.empty { padding: 2rem; text-align: center; color: #aaa; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; text-decoration: none; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: .6rem .75rem; text-align: left; border-bottom: 1px solid #eee; font-size: .9rem; }
.table th { background: #f8f9fa; font-weight: 600; }
.actions-cell { display: flex; gap: .4rem; }
.tipo-badge { background: #e8f4fd; color: #2980b9; padding: .15rem .5rem; border-radius: 4px; font-size: .8rem; font-weight: 600; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal-dialog { background: white; border-radius: 10px; padding: 1.75rem; width: 90%; max-width: 480px; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
.modal-dialog h3 { margin: 0 0 1.25rem; font-size: 1.1rem; }
.dialog-actions { display: flex; justify-content: flex-end; gap: .75rem; margin-top: 1.25rem; }
</style>
