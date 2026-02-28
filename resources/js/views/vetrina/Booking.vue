<template>
  <div class="booking-page">
    <div class="container">
      <div v-if="loading" class="loading">Caricamento sessione…</div>
      <div v-else-if="erroreCaricamento" class="alert-error">{{ erroreCaricamento }}</div>
      <template v-else>
        <div class="back-link-wrap">
          <router-link :to="`/vetrina/${shopUrl}/eventi/${slug}`" class="back-link">← Torna all'evento</router-link>
        </div>

        <h1>🎟 Prenota — {{ evento?.titolo }}</h1>
        <div class="sessione-info card">
          <strong>📅 {{ formatDateTime(sessione?.inizio_at) }}</strong>
          <span v-if="sessione?.posti_totali" class="posti-left">
            — {{ postiRimasti }} posti disponibili
          </span>
        </div>

        <!-- STEP 1: Selezione posti -->
        <div v-if="step === 1" class="card">
          <h2>1. Seleziona i posti</h2>
          <div v-if="!tipologie.length" class="empty">Nessuna tipologia di posto disponibile.</div>
          <div v-for="t in tipologie" :key="t.tipologia_posto.id" class="tipologia-row">
            <div class="tipologia-info">
              <strong>{{ t.tipologia_posto.nome }}</strong>
              <span class="prezzo">
                {{ t.tipologia_posto.gratuita ? 'Gratuito' : `€ ${Number(t.tipologia_posto.costo).toFixed(2)}` }}
              </span>
            </div>
            <div class="qty-control">
              <button type="button" @click="cambiaQty(t.tipologia_posto.id, -1)" class="qty-btn">−</button>
              <span class="qty-val">{{ getQty(t.tipologia_posto.id) }}</span>
              <button type="button" @click="cambiaQty(t.tipologia_posto.id, +1)" class="qty-btn">+</button>
            </div>
          </div>

          <div class="totale">
            Totale: <strong>€ {{ totale.toFixed(2) }}</strong>
          </div>

          <div v-if="errore" class="alert-error">{{ errore }}</div>
          <div class="step-actions">
            <button
              @click="acquisisciLock"
              :disabled="totPosti === 0 || locking"
              class="btn btn-primary"
            >
              {{ locking ? 'Prenotazione in corso…' : 'Continua →' }}
            </button>
          </div>
        </div>

        <!-- STEP 2: Dati personali + form aggiuntivo -->
        <div v-if="step === 2" class="card">
          <h2>2. I tuoi dati</h2>

          <div class="countdown" v-if="scadenzaSecondi > 0">
            ⏱ Il tuo posto è riservato per {{ Math.floor(scadenzaSecondi / 60) }}:{{ String(scadenzaSecondi % 60).padStart(2, '0') }}
          </div>
          <div v-else class="alert-error">Il tempo è scaduto. Ricomincia la prenotazione.</div>

          <form @submit.prevent="confermaPrenot" v-if="scadenzaSecondi > 0">
            <div class="grid-2">
              <div class="form-group">
                <label>Nome *</label>
                <input v-model="datiPersonali.nome" required class="input" />
              </div>
              <div class="form-group">
                <label>Cognome *</label>
                <input v-model="datiPersonali.cognome" required class="input" />
              </div>
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label>Email *</label>
                <input v-model="datiPersonali.email" type="email" required class="input" />
              </div>
              <div class="form-group">
                <label>Telefono</label>
                <input v-model="datiPersonali.telefono" class="input" />
              </div>
            </div>
            <div class="form-group">
              <label>Note</label>
              <textarea v-model="datiPersonali.note" rows="2" class="input"></textarea>
            </div>

            <!-- Campi form personalizzati -->
            <div v-for="campo in campiForm" :key="campo.id" class="form-group">
              <label>{{ campo.etichetta }} <span v-if="campo.obbligatorio" class="required">*</span></label>
              <input
                v-if="['TEXT','EMAIL','PHONE','NUMBER','DATE'].includes(campo.tipo)"
                v-model="risposte[campo.id]"
                :type="campo.tipo.toLowerCase()"
                :required="campo.obbligatorio"
                class="input"
              />
              <textarea
                v-else-if="campo.tipo === 'TEXTAREA'"
                v-model="risposte[campo.id]"
                :required="campo.obbooligatorio"
                rows="3"
                class="input"
              ></textarea>
              <select
                v-else-if="['SELECT','RADIO'].includes(campo.tipo)"
                v-model="risposte[campo.id]"
                :required="campo.obbligatorio"
                class="input"
              >
                <option value="">— scegli —</option>
                <option v-for="op in campo.opzioni" :key="op" :value="op">{{ op }}</option>
              </select>
              <label v-else-if="campo.tipo === 'CHECKBOX'" class="checkbox-label">
                <input type="checkbox" v-model="risposte[campo.id]" />
                {{ campo.placeholder || campo.etichetta }}
              </label>
            </div>

            <div v-if="errore" class="alert-error">{{ errore }}</div>

            <div class="step-actions">
              <button type="button" @click="step = 1; rilasciaLock()" class="btn btn-secondary">← Modifica posti</button>
              <button type="submit" :disabled="confermando" class="btn btn-primary">
                {{ confermando ? 'Conferma in corso…' : '✅ Conferma prenotazione' }}
              </button>
            </div>
          </form>
        </div>

        <!-- STEP 3: Conferma -->
        <div v-if="step === 3" class="card conferma">
          <div class="conferma-icon">🎉</div>
          <h2>Prenotazione {{ prenotazioneConfermata?.stato === 'DA_CONFERMARE' ? 'in attesa di approvazione' : 'confermata' }}!</h2>
          <p>Codice prenotazione: <strong class="codice">{{ prenotazioneConfermata?.codice }}</strong></p>
          <p>Riceverai una email di conferma a <strong>{{ datiPersonali.email }}</strong>.</p>
          <div class="step-actions" style="justify-content: center;">
            <router-link :to="`/prenotazioni/${prenotazioneConfermata?.codice}`" class="btn btn-primary">
              Visualizza prenotazione
            </router-link>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { prenotazioniApi } from '@/api/prenotazioni'
