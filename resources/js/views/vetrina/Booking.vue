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
          <strong>📅 {{ formatDateTime(sessione?.data_inizio) }}</strong>
          <span v-if="sessione?.visualizza_disponibili && sessione?.posti_totali > 0" class="posti-left">
            — {{ postiRimasti }} posti disponibili
          </span>
        </div>

        <!-- STEP 1: Selezione posti -->
        <div v-if="step === 1" class="card">
          <h2>1. Seleziona i posti</h2>
          <div v-if="!tipologie.length" class="empty">Nessuna tipologia di posto disponibile.</div>
          <div v-for="t in tipologie" :key="t.tipologia_posto.id"
            class="tipologia-row"
            :class="{ 'tipologia-row--esaurita': t.posti_totali > 0 && (t.posti_disponibili ?? 0) === 0 }">
            <div class="tipologia-info">
              <strong>{{ t.tipologia_posto.nome }}</strong>
              <span class="prezzo">
                {{ t.tipologia_posto.gratuita ? 'Gratuito' : `€ ${Number(t.tipologia_posto.costo).toFixed(2)}` }}
              </span>
              <span v-if="t.posti_totali > 0 && (t.posti_disponibili ?? 0) === 0" class="posti-esauriti">
                Disponibilità terminata
              </span>
              <span v-else-if="t.tipologia_posto.visualizza_disponibili && t.posti_totali > 0" class="posti-left-tp">
                {{ t.posti_disponibili }} disponibili
              </span>
              <span v-else-if="t.tipologia_posto.visualizza_disponibili && t.posti_totali === 0" class="posti-left-tp">
                Disponibilità libera
              </span>
              <span v-if="t.tipologia_posto.min_prenotabili" class="qty-hint">
                Quantità minima prenotabile: {{ t.tipologia_posto.min_prenotabili }}
              </span>
              <span v-if="t.tipologia_posto.max_prenotabili" class="qty-hint">
                Quantità massima prenotabile: {{ t.tipologia_posto.max_prenotabili }}
              </span>
            </div>
            <div class="qty-control">
              <button type="button"
                @click="cambiaQty(t.tipologia_posto.id, -1)"
                class="qty-btn"
                :disabled="t.posti_totali > 0 && (t.posti_disponibili ?? 0) === 0">−</button>
              <input
                type="number"
                min="0"
                class="qty-input"
                :value="getQty(t.tipologia_posto.id)"
                @change="setQty(t.tipologia_posto.id, $event.target.value)"
                @focus="$event.target.select()"
                :disabled="t.posti_totali > 0 && (t.posti_disponibili ?? 0) === 0"
              />
              <button type="button"
                @click="cambiaQty(t.tipologia_posto.id, +1)"
                class="qty-btn"
                :disabled="t.posti_totali > 0 && (t.posti_disponibili ?? 0) === 0">+</button>
            </div>
          </div>

          <div class="totale">
            Totale: <strong>€ {{ totale.toFixed(2) }}</strong>
          </div>

          <div v-if="errore" class="alert-error">{{ errore }}</div>
          <div class="step-actions">
            <button
              @click="acquisisciLock"
              :disabled="locking"
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

            <!-- Riepilogo posti selezionati -->
            <div class="riepilogo-posti-step2">
              <div class="riepilogo-posti-title">🪑 Posti selezionati</div>
              <div v-for="p in postiSelezionati" :key="p.id" class="riepilogo-posti-row">
                <span class="riepilogo-posti-nome">{{ p.nome }}</span>
                <span class="riepilogo-posti-qty">× {{ p.quantita }}</span>
                <span class="riepilogo-posti-costo">{{ p.gratuita ? 'Gratuito' : `€ ${p.costoTotale.toFixed(2)}` }}</span>
              </div>
              <div v-if="totale > 0" class="riepilogo-posti-totale">
                <span>Totale</span>
                <span>€ {{ totale.toFixed(2) }}</span>
              </div>
            </div>

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
                <input v-model="datiPersonali.email" type="email" required class="input" autocomplete="email"
                  :class="{ 'input-error': datiPersonali.email && !emailValida(datiPersonali.email) }" />
                <span v-if="datiPersonali.email && !emailValida(datiPersonali.email)" class="field-error">Indirizzo email non valido</span>
              </div>
              <div class="form-group">
                <label>Conferma email *</label>
                <input v-model="emailConferma" type="email" required class="input" autocomplete="off"
                  :class="{ 'input-error': emailConferma && (datiPersonali.email !== emailConferma || !emailValida(emailConferma)) }" />
                <span v-if="emailConferma && !emailValida(emailConferma)" class="field-error">Indirizzo email non valido</span>
                <span v-else-if="emailConferma && datiPersonali.email !== emailConferma" class="field-error">Le email non coincidono</span>
              </div>
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label>Telefono</label>
                <input v-model="datiPersonali.telefono" class="input" placeholder="es. 3382781823 oppure +393382781823"
                  :class="{ 'input-error': datiPersonali.telefono && !telefonoValido(datiPersonali.telefono) }" />
                <span v-if="datiPersonali.telefono && !telefonoValido(datiPersonali.telefono)" class="field-error">Formato non valido (es. 3382781823 o +393382781823)</span>
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

        <!-- STEP 3: Riepilogo -->
        <div v-if="step === 3" class="riepilogo">

          <!-- Banner stato -->
          <div :class="['riepilogo-banner', prenotazioneConfermata?.stato === 'DA_CONFERMARE' ? 'banner-attesa' : 'banner-ok']">
            <span class="riepilogo-icon">{{ prenotazioneConfermata?.stato === 'DA_CONFERMARE' ? '⏳' : '🎉' }}</span>
            <div>
              <div class="riepilogo-titolo">
                {{ prenotazioneConfermata?.stato === 'DA_CONFERMARE' ? 'Prenotazione in attesa di approvazione' : 'Prenotazione confermata!' }}
              </div>
              <div class="riepilogo-sub">Riceverai una email a <strong>{{ datiPersonali.email }}</strong></div>
            </div>
            <span class="riepilogo-codice">{{ prenotazioneConfermata?.codice }}</span>
          </div>

          <!-- Dettaglio evento -->
          <div class="card riepilogo-card">
            <h3 class="riepilogo-section-title">🗓 Evento</h3>
            <p class="riepilogo-evento-titolo">{{ evento?.titolo }}</p>
            <p class="riepilogo-muted">{{ formatDateTime(sessione?.data_inizio) }}</p>
            <p v-if="sessione?.luoghi?.length" class="riepilogo-muted">
              📍 {{ sessione.luoghi.map(l => l.nome).join(', ') }}
            </p>
          </div>

          <!-- Posti prenotati -->
          <div class="card riepilogo-card" v-if="postiSelezionati.length">
            <h3 class="riepilogo-section-title">🪑 Posti prenotati</h3>
            <table class="riepilogo-table">
              <thead>
                <tr>
                  <th>Tipologia</th>
                  <th class="text-right">Qtà</th>
                  <th class="text-right">Costo unit.</th>
                  <th class="text-right">Totale</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in postiSelezionati" :key="p.id">
                  <td>{{ p.nome }}</td>
                  <td class="text-right">{{ p.quantita }}</td>
                  <td class="text-right">{{ p.gratuita ? 'Gratuito' : `€ ${Number(p.costoUnitario).toFixed(2)}` }}</td>
                  <td class="text-right">{{ p.gratuita ? '–' : `€ ${Number(p.costoTotale).toFixed(2)}` }}</td>
                </tr>
              </tbody>
              <tfoot v-if="totale > 0">
                <tr>
                  <td colspan="3"><strong>Totale</strong></td>
                  <td class="text-right"><strong>€ {{ totale.toFixed(2) }}</strong></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Dati prenotante -->
          <div class="card riepilogo-card">
            <h3 class="riepilogo-section-title">👤 Prenotante</h3>
            <div class="riepilogo-grid">
              <div><span class="riepilogo-label">Nome</span><span>{{ datiPersonali.nome }} {{ datiPersonali.cognome }}</span></div>
              <div><span class="riepilogo-label">Email</span><span>{{ datiPersonali.email }}</span></div>
              <div v-if="datiPersonali.telefono"><span class="riepilogo-label">Telefono</span><span>{{ datiPersonali.telefono }}</span></div>
              <div v-if="datiPersonali.note" class="riepilogo-note-row"><span class="riepilogo-label">Note</span><span>{{ datiPersonali.note }}</span></div>
            </div>
          </div>

          <div class="step-actions" style="justify-content: center;">
            <router-link :to="`/prenotazioni/${prenotazioneConfermata?.codice}?token=${prenotazioneConfermata?.token_accesso}`" class="btn btn-primary">
              Gestisci prenotazione
            </router-link>
            <router-link :to="`/vetrina/${shopUrl}/eventi/${slug}`" class="btn btn-secondary">
              Torna all'evento
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
const emailValida     = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
const telefonoValido  = (v) => /^(\+\d{1,3})?\s?\d{6,14}$/.test(v.replace(/[\s\-().]/g, ''))
const emailConferma     = ref('')
const risposte          = reactive({})
const lockToken         = ref(null)
const scadenzaSecondi   = ref(0)
const prenotazioneConfermata = ref(null)
let timer = null

