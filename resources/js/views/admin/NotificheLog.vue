<template>
  <div>
    <div class="page-header">
      <h1>✉ Log notifiche email</h1>
    </div>

    <!-- Filtri -->
    <div class="card filtri">
      <div class="filtri-row">
        <input v-model="filtri.dal"  type="date" class="input" title="Dal" />
        <input v-model="filtri.al"   type="date" class="input" title="Al" />
        <select v-model="filtri.tipo" class="input">
          <option value="">Tutti i tipi</option>
          <optgroup label="Prenotazioni">
            <option value="PRENOTAZIONE_CONFERMATA">Prenotazione confermata</option>
            <option value="PRENOTAZIONE_DA_CONFERMARE">Da confermare</option>
            <option value="PRENOTAZIONE_APPROVATA">Approvata</option>
            <option value="PRENOTAZIONE_ANNULLATA_UTENTE">Annullata (utente)</option>
            <option value="PRENOTAZIONE_ANNULLATA_OPERATORE">Annullata (operatore)</option>
            <option value="PRENOTAZIONE_NOTIFICA_STAFF">Notifica staff</option>
          </optgroup>
          <optgroup label="Evento">
            <option value="EVENTO_ANNULLATO">Evento annullato</option>
            <option value="REMINDER_EVENTO">Reminder evento</option>
          </optgroup>
          <optgroup label="Lista attesa">
            <option value="LISTA_ATTESA_ISCRIZIONE">Iscrizione lista attesa</option>
            <option value="LISTA_ATTESA_POSTO_DISPONIBILE">Posto disponibile</option>
            <option value="LISTA_ATTESA_SCADENZA">Scadenza lista attesa</option>
          </optgroup>
          <optgroup label="Account">
            <option value="REGISTRAZIONE_CONFERMATA">Registrazione confermata</option>
            <option value="RESET_PASSWORD">Reset password</option>
            <option value="BENVENUTO_OPERATORE">Benvenuto operatore</option>
          </optgroup>
        </select>
        <select v-model="filtri.stato" class="input">
          <option value="">Tutti gli stati</option>
          <option value="INVIATA">✅ Inviata</option>
          <option value="IN_CODA">🕐 In coda</option>
          <option value="ERRORE">❌ Errore</option>
        </select>
        <button @click="carica(1)" class="btn btn-primary btn-sm">Filtra</button>
        <button @click="resetFiltri" class="btn btn-secondary btn-sm">Reset</button>
      </div>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="!log.length" class="empty">Nessuna notifica trovata con i filtri selezionati.</div>
      <template v-else>
        <table class="table">
          <thead>
            <tr>
              <th>Data / Ora</th>
              <th>Tipo</th>
              <th>Destinatario</th>
              <th>Oggetto</th>
              <th>Prenotazione</th>
              <th>Stato</th>
              <th>Tentativo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in log" :key="r.id" :class="{ 'row-error': r.stato === 'ERRORE' }">
              <td data-label="Data / Ora" class="nowrap">{{ formatDataOra(r.created_at) }}</td>
              <td data-label="Tipo">
                <span :class="['tipo-badge', tipoCategoria(r.tipo)]">{{ tipoLabel(r.tipo) }}</span>
              </td>
              <td data-label="Destinatario" class="email">{{ r.destinatario_email }}</td>
              <td data-label="Oggetto" class="oggetto" :title="r.oggetto">{{ r.oggetto }}</td>
              <td data-label="Prenotazione">
                <span v-if="r.prenotazione" class="codice-prenotazione">{{ r.prenotazione.codice }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td data-label="Stato">
                <span :class="['stato-badge', r.stato.toLowerCase()]">{{ statoLabel(r.stato) }}</span>
                <div v-if="r.stato === 'ERRORE' && r.errore" class="errore-detail">{{ r.errore }}</div>
              </td>
              <td data-label="Tentativo" class="center">{{ r.tentativo }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Paginazione -->
        <div v-if="meta.last_page > 1" class="paginazione">
          <button
            v-for="p in meta.last_page" :key="p"
            :class="['btn btn-sm', p === meta.current_page ? 'btn-primary' : 'btn-secondary']"
            @click="carica(p)"
          >{{ p }}</button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { notificheLogApi } from '@/api/admin'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const log     = ref([])
const loading = ref(false)
const meta    = ref({ current_page: 1, last_page: 1 })

const oggi      = new Date().toISOString().slice(0, 10)
const treggiFa  = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

const filtri = reactive({
  dal:   treggiFa,
  al:    oggi,
  tipo:  '',
  stato: '',
})

const carica = async (page = 1) => {
  loading.value = true
  try {
    const params = { per_page: 50, page, ...filtri }
    if (!params.dal)   delete params.dal
    if (!params.al)    delete params.al
    if (!params.tipo)  delete params.tipo
    if (!params.stato) delete params.stato

    const { data } = await notificheLogApi.index(enteId, params)
    log.value  = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page }
  } catch (e) {
    console.error('Errore caricamento log notifiche', e)
  } finally {
    loading.value = false
  }
}

const resetFiltri = () => {
  filtri.dal   = treggiFa
  filtri.al    = oggi
  filtri.tipo  = ''
  filtri.stato = ''
  carica(1)
}

const formatDataOra = (d) => d
  ? new Date(d).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })
  : '—'