import { vetrinaApi } from '@/api/vetrina'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route     = useRoute()
const shopUrl   = route.params.shopUrl
const slug      = route.params.slug
const sessioneId = Number(route.params.sessioneId)

const loading           = ref(true)
const erroreCaricamento = ref('')
const errore            = ref('')
const locking           = ref(false)
const confermando       = ref(false)
const step              = ref(1)
const evento            = ref(null)
const sessione          = ref(null)
const tipologie         = ref([])
const campiForm         = ref([])
const posti             = reactive({})    // tipologia_id → qty
const datiPersonali     = reactive({ nome: '', cognome: '', email: '', telefono: '', note: '' })
const risposte          = reactive({})
const lockToken         = ref(null)
const scadenzaSecondi   = ref(0)
const prenotazioneConfermata = ref(null)
let timer = null

const getQty      = (id) => posti[id] ?? 0
const cambiaQty   = (id, delta) => {
  posti[id] = Math.max(0, (posti[id] ?? 0) + delta)
}

const totPosti = computed(() => Object.values(posti).reduce((s, v) => s + v, 0))

const totale = computed(() => {
  return tipologie.value.reduce((s, t) => {
    const qty  = getQty(t.tipologia_posto.id)
    const cost = t.tipologia_posto.gratuita ? 0 : (t.tipologia_posto.costo ?? 0)
    return s + qty * Number(cost)
  }, 0)
})

const postiRimasti = computed(() => {
  if (!sessione.value?.posti_totali) return null
  return sessione.value.posti_totali - sessione.value.posti_prenotati - sessione.value.posti_riservati
})

const carica = async () => {
  loading.value = true
  try {
    const res = await vetrinaApi.evento(shopUrl, slug)
    evento.value    = res.data
    sessione.value  = res.data.sessioni?.find(s => s.id === sessioneId) ?? null
    tipologie.value = sessione.value?.tipologie_disponibili ?? []
    campiForm.value = res.data.campi_form ?? []

    if (!sessione.value) {
      erroreCaricamento.value = 'Sessione non trovata o non disponibile.'
    }
  } catch {
    erroreCaricamento.value = 'Impossibile caricare i dati. Riprova.'
  } finally { loading.value = false }
}