const getQty = (id) => posti[id] ?? 0

const maxQtyTipologia = (id) => {
  const t = tipologie.value.find(x => x.tipologia_posto.id === id)
  if (!t) return 0
  // posti_disponibili cap (0 = illimitata per la sessione-tipologia)
  const maxDisp = t.posti_totali === 0 ? Infinity : (t.posti_disponibili ?? 0)
  // max_prenotabili configurato sulla tipologia (null = nessun limite)
  const maxConf = t.tipologia_posto.max_prenotabili ?? Infinity
  return Math.min(maxDisp, maxConf)
}

const maxQtySessione = () => {
  const s = sessione.value
  if (!s || s.posti_totali === 0) return Infinity
  const giaScelti = Object.values(posti).reduce((a, v) => a + v, 0)
  return Math.max(0, (s.posti_disponibili ?? 0) - giaScelti)
}

const setQty = (id, val) => {
  const v = Math.max(0, parseInt(val, 10) || 0)
  const maxTp = maxQtyTipologia(id)
  // per il vincolo sessione devo escludere la qty attuale di questa tipologia
  const s = sessione.value
  const sMax = (!s || s.posti_totali === 0)
    ? Infinity
    : Math.max(0, (s.posti_disponibili ?? 0) - (Object.entries(posti).filter(([k]) => Number(k) !== id).reduce((a, [, vv]) => a + vv, 0)))
  posti[id] = Math.min(v, maxTp === Infinity ? sMax : Math.min(maxTp, sMax))
}

