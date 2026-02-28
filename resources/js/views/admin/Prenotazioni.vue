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
            <td class="mono">{{ p.codice }}</td>
            <td>
              {{ p.cognome }} {{ p.nome }}
              <div class="muted">{{ p.email }}</div>
            </td>
            <td>
              {{ p.sessione?.evento?.titolo ?? '–' }}
              <div class="muted">{{ formatDateTime(p.sessione?.inizio_at) }}</div>
            </td>
            <td>{{ totPosti(p) }}</td>
            <td>€ {{ Number(p.importo_totale).toFixed(2) }}</td>
            <td>
              <span :class="['badge', `badge-${statoClass(p.stato)}`]">{{ p.stato }}</span>
            </td>
            <td class="actions">
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
}

const annulla = async (p) => {
  if (!confirm(`Annullare la prenotazione ${p.codice}?`)) return
  await prenotazioniApi.annullaAdmin(enteId, p.id)
  p.stato = 'ANNULLATA_ADMIN'
}

const totPosti = (p) => p.prenotazione_posti?.reduce((s, x) => s + x.quantita, 0) ?? '–'
const formatDateTime = (d) => d ? new Date(d).toLocaleString('it-IT', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '–'
const statoClass = (s) => s?.toLowerCase().replace('_', '-') ?? ''

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
</style>