const acquisisciLock = async () => {
  errore.value = ''
  if (totPosti.value === 0) return
  locking.value = true
  try {
    const postiPayload = Object.entries(posti)
      .filter(([, qty]) => qty > 0)
      .map(([tipologia_id, quantita]) => ({
        tipologia_id: Number(tipologia_id),
        quantita,
      }))

    const res = await prenotazioniApi.lock({ sessione_id: sessioneId, posti: postiPayload })
    lockToken.value = res.data.token
    const scadenzaMs = new Date(res.data.scadenza_at) - Date.now()
    scadenzaSecondi.value = Math.max(0, Math.floor(scadenzaMs / 1000))
    avviaTimer()
    step.value = 2
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Posti non disponibili.'
  } finally { locking.value = false }
}

const confermaPrenot = async () => {
  errore.value = ''
  confermando.value = true
  try {
    const postiPayload = Object.entries(posti)
      .filter(([, qty]) => qty > 0)
      .map(([tipologia_id, quantita]) => {
        const t = tipologie.value.find(x => x.tipologia_posto.id === Number(tipologia_id))
        return {
          tipologia_id: Number(tipologia_id),
          quantita,
          costo_unitario: t?.tipologia_posto?.gratuita ? 0 : (t?.tipologia_posto?.costo ?? 0),
        }
      })

    const rispostePayload = campiForm.value
      .filter(c => risposte[c.id] !== undefined && risposte[c.id] !== '')
      .map(c => ({ campo_form_id: c.id, risposta: String(risposte[c.id]) }))

    const res = await prenotazioniApi.store({
      token: lockToken.value,
      ...datiPersonali,
      posti: postiPayload,
      risposte: rispostePayload,
    })

    prenotazioneConfermata.value = res.data
    fermaTimer()
    step.value = 3
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore durante la conferma.'
  } finally { confermando.value = false }
}

const rilasciaLock = async () => {
  if (lockToken.value) {
    await prenotazioniApi.rilasciaLock(lockToken.value).catch(() => {})
    lockToken.value = null
    fermaTimer()
  }
}

const avviaTimer = () => {
  timer = setInterval(() => {
    if (scadenzaSecondi.value > 0) {
      scadenzaSecondi.value--
    } else {
      fermaTimer()
    }
  }, 1000)
}

const fermaTimer = () => {
  if (timer) { clearInterval(timer); timer = null }
}

const formatDateTime = (d) => d ? new Date(d).toLocaleString('it-IT', { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–'

onMounted(carica)
onUnmounted(() => fermaTimer())
</script>

<style scoped>
.booking-page { min-height: 100vh; background: #f8f9fa; padding: 2rem 0; }
.container { max-width: 700px; margin: 0 auto; padding: 0 1rem; }
.back-link-wrap { margin-bottom: 1rem; }
.back-link { color: #3498db; text-decoration: none; font-size: .9rem; }
h1 { font-size: 1.6rem; margin-bottom: 1rem; }
.sessione-info { background: #ebf5fb; margin-bottom: 1.25rem; }
.posti-left { color: #27ae60; font-size: .9rem; }
.tipologia-row { display: flex; justify-content: space-between; align-items: center; padding: .75rem; border: 1px solid #eee; border-radius: 8px; margin-bottom: .6rem; }
.tipologia-info { display: flex; flex-direction: column; }
.prezzo { color: #27ae60; font-size: .9rem; margin-top: .15rem; }
.qty-control { display: flex; align-items: center; gap: .6rem; }
.qty-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #ddd; background: white; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.qty-val { font-size: 1.1rem; font-weight: 600; min-width: 24px; text-align: center; }
.totale { margin-top: 1rem; padding: 1rem; background: #f0f4f8; border-radius: 8px; text-align: right; font-size: 1.05rem; }
.step-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.5rem; flex-wrap: wrap; }
.countdown { background: #fef9e7; border: 1px solid #f9ca24; border-radius: 8px; padding: .7rem 1rem; margin-bottom: 1.25rem; font-weight: 600; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.required { color: #e74c3c; }
.checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; margin-top: .2rem; }
.conferma { text-align: center; padding: 2.5rem; }
.conferma-icon { font-size: 3rem; margin-bottom: 1rem; }
.conferma h2 { margin-bottom: .75rem; }
.codice { font-family: monospace; font-size: 1.1rem; color: #1a5276; letter-spacing: .05em; }
.loading { padding: 3rem; text-align: center; color: #aaa; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .5rem 1.1rem; cursor: pointer; text-decoration: none; }
@media (max-width: 500px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