const cambiaQty = (id, delta) => {
  const cur = posti[id] ?? 0
  const t = tipologie.value.find(x => x.tipologia_posto.id === id)
  const minQ = t?.tipologia_posto?.min_prenotabili ?? 0
  if (delta > 0) {
    const maxTp = maxQtyTipologia(id)
    const s = sessione.value
    const altreQty = Object.entries(posti).filter(([k]) => Number(k) !== id).reduce((a, [, v]) => a + v, 0)
    const sMax = (!s || s.posti_totali === 0)
      ? Infinity
      : Math.max(0, (s.posti_disponibili ?? 0) - altreQty - cur)
    const tpMax = maxTp === Infinity ? Infinity : Math.max(0, maxTp - cur)
    if (Math.min(sMax, tpMax) <= 0) return
    // Se qty era 0 e c'è un minimo > 1, salta direttamente al minimo
    if (cur === 0 && minQ > 1) {
      const jumpTo = Math.min(minQ, maxTp === Infinity ? minQ : maxTp)
      posti[id] = Math.min(jumpTo, sMax === Infinity ? jumpTo : (altreQty + jumpTo <= (s?.posti_disponibili ?? Infinity) ? jumpTo : Math.max(0, (s?.posti_disponibili ?? 0) - altreQty)))
      return
    }
  } else {
    // Decremento: se scendere sotto il minimo, azzera la tipologia (deseleziona)
    if (cur > 0 && minQ > 0 && cur - 1 < minQ) {
      posti[id] = 0
      return
    }
  }
  posti[id] = Math.max(0, cur + delta)
}

const totPosti = computed(() => Object.values(posti).reduce((s, v) => s + v, 0))

