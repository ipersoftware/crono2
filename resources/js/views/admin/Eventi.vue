<template>
  <div>
    <!-- Header -->
    <div class="page-header">
      <h1>📋 Eventi</h1>
      <router-link :to="`/admin/${enteId}/eventi/nuovo`" class="btn btn-primary">
        + Nuovo evento
      </router-link>
    </div>

    <!-- Filtri -->
    <div class="card filtri">
      <div class="filtri-row">
        <select v-model="filtri.anno" @change="carica" class="input" style="min-width:90px">
          <option value="">Tutti gli anni</option>
          <option v-for="a in anniDisponibili" :key="a" :value="a">{{ a }}</option>
        </select>
        <input v-model="filtri.q" @input="carica" placeholder="Cerca titolo…" class="input" />
        <select v-model="filtri.stato" @change="carica" class="input">
          <option value="">Tutti gli stati</option>
          <option value="BOZZA">Bozza</option>
          <option value="PUBBLICATO">Pubblicato</option>
          <option value="SOSPESO">Sospeso</option>
          <option value="ANNULLATO">Annullato</option>
        </select>
      </div>
    </div>

    <!-- Lista -->
    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="eventi.length === 0" class="empty">Nessun evento trovato.</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Titolo</th>
            <th>Stato</th>
            <th>Sessioni</th>
            <th>Creato il</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ev in eventi" :key="ev.id">
            <td data-label="Titolo">
              <strong>{{ ev.titolo }}</strong>
              <div class="muted">{{ ev.slug }}</div>
            </td>
            <td data-label="Stato">
              <span :class="['badge', `badge-${ev.stato.toLowerCase()}`]">
                {{ ev.stato }}
              </span>
            </td>
            <td data-label="Sessioni">{{ ev.sessioni_count ?? '–' }}</td>
            <td data-label="Creato il">{{ formatData(ev.created_at) }}</td>
            <td data-label="Azioni" class="actions">
              <router-link :to="`/admin/${enteId}/eventi/${ev.id}/sessioni`" class="btn btn-sm btn-secondary">
                Sessioni
              </router-link>
              <button @click="apriMonitoraggio(ev)" class="btn btn-sm btn-monitor">📊 Monitoraggio</button>
              <router-link :to="`/admin/${enteId}/eventi/${ev.id}`" class="btn btn-sm btn-primary">
                Modifica
              </router-link>
              <button
                v-if="ev.stato === 'BOZZA'"
                @click="pubblica(ev)"
                class="btn btn-sm btn-success"
              >Pubblica</button>
              <button
                v-if="ev.stato === 'PUBBLICATO'"
                @click="sospendi(ev)"
                class="btn btn-sm btn-warning"
              >Sospendi</button>
              <button @click="elimina(ev)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Dialog Monitoraggio -->
  <div v-if="monitDialog" class="modal-backdrop" @click.self="monitDialog = false">
    <div class="modal-box">
      <div class="modal-head">
        <h2>📊 Monitoraggio — {{ monitDati?.evento?.titolo }}</h2>
        <button @click="monitDialog = false" class="btn-close">&times;</button>
      </div>

      <div v-if="monitLoading" class="monit-loading">Caricamento…</div>
      <div v-else-if="monitDati">

        <!-- Riepilogo globale -->
        <div class="monit-riepilogo">
          <div class="monit-card monit-card--click" @click="apriPrenotazioni(null, null)">
            <div class="monit-val">{{ monitDati.riepilogo.prenotazioni_attive }}</div>
            <div class="monit-label">Prenotazioni attive</div>
          </div>
          <div class="monit-card monit-card--click" @click="apriPrenotazioni(null, 'CONFERMATA')">
            <div class="monit-val">{{ monitDati.riepilogo.prenotazioni_confermate }}</div>
            <div class="monit-label">Confermate</div>
          </div>
          <div class="monit-card monit-card--click" @click="apriPrenotazioni(null, 'DA_CONFERMARE')">
            <div class="monit-val monit-warn">{{ monitDati.riepilogo.prenotazioni_da_confermare }}</div>
            <div class="monit-label">Da confermare</div>
          </div>
          <div class="monit-card monit-card--click" @click="apriPrenotazioni(null, 'ANNULLATA_UTENTE')">
            <div class="monit-val monit-muted">{{ monitDati.riepilogo.prenotazioni_annullate }}</div>
            <div class="monit-label">Annullate</div>
          </div>
          <div class="monit-card">
            <div class="monit-val">{{ monitDati.riepilogo.posti_totali > 0 ? monitDati.riepilogo.posti_totali : '∞' }}</div>
            <div class="monit-label">Posti totali</div>
          </div>
          <div class="monit-card">
            <div class="monit-val" :class="monitDati.riepilogo.posti_disponibili === 0 ? 'monit-danger' : 'monit-ok'">{{ monitDati.riepilogo.posti_totali > 0 ? monitDati.riepilogo.posti_disponibili : '∞' }}</div>
            <div class="monit-label">Posti disponibili</div>
          </div>
        </div>

        <!-- Tabella sessioni -->
        <h3 class="monit-section">Sessioni</h3>
        <div class="monit-table-wrap">
          <table class="monit-table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Luogo</th>
                <th>Posti tot.</th>
                <th>Disponibili</th>
                <th>In attesa</th>
                <th>Prenotaz. attive</th>
                <th>Confermate</th>
                <th>Da conf.</th>
                <th>Annullate</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="s in monitDati.sessioni" :key="s.id">
                <tr>
                  <td>{{ formatDataOra(s.data_inizio) }}</td>
                  <td>{{ s.luoghi.join(', ') || '–' }}</td>
                  <td class="text-center">{{ s.posti_totali > 0 ? s.posti_totali : '∞' }}</td>
                  <td class="text-center" :class="s.posti_totali > 0 && s.posti_disponibili === 0 ? 'monit-danger' : ''">
                    {{ s.posti_totali > 0 ? s.posti_disponibili : '∞' }}
                  </td>
                  <td class="text-center">{{ s.posti_in_attesa || 0 }}</td>
                  <td class="text-center fw link-cell" @click="apriPrenotazioni(s.id, null)">{{ s.prenotazioni_attive }}</td>
                  <td class="text-center link-cell" @click="apriPrenotazioni(s.id, 'CONFERMATA')">{{ s.prenotazioni_confermate }}</td>
                  <td class="text-center monit-warn link-cell" @click="apriPrenotazioni(s.id, 'DA_CONFERMARE')">{{ s.prenotazioni_da_confermare }}</td>
                  <td class="text-center monit-muted link-cell" @click="apriPrenotazioni(s.id, 'ANNULLATA_UTENTE')">{{ s.prenotazioni_annullate }}</td>
                </tr>
                <tr v-if="s.tipologie.length" class="row-tipologie">
                  <td colspan="9">
                    <span v-for="tp in s.tipologie" :key="tp.nome" class="tp-chip">
                      <strong>{{ tp.nome }}</strong>:
                      <span v-if="tp.posti_totali === 0" class="tp-illim">Illim.</span>
                      <span v-else :class="tp.posti_disponibili === 0 ? 'tp-esaurito' : ''">
                        {{ tp.posti_disponibili }} / {{ tp.posti_totali }}
                      </span>
                      <span v-if="tp.posti_riservati > 0" class="tp-lock" :title="tp.posti_riservati + ' posti in lock temporaneo'">🔒{{ tp.posti_riservati }}</span>
                    </span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pannello prenotazioni drill-down -->
        <div v-if="prenotPanel" class="prenotp">
          <div class="prenotp-head">
            <span class="prenotp-title">
              Prenotazioni
              <span v-if="prenotFiltroStato" :class="['badge-stato', `bs-${prenotFiltroStato.toLowerCase()}`]">{{ statoLabel(prenotFiltroStato) }}</span>
              <span v-if="prenotFiltroSessione" class="prenotp-sess"> — sessione {{ formatDataOra(monitDati.sessioni.find(s=>s.id===prenotFiltroSessione)?.data_inizio) }}</span>
            </span>
            <button class="btn-close-sm" @click="prenotPanel = false">&times;</button>
          </div>
          <div v-if="!prenotLoading" class="prenotp-search">
            <input
              v-model="prenotCerca"
              placeholder="🔍 Cerca codice, nome, cognome, email…"
              class="input prenotp-input"
            />
            <span class="prenotp-count">{{ prenotazioniFiltrate.length }} / {{ prenotazioni.length }}</span>
          </div>
          <div v-if="prenotLoading" class="monit-loading">Caricamento…</div>
          <div v-else-if="prenotazioniFiltrate.length === 0" class="prenotp-empty">Nessuna prenotazione trovata.</div>
          <table v-else class="monit-table">
            <thead>
              <tr>
                <th>Codice</th>
                <th>Nominativo</th>
                <th>Email</th>
                <th>Posti</th>
                <th>Stato</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in prenotazioniFiltrate" :key="p.id">
                <td data-label="Codice"><code>{{ p.codice }}</code></td>
                <td data-label="Nominativo">{{ p.cognome }} {{ p.nome }}</td>
                <td data-label="Email">{{ p.email }}</td>
                <td data-label="Posti" class="text-center">{{ p.posti?.reduce((a,pp)=>a+pp.quantita,0) ?? '\u2013' }}</td>
                <td data-label="Stato"><span :class="['badge-stato', `bs-${p.stato.toLowerCase()}`]">{{ statoLabel(p.stato) }}</span></td>
                <td data-label="Data">{{ formatDataOra(p.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import api from '@/api'
import { eventiApi } from '@/api/eventi'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const enteId = route.params.enteId

const eventi = ref([])
const loading = ref(false)
const annoCorrente = new Date().getFullYear()
const anniDisponibili = computed(() => {
  const anni = []
  for (let a = annoCorrente + 1; a >= 2022; a--) anni.push(a)
  return anni
})
const filtri = reactive({ q: '', stato: '', anno: annoCorrente })

const carica = async () => {
  loading.value = true
  try {
    const res = await eventiApi.index(enteId, filtri)
    eventi.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

const pubblica = async (ev) => {
  await eventiApi.pubblica(enteId, ev.id)
  ev.stato = 'PUBBLICATO'
}

const sospendi = async (ev) => {
  await eventiApi.sospendi(enteId, ev.id)
  ev.stato = 'SOSPESO'
}

const elimina = async (ev) => {
  if (!confirm(`Eliminare "${ev.titolo}"?`)) return
  await eventiApi.destroy(enteId, ev.id)
  eventi.value = eventi.value.filter(e => e.id !== ev.id)
}

const formatData = (d) => d ? new Date(d).toLocaleDateString('it-IT') : '–'
const formatDataOra = (d) => d ? new Date(d).toLocaleString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '–'

// ─── Monitoraggio ────────────────────────────────────────────
const monitDialog  = ref(false)
const monitLoading = ref(false)
const monitDati    = ref(null)

const apriMonitoraggio = async (ev) => {
  monitDialog.value  = true
  monitLoading.value = true
  monitDati.value    = null
  monitEventoId.value = ev.id
  prenotPanel.value  = false
  try {
    const res = await api.get(`/enti/${enteId}/eventi/${ev.id}/monitoraggio`)
    monitDati.value = res.data
  } finally {
    monitLoading.value = false
  }
}

// ─── Drill-down prenotazioni ───

const monitEventoId        = ref(null)
const prenotPanel          = ref(false)
const prenotLoading        = ref(false)
const prenotazioni         = ref([])
const prenotFiltroSessione = ref(null)
const prenotFiltroStato    = ref(null)
const prenotCerca          = ref('')

const prenotazioniFiltrate = computed(() => {
  const q = prenotCerca.value.trim().toLowerCase()
  if (!q) return prenotazioni.value
  return prenotazioni.value.filter(p =>
    (p.codice ?? '').toLowerCase().includes(q) ||
    (p.nome ?? '').toLowerCase().includes(q) ||
    (p.cognome ?? '').toLowerCase().includes(q) ||
    (p.email ?? '').toLowerCase().includes(q)
  )
})

const apriPrenotazioni = async (sessioneId, stato) => {
  prenotPanel.value          = true
  prenotLoading.value        = true
  prenotFiltroSessione.value = sessioneId
  prenotFiltroStato.value    = stato
  prenotazioni.value         = []
  prenotCerca.value          = ''

  const params = { per_page: 200 }
  if (sessioneId) params.sessione_id = sessioneId
  else            params.evento_id   = monitEventoId.value

  // Per annullate mostriamo entrambe le causali
  if (stato === 'ANNULLATA_UTENTE') {
    const [r1, r2] = await Promise.all([
      api.get(`/enti/${enteId}/prenotazioni`, { params: { ...params, stato: 'ANNULLATA_UTENTE' } }),
      api.get(`/enti/${enteId}/prenotazioni`, { params: { ...params, stato: 'ANNULLATA_OPERATORE' } }),
    ])
    prenotazioni.value = [...(r1.data.data ?? r1.data), ...(r2.data.data ?? r2.data)]
  } else {
    if (stato) params.stato = stato
    const res = await api.get(`/enti/${enteId}/prenotazioni`, { params })
    prenotazioni.value = res.data.data ?? res.data
  }
  prenotLoading.value = false
}

const statoLabel = (s) => ({
  CONFERMATA:          'Confermata',
  DA_CONFERMARE:       'Da confermare',
  RISERVATA:           'Riservata',
  ANNULLATA_UTENTE:    'Annullata (utente)',
  ANNULLATA_OPERATORE: 'Annullata (operatore)',
}[s] ?? s)

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .5rem; }
.filtri { margin-bottom: 1rem; }
.filtri-row { display: flex; gap: .75rem; flex-wrap: wrap; }
.input { padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #888; }
.muted { font-size: .78rem; color: #999; margin-top: .15rem; }
.actions { display: flex; gap: .4rem; flex-wrap: wrap; }
.badge { padding: .25rem .6rem; border-radius: 12px; font-size: .78rem; font-weight: 600; text-transform: uppercase; }
.badge-bozza      { background: #eee; color: #555; }
.badge-pubblicato { background: #d5f5e3; color: #1a7a45; }
.badge-sospeso    { background: #fdebd0; color: #a04000; }
.badge-annullato  { background: #fadbd8; color: #a93226; }
.btn-monitor { background: #8e44ad; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-monitor:hover { background: #7d3c98; }
/* Modal monitoraggio */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
.modal-box { background: #fff; border-radius: 10px; width: 100%; max-width: 1000px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; }
.modal-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 1.25rem 1.5rem; border-bottom: 1px solid #eee; gap: .75rem; }
.modal-head h2 { margin: 0; font-size: 1.15rem; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #555; line-height: 1; }
.monit-loading { padding: 3rem; text-align: center; color: #888; }
.monit-riepilogo { display: flex; flex-wrap: wrap; gap: .75rem; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f0f0f0; }
.monit-card { background: #f8f9fa; border-radius: 8px; padding: .75rem 1.1rem; min-width: 110px; text-align: center; }
.monit-val { font-size: 1.5rem; font-weight: 700; color: #2c3e50; }
.monit-label { font-size: .72rem; color: #888; margin-top: .15rem; }
.monit-warn { color: #e67e22; }
.monit-muted { color: #95a5a6; }
.monit-ok { color: #27ae60; }
.monit-danger { color: #e74c3c; font-weight: 700; }
.monit-section { margin: 1rem 1.5rem .5rem; font-size: .95rem; color: #555; }
.monit-table-wrap { padding: 0 1.5rem 1.5rem; overflow-x: auto; }
.monit-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.monit-table th { background: #f0f4f8; padding: .5rem .65rem; text-align: left; font-weight: 600; white-space: nowrap; }
.monit-table td { padding: .45rem .65rem; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
.monit-table .text-center { text-align: center; }
.monit-table .fw { font-weight: 600; }
.monit-card--click { cursor: pointer; transition: box-shadow .15s; }
.monit-card--click:hover { box-shadow: 0 0 0 2px #8e44ad44; background: #f3eaff; }
.link-cell { cursor: pointer; text-decoration: underline dotted; }
.link-cell:hover { background: #f3eaff; }
.prenotp { border-top: 2px solid #e8e4f0; margin: 0 1.5rem 1.5rem; padding-top: 1rem; }
.prenotp-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .75rem; }
.prenotp-title { font-weight: 600; font-size: .95rem; display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.prenotp-sess { font-weight: 400; color: #666; font-size: .85rem; }
.prenotp-search { display: flex; align-items: center; gap: .75rem; margin-bottom: .6rem; }
.prenotp-input { flex: 1; font-size: .875rem; padding: .35rem .7rem; }
.prenotp-count { font-size: .78rem; color: #999; white-space: nowrap; }
.prenotp-empty { padding: 1.5rem; text-align: center; color: #aaa; font-style: italic; }
.btn-close-sm { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #888; line-height: 1; }
.badge-stato { display: inline-block; border-radius: 8px; padding: .1rem .5rem; font-size: .75rem; font-weight: 600; }
.bs-confermata            { background: #d5f5e3; color: #1a7a45; }
.bs-da_confermare         { background: #fdebd0; color: #a04000; }
.bs-riservata             { background: #d6eaf8; color: #1a5276; }
.bs-annullata_utente      { background: #fadbd8; color: #a93226; }
.bs-annullata_operatore   { background: #fadbd8; color: #a93226; }
.row-tipologie td { background: #fafafa; padding: .3rem .65rem; }
.tp-chip { display: inline-flex; align-items: center; gap: .25rem; background: #eaf2ff; color: #1a5276; border-radius: 10px; padding: .1rem .6rem; font-size: .78rem; margin: .1rem .2rem; }
.tp-esaurito { color: #c0392b; font-weight: 600; }
.tp-illim { color: #1a5276; opacity: .65; font-style: italic; }
.tp-lock { font-size: .72rem; color: #7d6608; background: #fef9c3; border-radius: 6px; padding: 0 .3rem; cursor: help; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
.btn-success  { background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-warning  { background: #f39c12; color: white; border: none; border-radius: 4px; cursor: pointer; }

@media (max-width: 640px) {
  /* ── tabella eventi principale ── */
  .filtri-row { flex-direction: column; }
  .filtri-row .input { width: 100%; box-sizing: border-box; }
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
  .table tr { border: 1px solid #e8eaed; border-radius: 8px; margin-bottom: .75rem; padding: .5rem 0; background: white; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .table td { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; padding: .45rem .75rem; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
  .table td:last-child { border-bottom: none; }
  .table td::before { content: attr(data-label); font-weight: 600; color: #777; font-size: .78rem; text-transform: uppercase; white-space: nowrap; padding-top: .1rem; min-width: 80px; flex-shrink: 0; }
  .actions { justify-content: flex-end; flex-wrap: wrap; }

  /* ── tabella sessioni monitoraggio ── */
  .monit-table-wrap { padding: 0 .75rem 1rem; }
  .monit-section { margin: 1rem .75rem .5rem; }
  .monit-table thead { display: none; }
  .monit-table, .monit-table tbody, .monit-table tr, .monit-table td { display: block; width: 100%; box-sizing: border-box; }
  .monit-table tr { border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: .75rem; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .monit-table td { display: flex; justify-content: space-between; align-items: center; padding: .45rem .75rem; border-bottom: 1px solid #f5f5f5; font-size: .85rem; gap: .5rem; white-space: normal; }
  .monit-table td:last-child { border-bottom: none; }
  .monit-table td::before { content: attr(data-label); font-weight: 600; color: #666; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; min-width: 90px; flex-shrink: 0; }
  .monit-table .text-center { text-align: right; }
  .row-tipologie td { display: block; }
  .row-tipologie td::before { display: none; }

  /* ── prenotazioni drill-down ── */
  .prenotp { margin: 0 .75rem 1rem; }
  .prenotp-search { flex-wrap: wrap; }
  .prenotp-input { min-width: 0; }

  /* ── riepilogo card ── */
  .monit-riepilogo { padding: .75rem; }
  .monit-card { min-width: 80px; padding: .6rem .75rem; }
  .monit-val { font-size: 1.25rem; }
}
</style>
