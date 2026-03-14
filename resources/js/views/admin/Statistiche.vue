<template>
  <div class="statistiche">
    <div class="page-header">
      <h1>📊 Statistiche</h1>
      <!-- Filtri globali -->
      <div class="filtri-row">
        <label class="filtro-label">Dal
          <input v-model="filtri.dal" type="date" class="input" @change="caricaTutto" />
        </label>
        <label class="filtro-label">Al
          <input v-model="filtri.al" type="date" class="input" @change="caricaTutto" />
        </label>
        <label class="filtro-label">Evento
          <select v-model="filtri.evento_id" class="input" @change="caricaTutto">
            <option value="">Tutti gli eventi</option>
            <option v-for="e in eventi" :key="e.id" :value="e.id">{{ e.titolo }}</option>
          </select>
        </label>
      </div>
    </div>

    <div v-if="loadingInit" class="loading-full">Caricamento…</div>
    <template v-else>

      <!-- ── KPI Cards ──────────────────────────────────────────── -->
      <div class="kpi-grid">
        <div class="kpi-card kpi-blu">
          <div class="kpi-label">Prenotazioni confermate</div>
          <div class="kpi-value">{{ fmt(kpi.confermate) }}</div>
        </div>
        <div class="kpi-card kpi-verde">
          <div class="kpi-label">Posti prenotati</div>
          <div class="kpi-value">{{ fmt(kpi.posti_prenotati) }}</div>
        </div>
        <div class="kpi-card kpi-arancio">
          <div class="kpi-label">Tasso annullamento</div>
          <div class="kpi-value">{{ kpi.tasso_annullamento }}%</div>
        </div>
        <div class="kpi-card kpi-verde-scuro">
          <div class="kpi-label">Ricavi totali</div>
          <div class="kpi-value">€ {{ fmtCurrency(kpi.ricavi) }}</div>
        </div>
        <div class="kpi-card kpi-viola">
          <div class="kpi-label">In lista d'attesa</div>
          <div class="kpi-value">{{ fmt(kpi.lista_attesa) }}</div>
        </div>
      </div>

      <!-- ── Riga grafici principali ────────────────────────────── -->
      <div class="grafici-row">
        <!-- Andamento mensile (line chart) -->
        <div class="card grafico-card grafico-large">
          <h2 class="card-title">📈 Andamento prenotazioni</h2>
          <div v-if="!andamento.length" class="empty-chart">Nessun dato nel periodo</div>
          <div v-else class="chart-wrap line-chart">
            <svg :viewBox="`0 0 ${svgW} ${svgH}`" preserveAspectRatio="none" class="chart-svg">
              <!-- griglia y -->
              <line v-for="y in gridY" :key="y" :x1="padL" :x2="svgW - padR" :y1="yScale(y)" :y2="yScale(y)"
                    stroke="#eee" stroke-width="1" />
              <!-- etichette y -->
              <text v-for="y in gridY" :key="'yl'+y" :x="padL - 6" :y="yScale(y) + 4"
                    text-anchor="end" class="chart-label">{{ y }}</text>
              <!-- linee -->
              <polyline :points="linePoints(andamento, 'confermate')" fill="none" stroke="#4a90d9" stroke-width="2.5" stroke-linejoin="round" />
              <polyline :points="linePoints(andamento, 'annullate')"   fill="none" stroke="#e74c3c" stroke-width="2" stroke-linejoin="round" stroke-dasharray="5,3" />
              <!-- etichette x (mesi) -->
              <text v-for="(row, i) in andamento" :key="'xl'+i"
                    :x="xPos(i, andamento.length)" :y="svgH - 4"
                    text-anchor="middle" class="chart-label">{{ row.mese.slice(5) }}</text>
            </svg>
            <div class="chart-legend">
              <span class="legend-item"><span class="dot" style="background:#4a90d9"></span>Confermate</span>
              <span class="legend-item"><span class="dot" style="background:#e74c3c"></span>Annullate</span>
            </div>
          </div>
        </div>

        <!-- Distribuzione stati (donut chart) -->
        <div class="card grafico-card">
          <h2 class="card-title">🍩 Distribuzione stati</h2>
          <div v-if="!statiData.length" class="empty-chart">Nessun dato</div>
          <div v-else class="donut-wrap">
            <svg viewBox="0 0 120 120" class="donut-svg">
              <circle cx="60" cy="60" r="46" fill="none" stroke="#f4f5f7" stroke-width="24" />
              <circle
                v-for="(seg, i) in donutSegments" :key="i"
                cx="60" cy="60" r="46"
                fill="none"
                :stroke="seg.color"
                stroke-width="24"
                :stroke-dasharray="`${seg.dash} ${seg.gap}`"
                :stroke-dashoffset="seg.offset"
                transform="rotate(-90 60 60)"
              />
              <text x="60" y="64" text-anchor="middle" class="donut-center">{{ totaleStati }}</text>
            </svg>
            <ul class="donut-legend">
              <li v-for="(seg, i) in donutSegments" :key="i">
                <span class="dot" :style="{ background: seg.color }"></span>
                <span class="donut-stato">{{ seg.label }}</span>
                <span class="donut-n">{{ seg.n }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ── Top 10 eventi ─────────────────────────────────────── -->
      <div class="card">
        <h2 class="card-title">🏆 Top 10 eventi per prenotazioni</h2>
        <div v-if="!topEventi.length" class="empty-chart">Nessun dato</div>
        <div v-else class="bar-chart-h">
          <div v-for="ev in topEventi" :key="ev.id" class="bar-h-row">
            <div class="bar-h-label" :title="ev.titolo">{{ ev.titolo }}</div>
            <div class="bar-h-track">
              <div class="bar-h-fill"
                :style="{ width: barWidthPct(ev.n_prenotazioni, topEventi[0].n_prenotazioni) + '%',
                          background: occupColor(ev.tasso_occupazione) }">
              </div>
            </div>
            <div class="bar-h-val">{{ ev.n_prenotazioni }} <span class="muted">({{ ev.tasso_occupazione ?? '–' }}%)</span></div>
          </div>
        </div>
      </div>

      <!-- ── Riga grafici secondari ─────────────────────────────── -->
      <div class="grafici-row">
        <!-- Giorni settimana -->
        <div class="card grafico-card">
          <h2 class="card-title">📅 Giorno della settimana</h2>
          <div v-if="!giorniData.length" class="empty-chart">Nessun dato</div>
          <div v-else class="bar-chart-v">
            <div v-for="d in giorniData" :key="d.giorno" class="bar-v-col">
              <div class="bar-v-track">
                <div class="bar-v-fill" :style="{ height: barHeightPct(d.n, maxGiorni) + '%', background: '#4a90d9' }"></div>
              </div>
              <div class="bar-v-label">{{ d.giorno }}</div>
              <div class="bar-v-val">{{ d.n }}</div>
            </div>
          </div>
        </div>

        <!-- Fasce orarie -->
        <div class="card grafico-card">
          <h2 class="card-title">🕐 Fascia oraria</h2>
          <div v-if="!fasceData.length" class="empty-chart">Nessun dato</div>
          <div v-else class="bar-chart-v bar-chart-v--ore">
            <div v-for="d in fasceData" :key="d.ora" class="bar-v-col">
              <div class="bar-v-track">
                <div class="bar-v-fill" :style="{ height: barHeightPct(d.n, maxFasce) + '%', background: '#a29bfe' }"></div>
              </div>
              <div class="bar-v-label">{{ d.ora.slice(0,2) }}</div>
            </div>
          </div>
        </div>

        <!-- Tipologie posto -->
        <div class="card grafico-card">
          <h2 class="card-title">🎫 Tipologie posto</h2>
          <div v-if="!tipologieData.length" class="empty-chart">Nessun dato</div>
          <ul v-else class="tipologie-list">
            <li v-for="t in tipologieData" :key="t.id" class="tipologia-row">
              <span class="tipologia-nome">{{ t.nome }}</span>
              <span class="tipologia-q">{{ fmt(t.quantita) }} posti</span>
              <span v-if="t.ricavo > 0" class="tipologia-ricavo">€ {{ fmtCurrency(t.ricavo) }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Lista d'attesa ─────────────────────────────────────── -->
      <div class="card" v-if="listaAttesaData.length">
        <h2 class="card-title">⏳ Lista d'attesa — dettaglio</h2>
        <table class="table">
          <thead>
            <tr>
              <th>Evento</th>
              <th>In attesa</th>
              <th>Notificati</th>
              <th>Convertiti</th>
              <th>Tasso conversione</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in listaAttesaData" :key="r.id">
              <td>{{ r.titolo }}</td>
              <td>{{ r.in_attesa }}</td>
              <td>{{ r.notificati }}</td>
              <td>{{ r.convertiti }}</td>
              <td>
                <span :class="['badge', r.tasso_conversione >= 50 ? 'badge-verde' : 'badge-arancio']">
                  {{ r.tasso_conversione }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Occupazione sessioni ───────────────────────────────── -->
      <div class="card" v-if="occupazioneData.length">
        <h2 class="card-title">🪑 Tasso occupazione per evento</h2>
        <div class="bar-chart-h">
          <div v-for="ev in occupazioneData" :key="ev.id" class="bar-h-row">
            <div class="bar-h-label" :title="ev.titolo">{{ ev.titolo }}</div>
            <div class="bar-h-track">
              <div class="bar-h-fill" :style="{ width: (ev.tasso ?? 0) + '%', background: occupColor(ev.tasso) }"></div>
            </div>
            <div class="bar-h-val">{{ ev.tasso ?? 0 }}%</div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { statisticheApi } from '@/api/admin'
import { eventiApi } from '@/api/eventi'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

// ── Filtri ────────────────────────────────────────────────────────────────
const oggi     = new Date().toISOString().slice(0, 10)
const annoInizio = new Date().getFullYear() + '-01-01'

const filtri = reactive({ dal: annoInizio, al: oggi, evento_id: '' })

// ── Stato ─────────────────────────────────────────────────────────────────
const loadingInit     = ref(true)
const eventi          = ref([])
const kpi             = ref({ confermate: 0, posti_prenotati: 0, tasso_annullamento: 0, ricavi: 0, lista_attesa: 0 })
const andamento       = ref([])
const statiData       = ref([])
const topEventi       = ref([])
const occupazioneData = ref([])
const giorniData      = ref([])
const fasceData       = ref([])
const listaAttesaData = ref([])
const tipologieData   = ref([])

// ── Carica ────────────────────────────────────────────────────────────────
const params = () => ({
  dal:       filtri.dal,
  al:        filtri.al,
  ...(filtri.evento_id ? { evento_id: filtri.evento_id } : {}),
})

const caricaTutto = async () => {
  const p = params()
  const [r1, r2, r3, r4, r5, r6, r7, r8, r9] = await Promise.all([
    statisticheApi.kpi(enteId, p),
    statisticheApi.andamento(enteId, p),
    statisticheApi.stati(enteId, p),
    statisticheApi.topEventi(enteId, p),
    statisticheApi.occupazione(enteId, p),
    statisticheApi.giorniSettimana(enteId, p),
    statisticheApi.fasceOrarie(enteId, p),
    statisticheApi.listaAttesa(enteId, p),
    statisticheApi.tipologiePosto(enteId, p),
  ])
  kpi.value             = r1.data
  andamento.value       = r2.data
  statiData.value       = r3.data
  topEventi.value       = r4.data
  occupazioneData.value = r5.data
  giorniData.value      = r6.data
  fasceData.value       = r7.data
  listaAttesaData.value = r8.data
  tipologieData.value   = r9.data
}

onMounted(async () => {
  const res = await eventiApi.index(enteId, { per_page: 500 })
  eventi.value = (res.data.data ?? res.data).sort((a, b) => a.titolo.localeCompare(b.titolo))
  await caricaTutto()
  loadingInit.value = false
})

// ── Formatters ────────────────────────────────────────────────────────────
const fmt = (v) => Number(v ?? 0).toLocaleString('it-IT')
const fmtCurrency = (v) => Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const barWidthPct  = (v, max) => max > 0 ? Math.round(v / max * 100) : 0
const barHeightPct = (v, max) => max > 0 ? Math.max(4, Math.round(v / max * 100)) : 4

const maxGiorni = computed(() => Math.max(...giorniData.value.map(d => d.n), 1))
const maxFasce  = computed(() => Math.max(...fasceData.value.map(d => d.n), 1))

const occupColor = (pct) => {
  const v = pct ?? 0
  if (v >= 100) return '#e74c3c'
  if (v >= 80)  return '#27ae60'
  if (v >= 50)  return '#f39c12'
  return '#bdc3c7'
}

// ── Donut chart (SVG) ─────────────────────────────────────────────────────
const STATO_COLORS = {
  CONFERMATA:        '#27ae60',
  DA_CONFERMARE:     '#4a90d9',
  ANNULLATA:         '#e74c3c',
  ANNULLATA_UTENTE:  '#e74c3c',
  ANNULLATA_ADMIN:   '#c0392b',
  IN_LISTA_ATTESA:   '#9b59b6',
  NOTIFICATO:        '#8e44ad',
  SCADUTA:           '#95a5a6',
  SCADUTO:           '#95a5a6',
  RISERVATA:         '#3498db',
}
const STATO_DEFAULT_COLOR = '#bdc3c7'

const totaleStati = computed(() => statiData.value.reduce((s, r) => s + Number(r.n), 0))
const circumference = 2 * Math.PI * 46 // r=46

const donutSegments = computed(() => {
  const tot = totaleStati.value
  if (!tot) return []
  let offset = 0
  return statiData.value.map(r => {
    const pct  = r.n / tot
    const dash = pct * circumference
    const gap  = circumference - dash
    const seg  = { label: r.stato, n: r.n, color: STATO_COLORS[r.stato] ?? STATO_DEFAULT_COLOR, dash, gap, offset: -offset }
    offset += dash
    return seg
  })
})

// ── Line chart SVG ────────────────────────────────────────────────────────
const svgW = 600; const svgH = 180; const padL = 36; const padR = 10; const padT = 10; const padB = 20

const maxAndamento = computed(() =>
  Math.max(...andamento.value.flatMap(r => [Number(r.confermate), Number(r.annullate)]), 1)
)

const gridY = computed(() => {
  const m = maxAndamento.value
  const step = m <= 10 ? 2 : m <= 50 ? 10 : m <= 200 ? 50 : Math.ceil(m / 5 / 10) * 10
  const lines = []
  for (let y = 0; y <= m; y += step) lines.push(y)
  return lines
})

const yScale = (v) => svgH - padB - (v / maxAndamento.value) * (svgH - padT - padB)
const xPos   = (i, len) => padL + (i / Math.max(len - 1, 1)) * (svgW - padL - padR)

const linePoints = (data, key) =>
  data.map((r, i) => `${xPos(i, data.length)},${yScale(Number(r[key]))}`).join(' ')
</script>

<style scoped>
.statistiche { padding-bottom: 2rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; }
.filtri-row { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
.filtro-label { display: flex; flex-direction: column; font-size: .8rem; color: #666; gap: .25rem; }
.input { padding: .4rem .7rem; border: 1px solid #ddd; border-radius: 6px; font-size: .88rem; }
.loading-full { padding: 4rem; text-align: center; color: #aaa; }
.empty-chart { padding: 2rem; text-align: center; color: #bbb; font-size: .9rem; }
.card { background: white; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
.card-title { font-size: 1rem; font-weight: 700; margin: 0 0 1rem; color: #2c3e50; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.kpi-card { border-radius: 10px; padding: 1.2rem 1.4rem; color: white; }
.kpi-label { font-size: .78rem; opacity: .88; margin-bottom: .35rem; }
.kpi-value { font-size: 1.75rem; font-weight: 800; }
.kpi-blu        { background: linear-gradient(135deg,#4a90d9,#3a7abf); }
.kpi-verde      { background: linear-gradient(135deg,#27ae60,#1e8449); }
.kpi-arancio    { background: linear-gradient(135deg,#f39c12,#d68910); }
.kpi-verde-scuro{ background: linear-gradient(135deg,#16a085,#117a65); }
.kpi-viola      { background: linear-gradient(135deg,#9b59b6,#7d3c98); }

/* layout righe grafici */
.grafici-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
.grafico-card { margin-bottom: 0; }
.grafico-large { grid-column: span 2; }

/* Line chart SVG */
.chart-wrap { position: relative; }
.chart-svg { width: 100%; height: 160px; }
.chart-label { font-size: 9px; fill: #aaa; }
.chart-legend { display: flex; gap: 1rem; margin-top: .5rem; font-size: .8rem; color: #555; }
.legend-item { display: flex; align-items: center; gap: .35rem; }

/* Donut */
.donut-wrap { display: flex; align-items: center; gap: 1.25rem; }
.donut-svg { width: 120px; height: 120px; flex-shrink: 0; }
.donut-center { font-size: 18px; font-weight: 800; fill: #2c3e50; }
.donut-legend { list-style: none; padding: 0; margin: 0; font-size: .8rem; flex: 1; }
.donut-legend li { display: flex; align-items: center; gap: .4rem; margin-bottom: .3rem; }
.donut-stato { flex: 1; color: #555; font-size: .75rem; }
.donut-n { font-weight: 700; color: #2c3e50; }

/* Bar chart orizzontale */
.bar-chart-h { display: flex; flex-direction: column; gap: .55rem; }
.bar-h-row { display: grid; grid-template-columns: 200px 1fr 80px; align-items: center; gap: .6rem; }
.bar-h-label { font-size: .82rem; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bar-h-track { background: #f0f0f0; border-radius: 4px; height: 14px; overflow: hidden; }
.bar-h-fill  { height: 100%; border-radius: 4px; transition: width .4s; min-width: 4px; }
.bar-h-val   { font-size: .8rem; color: #333; text-align: right; }

/* Bar chart verticale */
.bar-chart-v { display: flex; align-items: flex-end; gap: .4rem; height: 120px; }
.bar-chart-v--ore { gap: .15rem; }
.bar-v-col { display: flex; flex-direction: column; align-items: center; flex: 1; }
.bar-v-track { flex: 1; width: 100%; display: flex; align-items: flex-end; }
.bar-v-fill { width: 100%; border-radius: 3px 3px 0 0; transition: height .4s; min-height: 2px; }
.bar-v-label { font-size: .7rem; color: #888; margin-top: .2rem; }
.bar-v-val { font-size: .65rem; color: #aaa; }

/* Tipologie */
.tipologie-list { list-style: none; padding: 0; margin: 0; }
.tipologia-row { display: flex; gap: .75rem; align-items: center; padding: .4rem 0; border-bottom: 1px solid #f4f4f4; font-size: .85rem; }
.tipologia-nome { flex: 1; font-weight: 600; }
.tipologia-q { color: #666; }
.tipologia-ricavo { color: #27ae60; font-weight: 600; }

/* Dot */
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.muted { color: #999; font-size: .78rem; }

/* Tabella lista attesa */
.table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.table th { text-align: left; padding: .5rem .75rem; border-bottom: 2px solid #eee; color: #888; font-weight: 600; font-size: .78rem; text-transform: uppercase; }
.table td { padding: .5rem .75rem; border-bottom: 1px solid #f4f4f4; }
.badge { padding: .2rem .5rem; border-radius: 8px; font-size: .72rem; font-weight: 700; }
.badge-verde   { background: #d5f5e3; color: #1a7a45; }
.badge-arancio { background: #fef9e7; color: #7d6608; }

@media (max-width: 900px) {
  .grafici-row { grid-template-columns: 1fr; }
  .grafico-large { grid-column: span 1; }
  .bar-h-row { grid-template-columns: 130px 1fr 60px; }
}
</style>