const totale = computed(() => {
  return tipologie.value.reduce((s, t) => {
    const qty  = getQty(t.tipologia_posto.id)
    const cost = t.tipologia_posto.gratuita ? 0 : (t.tipologia_posto.costo ?? 0)
    return s + qty * Number(cost)
  }, 0)
})

const postiSelezionati = computed(() => {
  return tipologie.value
    .filter(t => getQty(t.tipologia_posto.id) > 0)
    .map(t => {
      const qty = getQty(t.tipologia_posto.id)
      const costoU = t.tipologia_posto.gratuita ? 0 : Number(t.tipologia_posto.costo ?? 0)
      return {
        id: t.tipologia_posto.id,
        nome: t.tipologia_posto.nome,
        gratuita: t.tipologia_posto.gratuita,
        quantita: qty,
        costoUnitario: costoU,
        costoTotale: costoU * qty,
      }
    })
})

const postiRimasti = computed(() => {
  const s = sessione.value
  if (!s || s.posti_totali === 0) return null
  return Math.max(0, (s.posti_disponibili ?? 0) - (s.posti_riservati ?? 0))
})

const carica = async () => {
  loading.value = true
  try {
    const res = await vetrinaApi.evento(shopUrl, slug)
    evento.value    = res.data
    sessione.value  = res.data.sessioni?.find(s => s.id === sessioneId) ?? null
    tipologie.value = sessione.value?.tipologie_posto?.filter(t => t.attiva) ?? []
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
  if (totPosti.value === 0) {
    errore.value = 'Seleziona almeno un posto per continuare.'
    return
  }
  // Valida min/max prenotabili per tipologia
  for (const [tipId, qty] of Object.entries(posti)) {
    if (qty <= 0) continue
    const t = tipologie.value.find(x => x.tipologia_posto.id === Number(tipId))
    const minQ = t?.tipologia_posto?.min_prenotabili
    const maxQ = t?.tipologia_posto?.max_prenotabili
    if (minQ && qty < minQ) {
      errore.value = `Seleziona almeno ${minQ} posti per "${t.tipologia_posto.nome}".`
      return
    }
    if (maxQ && qty > maxQ) {
      errore.value = `Puoi selezionare al massimo ${maxQ} posti per "${t.tipologia_posto.nome}".`
      return
    }
  }
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
  if (!emailValida(datiPersonali.email)) {
    errore.value = 'Inserisci un indirizzo email valido.'
    return
  }
  if (datiPersonali.email !== emailConferma.value) {
    errore.value = 'I campi email non coincidono.'
    return
  }
  if (datiPersonali.telefono && !telefonoValido(datiPersonali.telefono)) {
    errore.value = 'Il numero di telefono non è valido.'
    return
  }
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
.posti-left-tp { color: #7f8c8d; font-size: .8rem; margin-top: .1rem; }
.posti-esauriti { color: #e74c3c; font-size: .8rem; font-weight: 600; margin-top: .1rem; }
.qty-hint { color: #e67e22; font-size: .78rem; margin-top: .1rem; }
.tipologia-row--esaurita { opacity: .6; }
.tipologia-row--esaurita .qty-btn:disabled,
.tipologia-row--esaurita .qty-input:disabled { cursor: not-allowed; background: #f0f0f0; color: #aaa; }
.tipologia-row { display: flex; justify-content: space-between; align-items: center; padding: .75rem; border: 1px solid #eee; border-radius: 8px; margin-bottom: .6rem; }
.tipologia-info { display: flex; flex-direction: column; }
.prezzo { color: #27ae60; font-size: .9rem; margin-top: .15rem; }
.qty-control { display: flex; align-items: center; gap: .6rem; }
.qty-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #ddd; background: white; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.qty-val { font-size: 1.1rem; font-weight: 600; min-width: 24px; text-align: center; }
.qty-input { width: 56px; text-align: center; font-size: 1.1rem; font-weight: 600; border: 1px solid #ddd; border-radius: 6px; padding: .25rem .3rem; -moz-appearance: textfield; }
.qty-input::-webkit-inner-spin-button, .qty-input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.totale { margin-top: 1rem; padding: 1rem; background: #f0f4f8; border-radius: 8px; text-align: right; font-size: 1.05rem; }
.step-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.5rem; flex-wrap: wrap; }
.countdown { background: #fef9e7; border: 1px solid #f9ca24; border-radius: 8px; padding: .7rem 1rem; margin-bottom: 1.25rem; font-weight: 600; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.required { color: #e74c3c; }
.checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; margin-top: .2rem; }
.input-error { border-color: #e74c3c !important; }
.field-error { color: #e74c3c; font-size: .8rem; margin-top: .2rem; display: block; }
/* Riepilogo posti step 2 */
.riepilogo-posti-step2 { background: #f0f4f8; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; }
.riepilogo-posti-title { font-weight: 700; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #666; margin-bottom: .5rem; }
.riepilogo-posti-row { display: flex; align-items: center; gap: .5rem; padding: .3rem 0; border-bottom: 1px solid #e0e6ed; }
.riepilogo-posti-row:last-of-type { border-bottom: none; }
.riepilogo-posti-nome { flex: 1; font-weight: 500; }
.riepilogo-posti-qty { color: #555; min-width: 40px; text-align: right; }
.riepilogo-posti-costo { min-width: 70px; text-align: right; color: #27ae60; font-weight: 600; }
.riepilogo-posti-totale { display: flex; justify-content: space-between; font-weight: 700; padding-top: .45rem; margin-top: .25rem; border-top: 2px solid #d0d9e4; }
.conferma-icon { font-size: 3rem; margin-bottom: 1rem; }
.conferma h2 { margin-bottom: .75rem; }
/* Riepilogo step 3 */
.riepilogo { display: flex; flex-direction: column; gap: 1rem; }
.riepilogo-banner { display: flex; align-items: center; gap: 1rem; border-radius: 10px; padding: 1.1rem 1.25rem; }
.banner-ok { background: #eafaf1; border: 1px solid #27ae60; }
.banner-attesa { background: #fef9e7; border: 1px solid #f9ca24; }
.riepilogo-icon { font-size: 2rem; flex-shrink: 0; }
.riepilogo-titolo { font-size: 1.1rem; font-weight: 700; }
.riepilogo-sub { font-size: .85rem; color: #555; margin-top: .15rem; }
.riepilogo-codice { margin-left: auto; font-family: monospace; font-size: 1rem; font-weight: 700; color: #1a5276; white-space: nowrap; }
.riepilogo-card { padding: 1rem 1.25rem; }
.riepilogo-section-title { font-size: .95rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #555; margin: 0 0 .75rem; }
.riepilogo-evento-titolo { font-size: 1.05rem; font-weight: 600; margin: 0 0 .2rem; }
.riepilogo-muted { color: #666; font-size: .9rem; margin: .15rem 0; }
.riepilogo-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.riepilogo-table th { text-align: left; padding: .4rem .5rem; border-bottom: 2px solid #eee; font-size: .8rem; color: #666; text-transform: uppercase; }
.riepilogo-table td { padding: .45rem .5rem; border-bottom: 1px solid #f0f0f0; }
.riepilogo-table tfoot td { border-bottom: none; border-top: 2px solid #eee; padding-top: .6rem; }
.riepilogo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem .75rem; font-size: .9rem; }
.riepilogo-note-row { grid-column: 1 / -1; }
.riepilogo-label { display: block; font-size: .75rem; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .1rem; }
.text-right { text-align: right; }
@media (max-width: 600px) {
  .riepilogo-banner { flex-wrap: wrap; }
  .riepilogo-codice { margin-left: 0; }
  .riepilogo-grid { grid-template-columns: 1fr; }
}
.codice { font-family: monospace; font-size: 1.1rem; color: #1a5276; letter-spacing: .05em; }
.loading { padding: 3rem; text-align: center; color: #aaa; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .5rem 1.1rem; cursor: pointer; text-decoration: none; }
@media (max-width: 600px) {
  .grid-2 { grid-template-columns: 1fr; }
  h1 { font-size: 1.3rem; }
  .booking-page { padding: 1rem 0; }
  .sessione-info { flex-direction: column; gap: .25rem; }
  .step-actions { flex-direction: column-reverse; }
  .step-actions .btn, .step-actions button { width: 100%; text-align: center; }
  .tipologia-row { gap: .5rem; }
  .totale { font-size: .95rem; }
  .conferma { padding: 1.5rem 1rem; }
  .conferma-icon { font-size: 2.2rem; }
}
</style>
