<template>
  <div>
    <div class="page-header">
      <h1>🎟 Prenotazioni</h1>
      <div class="filtri-row">
        <input v-model="filtri.cerca" @input="carica" placeholder="Cerca codice, nome, email…" class="input" />
        <select v-model="filtri.stato" @change="carica" class="input">
          <option value="">Tutti gli stati</option>
          <option value="CONFERMATA">Confermata</option>
          <option value="DA_CONFERMARE">Da confermare</option>
          <option value="RISERVATA">Riservata</option>
          <option value="ANNULLATA_UTENTE">Annullata utente</option>
          <option value="ANNULLATA_ADMIN">Annullata admin</option>
        </select>
      </div>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="prenotazioni.length === 0" class="empty">Nessuna prenotazione trovata.</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>Codice</th>
            <th>Nominativo</th>
            <th>Evento / Sessione</th>
            <th>Posti</th>
            <th>Importo</th>
            <th>Stato</th>
            <th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in prenotazioni" :key="p.id">
            <td data-label="Codice" class="mono">{{ p.codice }}</td>
            <td data-label="Nominativo">
              {{ p.cognome }} {{ p.nome }}
              <div class="muted">{{ p.email }}</div>
            </td>
            <td data-label="Evento / Sessione">
              {{ p.sessione?.evento?.titolo ?? '–' }}
              <div class="muted">{{ formatDateTime(p.sessione?.data_inizio) }}</div>
            </td>
            <td data-label="Posti">{{ totPosti(p) }}</td>
            <td data-label="Importo">€ {{ Number(p.costo_totale ?? 0).toFixed(2) }}</td>
            <td data-label="Stato">
              <span :class="['badge', `badge-${statoClass(p.stato)}`]">{{ p.stato }}</span>
            </td>
            <td data-label="Azioni" class="actions">
              <button @click="apriDettaglio(p)" class="btn btn-sm btn-secondary">Dettaglio</button>
              <button
                v-if="p.stato === 'DA_CONFERMARE'"
                @click="approva(p)"
                class="btn btn-sm btn-success"
              >Approva</button>
              <button
                v-if="!['ANNULLATA_UTENTE','ANNULLATA_ADMIN','SCADUTA'].includes(p.stato)"
                @click="annulla(p)"
                class="btn btn-sm btn-danger"
              >Annulla</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginazione -->
      <div v-if="meta?.last_page > 1" class="paginazione">
        <button
          v-for="n in meta.last_page"
          :key="n"
          :class="['btn btn-sm', n === meta.current_page ? 'btn-primary' : 'btn-secondary']"
          @click="pagina = n; carica()"
        >{{ n }}</button>
      </div>
    </div>
  </div>

  <!-- Modal conferma annullamento -->
  <div v-if="annullamentoTarget" class="modal-overlay" @click.self="annullamentoTarget = null">
    <div class="modal" style="max-width:460px">
      <div class="modal-header">
        <h3>Annulla prenotazione</h3>
        <button @click="annullamentoTarget = null" class="modal-close">✕</button>
      </div>
      <div class="modal-body">
        <p style="margin:0 0 1rem">
          Stai annullando la prenotazione <strong class="mono">{{ annullamentoTarget.codice }}</strong>
          di <strong>{{ annullamentoTarget.cognome }} {{ annullamentoTarget.nome }}</strong>.
        </p>
        <label style="display:block;font-weight:600;font-size:.88rem;margin-bottom:.4rem">
          Motivo annullamento <span style="color:#aaa;font-weight:400">(facoltativo)</span>
        </label>
        <textarea
          v-model="annullamentoMotivo"
          rows="3"
          placeholder="Es. evento annullato per lavori, posti esauriti, richiesta organizzatore…"
          class="input"
          style="width:100%;resize:vertical"
        ></textarea>
        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.25rem">
          <button @click="annullamentoTarget = null" class="btn btn-sm btn-secondary">Annulla</button>
          <button @click="confermAnnullamento" class="btn btn-sm btn-danger" :disabled="annullamentoLoading">
            {{ annullamentoLoading ? 'Annullamento…' : 'Conferma annullamento' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal dettaglio prenotazione -->
  <div v-if="dettaglio" class="modal-overlay" @click.self="dettaglio = null">
    <div class="modal">
      <div class="modal-header">
        <h3>Prenotazione <span class="mono">{{ dettaglio.codice }}</span></h3>
        <button @click="dettaglio = null" class="modal-close">✕</button>
      </div>
      <div class="modal-body">
        <div class="detail-grid">
          <div class="detail-section">
            <h4>Prenotante</h4>
            <dl>
              <dt>Nominativo</dt><dd>{{ dettaglio.cognome }} {{ dettaglio.nome }}</dd>
              <dt>Email</dt><dd>{{ dettaglio.email }}</dd>
              <dt>Telefono</dt><dd>{{ dettaglio.telefono || '–' }}</dd>
              <dt>Note</dt><dd>{{ dettaglio.note || '–' }}</dd>
            </dl>
          </div>
          <div class="detail-section">
            <h4>Evento / Sessione</h4>
            <dl>
              <dt>Evento</dt><dd>{{ dettaglio.sessione?.evento?.titolo ?? '–' }}</dd>
              <dt>Data</dt><dd>{{ formatDateTime(dettaglio.sessione?.data_inizio) }}</dd>
              <dt>Stato</dt>
              <dd><span :class="['badge', `badge-${statoClass(dettaglio.stato)}`]">{{ dettaglio.stato }}</span></dd>
              <dt>Data prenotazione</dt><dd>{{ formatDateTime(dettaglio.data_prenotazione) }}</dd>
              <template v-if="dettaglio.data_annullamento">
                <dt>Data annullamento</dt><dd>{{ formatDateTime(dettaglio.data_annullamento) }}</dd>
              </template>
              <template v-if="dettaglio.motivo_annullamento">
                <dt>Motivo</dt><dd>{{ dettaglio.motivo_annullamento }}</dd>
              </template>
            </dl>
          </div>
        </div>

        <!-- Tipologie posti -->
        <div class="detail-section" style="margin-top:1rem">
          <h4>Posti prenotati</h4>
          <table class="table-inner">
            <thead>
              <tr><th>Tipologia</th><th>Quantità</th><th>Prezzo unit.</th><th>Subtotale</th></tr>
            </thead>
            <tbody>
              <tr v-for="posto in dettaglio.posti" :key="posto.id">
                <td>{{ posto.tipologia_posto?.nome ?? '–' }}</td>
                <td>{{ posto.quantita }}</td>
                <td>€ {{ Number(posto.costo_unitario ?? 0).toFixed(2) }}</td>
                <td>€ {{ Number(posto.costo_totale ?? 0).toFixed(2) }}</td>
              </tr>
              <tr v-if="!dettaglio.posti?.length">
                <td colspan="4" style="text-align:center;color:#aaa">Nessun posto registrato</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" style="text-align:right;font-weight:600">Totale</td>
                <td style="font-weight:600">€ {{ Number(dettaglio.costo_totale ?? 0).toFixed(2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { prenotazioniApi } from '@/api/prenotazioni'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const prenotazioni = ref([])
const meta    = ref(null)
const loading = ref(false)
const pagina  = ref(1)
const filtri  = reactive({ cerca: '', stato: '' })
const dettaglio = ref(null)
const annullamentoTarget  = ref(null)
const annullamentoMotivo  = ref('')
const annullamentoLoading = ref(false)

const carica = async () => {
  loading.value = true
  try {
    const res = await prenotazioniApi.indexAdmin(enteId, {
      ...filtri,
      page: pagina.value,
    })
    prenotazioni.value = res.data.data
    meta.value = res.data
  } finally {
    loading.value = false
  }
}

const approva = async (p) => {
  await prenotazioniApi.approva(enteId, p.id)
  p.stato = 'CONFERMATA'
  if (dettaglio.value?.id === p.id) dettaglio.value.stato = 'CONFERMATA'
}

const annulla = (p) => {
  annullamentoTarget.value  = p
  annullamentoMotivo.value  = ''
  annullamentoLoading.value = false
}

const confermAnnullamento = async () => {
  const p = annullamentoTarget.value
  if (!p) return
  annullamentoLoading.value = true
  try {
    await prenotazioniApi.annullaAdmin(enteId, p.id, annullamentoMotivo.value)
    p.stato = 'ANNULLATA_ADMIN'
    p.motivo_annullamento = annullamentoMotivo.value
    if (dettaglio.value?.id === p.id) {
      dettaglio.value.stato = 'ANNULLATA_ADMIN'
      dettaglio.value.motivo_annullamento = annullamentoMotivo.value
    }
    annullamentoTarget.value = null
  } finally {
    annullamentoLoading.value = false
  }
}

const apriDettaglio = (p) => { dettaglio.value = p }

const totPosti = (p) => p.posti?.reduce((s, x) => s + x.quantita, 0) ?? p.posti_prenotati ?? '–'
const formatDateTime = (d) => d ? new Date(d).toLocaleString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '–'
const statoClass = (s) => s?.toLowerCase().replaceAll('_', '-') ?? ''

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; }
.filtri-row { display: flex; gap: .75rem; flex-wrap: wrap; }
.input { padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.muted { font-size: .78rem; color: #999; }
.mono { font-family: monospace; font-size: .85rem; }
.actions { display: flex; gap: .4rem; }
.badge { padding: .22rem .55rem; border-radius: 10px; font-size: .72rem; font-weight: 600; text-transform: uppercase; }
.badge-confermata       { background: #d5f5e3; color: #1a7a45; }
.badge-da-confermare    { background: #fef9e7; color: #7d6608; }
.badge-riservata        { background: #d6eaf8; color: #1a5276; }
.badge-annullata-utente { background: #fadbd8; color: #a93226; }
.badge-annullata-admin  { background: #fadbd8; color: #a93226; }
.badge-scaduta          { background: #eee; color: #555; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 4px; cursor: pointer; }
.btn-success { background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; }
.paginazione { display: flex; gap: .4rem; justify-content: center; margin-top: 1.25rem; flex-wrap: wrap; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal { background: white; border-radius: 10px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #eee; }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #888; }
.modal-body { padding: 1.5rem; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.detail-section h4 { margin: 0 0 .6rem; font-size: .85rem; text-transform: uppercase; letter-spacing: .05em; color: #888; }
dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: .3rem .75rem; font-size: .88rem; }
dt { font-weight: 600; color: #555; white-space: nowrap; }
dd { margin: 0; color: #222; }
.table-inner { width: 100%; border-collapse: collapse; font-size: .88rem; margin-top: .5rem; }
.table-inner th, .table-inner td { padding: .45rem .6rem; border-bottom: 1px solid #eee; text-align: left; }
.table-inner th { background: #f8f9fa; font-weight: 600; font-size: .8rem; color: #555; }
.table-inner tfoot td { background: #f8f9fa; border-top: 2px solid #ddd; }
@media (max-width: 540px) { .detail-grid { grid-template-columns: 1fr; } }

@media (max-width: 640px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
  .table tr { border: 1px solid #e8eaed; border-radius: 8px; margin-bottom: .75rem; padding: .5rem .75rem; background: white; }
  .table td { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; padding: .4rem 0; border-bottom: 1px solid #f0f0f0; font-size: .88rem; }
  .table td:last-child { border-bottom: none; }
  .table td::before { content: attr(data-label); font-weight: 600; color: #777; font-size: .75rem; white-space: nowrap; padding-top: .1rem; }
  .actions { justify-content: flex-end; }
}
</style>