// Mappa tipo → etichetta leggibile
const TIPO_LABELS = {
  PRENOTAZIONE_CONFERMATA:          'Confermata',
  PRENOTAZIONE_DA_CONFERMARE:       'Da confermare',
  PRENOTAZIONE_APPROVATA:           'Approvata',
  PRENOTAZIONE_ANNULLATA_UTENTE:    'Annullata (utente)',
  PRENOTAZIONE_ANNULLATA_OPERATORE: 'Annullata (oper.)',
  PRENOTAZIONE_NOTIFICA_STAFF:      'Notifica staff',
  EVENTO_ANNULLATO:                 'Evento annullato',
  REMINDER_EVENTO:                  'Reminder evento',
  LISTA_ATTESA_ISCRIZIONE:          'Lista attesa',
  LISTA_ATTESA_POSTO_DISPONIBILE:   'Posto disponibile',
  LISTA_ATTESA_SCADENZA:            'Scadenza att.',
  REGISTRAZIONE_CONFERMATA:         'Registrazione',
  RESET_PASSWORD:                   'Reset password',
  BENVENUTO_OPERATORE:              'Benvenuto oper.',
}
const tipoLabel = (t) => TIPO_LABELS[t] ?? t

// Categoria per colore badge
const tipoCategoria = (t) => {
  if (t?.startsWith('PRENOTAZIONE_')) return 'cat-prenotazione'
  if (t?.startsWith('EVENTO_') || t === 'REMINDER_EVENTO') return 'cat-evento'
  if (t?.startsWith('LISTA_ATTESA_')) return 'cat-lista-attesa'
  return 'cat-account'
}

const statoLabel = (s) => ({
  INVIATA:   '✅ Inviata',
  IN_CODA:   '🕐 In coda',
  ERRORE:    '❌ Errore',
}[s] ?? s)

onMounted(() => carica())
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.filtri { margin-bottom: 1rem; }
.filtri-row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }
.input { padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #888; }

.table { width: 100%; border-collapse: collapse; font-size: .875rem; }
.table th { text-align: left; border-bottom: 2px solid #e0e0e0; padding: .5rem .7rem; color: #555; font-weight: 600; }
.table td { padding: .5rem .7rem; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
.table tr:last-child td { border-bottom: none; }
.row-error td { background: #fff8f8; }

.email   { font-size: .82rem; color: #444; max-width: 180px; word-break: break-all; }
.oggetto { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .82rem; color: #555; }
.nowrap  { white-space: nowrap; }
.center  { text-align: center; }
.muted   { color: #bbb; font-size: .82rem; }

.codice-prenotazione {
  font-family: monospace;
  font-size: .82rem;
  background: #f0f4ff;
  color: #3730a3;
  padding: .1rem .4rem;
  border-radius: 4px;
}

/* Badge tipo */
.tipo-badge {
  display: inline-block;
  border-radius: 4px;
  padding: .15rem .45rem;
  font-size: .75rem;
  font-weight: 600;
  white-space: nowrap;
}
.cat-prenotazione { background: #dbeafe; color: #1d4ed8; }
.cat-evento       { background: #fef3c7; color: #b45309; }
.cat-lista-attesa { background: #ede9fe; color: #6d28d9; }
.cat-account      { background: #f0fdf4; color: #166534; }

/* Badge stato */
.stato-badge {
  display: inline-block;
  border-radius: 12px;
  padding: .2rem .6rem;
  font-size: .8rem;
  font-weight: 600;
  white-space: nowrap;
}
.stato-badge.inviata   { background: #d5f5e3; color: #1a7a45; }
.stato-badge.in_coda   { background: #fef9c3; color: #854d0e; }
.stato-badge.errore    { background: #fadbd8; color: #a93226; }

.errore-detail {
  margin-top: .3rem;
  font-size: .75rem;
  color: #c0392b;
  font-family: monospace;
  word-break: break-all;
  max-width: 240px;
}

.paginazione { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: 1rem; justify-content: center; }
</style>
