<template>
  <div class="dettaglio-page">
    <div v-if="loading" class="loading">Caricamento…</div>
    <div v-else-if="errore" class="alert-error">{{ errore }}</div>
    <template v-else-if="prenotazione">
      <div class="page-header">
        <div>
          <div class="breadcrumb">
            <router-link to="/prenotazioni/mie" class="back-link">← Le mie prenotazioni</router-link>
            <template v-if="prenotazione.sessione?.evento?.ente && enteIdentifier(prenotazione.sessione.evento.ente)">
              <span class="breadcrumb-sep">/</span>
              <router-link
                :to="`/vetrina/${enteIdentifier(prenotazione.sessione.evento.ente)}`"
                class="back-link"
              >{{ prenotazione.sessione.evento.ente.nome }}</router-link>
              <span class="breadcrumb-sep">/</span>
              <router-link
                :to="`/vetrina/${enteIdentifier(prenotazione.sessione.evento.ente)}/eventi/${prenotazione.sessione.evento.slug}`"
                class="back-link"
              >{{ prenotazione.sessione.evento.titolo }}</router-link>
            </template>
          </div>
          <h1>Prenotazione <span class="codice">{{ prenotazione.codice }}</span></h1>
        </div>
        <span :class="['badge', `badge-${statoClass(prenotazione.stato)}`]">{{ prenotazione.stato }}</span>
      </div>

      <div class="grid-2">
        <div class="card">
          <h2>🗓 Evento</h2>
          <p><strong>{{ prenotazione.sessione?.evento?.titolo }}</strong></p>
          <p class="muted">{{ formatDateTime(prenotazione.sessione?.data_inizio) }}</p>
          <template v-if="prenotazione.sessione?.luoghi?.length">
            <p v-for="l in prenotazione.sessione.luoghi" :key="l.id" class="muted">
              📍
              <a v-if="l.maps_url" :href="l.maps_url" target="_blank" rel="noopener" class="luogo-link">{{ l.nome }}</a>
              <span v-else>{{ l.nome }}</span>
              <span v-if="l.indirizzo"> — {{ l.indirizzo }}</span>
            </p>
          </template>
        </div>

        <div class="card">
          <h2>👤 Titolare</h2>
          <p>{{ prenotazione.cognome }} {{ prenotazione.nome }}</p>
          <p class="muted">{{ prenotazione.email }}</p>
          <p v-if="prenotazione.telefono" class="muted">{{ prenotazione.telefono }}</p>
        </div>
      </div>

      <!-- Posti -->
      <div class="card" v-if="prenotazione.posti?.length">
        <h2>🪑 Posti prenotati</h2>
        <table class="table">
          <thead><tr><th>Tipologia</th><th>Quantità</th><th>Costo unitario</th><th>Totale</th></tr></thead>
          <tbody>
            <tr v-for="p in prenotazione.posti" :key="p.id">
              <td>{{ p.tipologia_posto?.nome ?? '–' }}</td>
              <td>{{ p.quantita }}</td>
              <td>€ {{ Number(p.costo_unitario).toFixed(2) }}</td>
              <td>€ {{ Number(p.costo_riga).toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3"><strong>Totale</strong></td>
              <td><strong>€ {{ Number(prenotazione.costo_totale).toFixed(2) }}</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Risposte form -->
      <div class="card" v-if="prenotazione.risposte_form?.length">
        <h2>📋 Dati aggiuntivi</h2>
        <dl class="risposte">
          <template v-for="r in prenotazione.risposte_form" :key="r.id">
            <dt>{{ r.campo_form?.etichetta }}</dt>
            <dd>{{ r.risposta }}</dd>
          </template>
        </dl>
      </div>

      <!-- Note -->
      <div class="card" v-if="prenotazione.note">
        <h2>📝 Note</h2>
        <p>{{ prenotazione.note }}</p>
      </div>

      <!-- Azioni -->
      <div class="card" v-if="!['ANNULLATA_UTENTE','ANNULLATA_ADMIN','SCADUTA'].includes(prenotazione.stato)">
        <button @click="apriModaleAnnulla" :disabled="annullando" class="btn btn-danger">
          ❌ Annulla prenotazione
        </button>
        <p class="muted" style="margin-top: .5rem; font-size: .85rem;">
          L'annullamento è irreversibile.
        </p>
      </div>
    </template>

    <!-- Modale annullamento -->
    <teleport to="body">
      <div v-if="mostraModaleAnnulla" class="modal-overlay" @click.self="mostraModaleAnnulla = false">
      <div class="modal-box">
        <h3>Annulla prenotazione</h3>
        <p class="modal-avviso">Stai per annullare la prenotazione <strong>{{ prenotazione?.codice }}</strong>. L'operazione è irreversibile.</p>
        <label class="modal-label" for="motivo-annulla">Motivo annullamento (opzionale)</label>
        <div class="motivi-predefiniti">
          <button
            v-for="m in motiviPredefiniti" :key="m"
            type="button"
            :class="['motivo-chip', { active: motivoAnnullamento === m }]"
            @click="motivoAnnullamento = motivoAnnullamento === m ? '' : m"
            :disabled="annullando"
          >{{ m }}</button>
        </div>
        <textarea
          id="motivo-annulla"
          v-model="motivoAnnullamento"
          class="modal-textarea"
          rows="3"
          placeholder="Inserisci un motivo…"
          :disabled="annullando"
        />
        <div class="modal-azioni">
          <p v-if="erroreModale" class="modal-errore">{{ erroreModale }}</p>
          <div class="modal-azioni-buttons">
            <button class="btn btn-secondary" @click="mostraModaleAnnulla = false" :disabled="annullando">Torna indietro</button>
            <button class="btn btn-danger" @click="confermAnnullamento" :disabled="annullando">
              {{ annullando ? 'Annullamento…' : 'Conferma annullamento' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
  </div>
</template>

<script setup>
import { prenotazioniApi } from '@/api/prenotazioni'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const codice = route.params.codice
const tokenGuest = route.query.token ?? null

const prenotazione = ref(null)
const loading   = ref(true)
const annullando = ref(false)
const errore    = ref('')
const mostraModaleAnnulla = ref(false)
const motivoAnnullamento  = ref('')
const erroreModale        = ref('')
const motiviPredefiniti = [
  'Impegno sopravvenuto',
  'Problemi di salute',
  'Problemi di trasporto',
  'Evento non più di interesse',
  'Errore nella prenotazione',
]

const carica = async () => {
  loading.value = true
  errore.value  = ''
  try {
    const res = await prenotazioniApi.show(codice, tokenGuest)
    prenotazione.value = res.data
  } catch (e) {
    errore.value = e.response?.status === 403
      ? 'Accesso non autorizzato a questa prenotazione.'
      : 'Prenotazione non trovata.'
  } finally { loading.value = false }
}

const apriModaleAnnulla = () => {
  motivoAnnullamento.value = ''
  erroreModale.value = ''
  mostraModaleAnnulla.value = true
}

const confermAnnullamento = async () => {
  annullando.value = true
  erroreModale.value = ''
  try {
    await prenotazioniApi.annullaUtente(codice, tokenGuest, motivoAnnullamento.value)
    prenotazione.value.stato = 'ANNULLATA_UTENTE'
    mostraModaleAnnulla.value = false
  } catch (e) {
    erroreModale.value = e.response?.data?.message ?? 'Impossibile annullare la prenotazione.'
  } finally { annullando.value = false }
}

const formatDateTime = (d) => d ? new Date(d).toLocaleString('it-IT', { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–'
const enteIdentifier = (ente) => ente?.shop_url || ente?.slug || null
const statoClass = (s) => s?.toLowerCase().replace(/_/g, '-') ?? ''

onMounted(carica)
</script>

<style scoped>
.dettaglio-page { max-width: 820px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.back-link { color: #3498db; text-decoration: none; font-size: .85rem; }
.back-link:hover { text-decoration: underline; }
.breadcrumb { display: flex; align-items: center; flex-wrap: wrap; gap: .2rem; margin-bottom: .35rem; }
.breadcrumb-sep { color: #bbb; font-size: .85rem; }
h1 { margin: 0; }
.codice { font-family: monospace; font-size: 1rem; color: #1a5276; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.card { margin-bottom: 1rem; }
.card h2 { margin-bottom: .75rem; font-size: 1rem; }
.muted { color: #888; font-size: .88rem; margin: .2rem 0; }
.luogo-link { color: #2980b9; text-decoration: underline; }
.luogo-link:hover { color: #1a5276; }
.badge { padding: .3rem .75rem; border-radius: 12px; font-size: .82rem; font-weight: 600; text-transform: uppercase; }
.badge-confermata       { background: #d5f5e3; color: #1a7a45; }
.badge-da-confermare    { background: #fef9e7; color: #7d6608; }
.badge-riservata        { background: #d6eaf8; color: #1a5276; }
.badge-annullata-utente,
.badge-annullata-admin  { background: #fadbd8; color: #a93226; }
.badge-scaduta          { background: #eee; color: #555; }
.risposte { display: grid; grid-template-columns: auto 1fr; gap: .3rem 1.5rem; align-items: baseline; }
.risposte dt { font-weight: 600; font-size: .9rem; }
.risposte dd { margin: 0; color: #444; }
.loading { padding: 3rem; text-align: center; color: #aaa; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
tfoot td { padding: .75rem; font-size: 1rem; }
@media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }

/* Modale annullamento */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
}
.modal-box {
  background: #fff;
  border-radius: 10px;
  padding: 1.75rem 2rem;
  width: min(480px, 92vw);
  box-shadow: 0 8px 32px rgba(0,0,0,.18);
}
.modal-box h3 { margin: 0 0 .75rem; font-size: 1.15rem; }
.modal-avviso { margin: 0 0 1.25rem; font-size: .95rem; color: #444; }
.modal-label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: .4rem; }
.motivi-predefiniti { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .65rem; }
.motivo-chip {
  background: #f0f4f8; border: 1px solid #c5d3e0;
  border-radius: 20px; padding: .3rem .85rem;
  font-size: .82rem; cursor: pointer; transition: background .15s, border-color .15s;
  font-family: inherit;
}
.motivo-chip:hover:not(:disabled) { background: #dce8f5; border-color: #3498db; }
.motivo-chip.active { background: #d6eaf8; border-color: #2980b9; color: #1a5276; font-weight: 600; }
.motivo-chip:disabled { opacity: .5; cursor: default; }
.modal-textarea {
  width: 100%; box-sizing: border-box;
  padding: .55rem .75rem;
  border: 1px solid #ccc; border-radius: 6px;
  font-size: .95rem; resize: vertical;
  font-family: inherit;
}
.modal-textarea:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,.15); }
.modal-azioni { margin-top: 1.25rem; }
.modal-errore { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .6rem .9rem; font-size: .9rem; margin: 0 0 .75rem; }
.modal-azioni-buttons { display: flex; justify-content: flex-end; gap: .75rem; }
</style>
