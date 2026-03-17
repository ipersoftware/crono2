<template>
  <div>
    <div class="page-header">
      <h1>✉ Template email</h1>
    </div>

    <div class="layout">
      <!-- Lista tipi -->
      <div class="sidebar card">
        <div
          v-for="tipo in tipiDisponibili"
          :key="tipo"
          :class="['tipo-item', { active: tipoAttivo === tipo }]"
          @click="seleziona(tipo)"
        >
          <div class="tipo-nome">{{ tipoLabel(tipo) }}</div>
          <span v-if="templatePersonalizzati.has(tipo)" class="badge-custom">personalizzato</span>
        </div>
      </div>

      <!-- Editor -->
      <div class="editor-area">
        <div v-if="!tipoAttivo" class="empty">Seleziona un tipo di template dalla lista.</div>
        <div v-else class="card">
          <div class="editor-header">
            <h2>{{ tipoLabel(tipoAttivo) }}</h2>
            <div class="editor-actions">
              <button
                v-if="templatePersonalizzati.has(tipoAttivo)"
                @click="ripristina"
                class="btn btn-secondary btn-sm"
              >Ripristina predefinito</button>
              <button @click="salva" :disabled="saving" class="btn btn-primary btn-sm">
                {{ saving ? 'Salvataggio…' : 'Salva' }}
              </button>
            </div>
          </div>

          <div class="form-group">
            <label>Oggetto email *</label>
            <input v-model="form.oggetto" class="input" required />
          </div>

          <div class="form-group">
            <label>Corpo HTML</label>
            <details class="placeholder-box">
              <summary>Variabili disponibili <span class="ph-hint">(clicca per espandere — clicca su una variabile per copiarla)</span></summary>
              <div class="ph-groups">
                <div v-for="gruppo in gruppiPlaceholder" :key="gruppo.titolo" class="ph-group">
                  <div class="ph-group-title">{{ gruppo.titolo }}</div>
                  <template v-for="voce in gruppo.voci" :key="voce.k">
                    <code @click="copiaPh(voce.k)">{{ voce.k }}</code>
                    <span v-if="voce.desc" class="ph-desc">{{ voce.desc }}</span>
                  </template>
                </div>
              </div>
              <div v-if="copiato" class="ph-copiato">✓ Copiato!</div>
            </details>
            <Editor
              v-model="form.corpo"
              api-key="jzd2a0zvkf6gmknn3yaxfk66pb6c1zzjnap5x6uzxo717ufn"
              :init="tinyInit"
            />
          </div>

          <div v-if="errore" class="alert-error">{{ errore }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { mailTemplatesApi } from '@/api/admin'
import Editor from '@tinymce/tinymce-vue'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const tinyInit = {
  height: 480,
  menubar: false,
  plugins: [
    'advlist', 'autolink', 'lists', 'link', 'charmap',
    'searchreplace', 'visualblocks', 'code', 'fullscreen',
    'table', 'wordcount',
  ],
  toolbar:
    'undo redo | blocks | bold italic underline | forecolor backcolor | ' +
    'alignleft aligncenter alignright alignjustify | ' +
    'bullist numlist outdent indent | link | code | removeformat',
  content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
  entity_encoding: 'raw',
  verify_html: false,
  valid_elements: '*[*]',
  extended_valid_elements: '*[*]',
  valid_children: '+body[style]',
}

const tipiDisponibili = [
  'PRENOTAZIONE_CONFERMATA',
  'PRENOTAZIONE_DA_CONFERMARE',
  'PRENOTAZIONE_APPROVATA',
  'PRENOTAZIONE_ANNULLATA_UTENTE',
  'PRENOTAZIONE_ANNULLATA_OPERATORE',
  'PRENOTAZIONE_NOTIFICA_STAFF',
  'EVENTO_ANNULLATO',
  'LISTA_ATTESA_ISCRIZIONE',
  'LISTA_ATTESA_POSTO_DISPONIBILE',
  'LISTA_ATTESA_SCADENZA',
  'REMINDER_EVENTO',
  'REGISTRAZIONE_CONFERMATA',
  'RESET_PASSWORD',
  'BENVENUTO_OPERATORE',
]

const templates            = ref([])
const templatePersonalizzati = ref(new Set())
const tipoAttivo           = ref(null)
const saving               = ref(false)
const errore               = ref('')
const form = reactive({ oggetto: '', corpo: '' })

const carica = async () => {
  const res = await mailTemplatesApi.index(enteId)
  templates.value = res.data
  templatePersonalizzati.value = new Set(templates.value.map(t => t.tipo))
}

const seleziona = async (tipo) => {
  tipoAttivo.value = tipo
  errore.value     = ''
  try {
    const res = await mailTemplatesApi.show(enteId, tipo)
    Object.assign(form, { oggetto: res.data.oggetto, corpo: res.data.corpo })
  } catch {
    Object.assign(form, { oggetto: '', corpo: '' })
  }
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    await mailTemplatesApi.store(enteId, { tipo: tipoAttivo.value, ...form })
    templatePersonalizzati.value.add(tipoAttivo.value)
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore.'
  } finally { saving.value = false }
}

const ripristina = async () => {
  if (!confirm('Eliminare il template personalizzato e tornare al predefinito?')) return
  const t = templates.value.find(x => x.tipo === tipoAttivo.value)
  if (t) {
    await mailTemplatesApi.destroy(enteId, t.id)
    templatePersonalizzati.value.delete(tipoAttivo.value)
    await seleziona(tipoAttivo.value)
  }
}

const tipoLabel = (tipo) => ({
  PRENOTAZIONE_CONFERMATA:       '✅ Conferma prenotazione',
  PRENOTAZIONE_DA_CONFERMARE:    '📩 Prenotazione da confermare',
  PRENOTAZIONE_APPROVATA:        '👍 Prenotazione approvata',
  PRENOTAZIONE_ANNULLATA_UTENTE: '❌ Annullamento (utente)',
  PRENOTAZIONE_ANNULLATA_OPERATORE: '❌ Annullamento (operatore)',
  PRENOTAZIONE_NOTIFICA_STAFF:   '👤 Notifica staff',
  EVENTO_ANNULLATO:              '🚫 Evento annullato',
  LISTA_ATTESA_ISCRIZIONE:       '🔔 Lista attesa — iscrizione',
  LISTA_ATTESA_POSTO_DISPONIBILE:'✔ Lista attesa — posto disponibile',
  LISTA_ATTESA_SCADENZA:         '⏰ Lista attesa — scadenza',
  REMINDER_EVENTO:               '⏰ Promemoria evento',
  REGISTRAZIONE_CONFERMATA:      '👋 Registrazione confermata',
  RESET_PASSWORD:                '🔑 Reset password',
  BENVENUTO_OPERATORE:           '🔐 Benvenuto operatore',
}[tipo] ?? tipo)

const gruppiPlaceholder = [
  { titolo: '👤 Utente', voci: [
    { k: '{{nome_utente}}' },
    { k: '{{cognome_utente}}' },
    { k: '{{email_utente}}' },
  ]},
  { titolo: '📅 Evento & Sessione', voci: [
    { k: '{{titolo_evento}}' },
    { k: '{{data_sessione}}' },
    { k: '{{ora_inizio}}' },
    { k: '{{ora_fine}}' },
    { k: '{{luogo_evento}}' },
    { k: '{{indirizzo_luogo}}' },
    { k: '{{descrizione_sessione}}' },
  ]},
  { titolo: '🎫 Prenotazione', voci: [
    { k: '{{codice_prenotazione}}' },
    { k: '{{posti_prenotati}}' },
    { k: '{{dettaglio_posti}}' },
    { k: '{{costo_totale}}' },
    { k: '{{note_prenotazione}}' },
    { k: '{{motivo_annullamento}}' },
  ]},
  { titolo: '🔗 Link', voci: [
    { k: '{{link_prenotazione}}' },
    { k: '{{link_annullamento}}' },
    { k: '{{link_vetrina}}' },
  ]},
  { titolo: '❌ Cancellazione', voci: [
    { k: '{{info_cancellazione}}', desc: 'Frase automatica sulla policy (sempre / mai / N ore prima)' },
  ]},
  { titolo: '🏢 Ente', voci: [
    { k: '{{nome_ente}}' },
    { k: '{{email_ente}}' },
    { k: '{{telefono_ente}}' },
  ]},
]

const copiato = ref(false)
const copiaPh = (testo) => {
  navigator.clipboard.writeText(testo).catch(() => {})
  copiato.value = true
  setTimeout(() => { copiato.value = false }, 1500)
}

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .75rem; }
.layout { display: grid; grid-template-columns: 240px 1fr; gap: 1rem; align-items: start; }
.sidebar { padding: .5rem; }
.tipo-item { padding: .6rem .85rem; border-radius: 6px; cursor: pointer; margin-bottom: .2rem; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
.tipo-item:hover { background: #f0f4f8; }
.tipo-item.active { background: #ebf5fb; color: #1a5276; font-weight: 600; }
.tipo-nome { font-size: .88rem; }
.badge-custom { background: #d6eaf8; color: #1a5276; border-radius: 8px; padding: .1rem .45rem; font-size: .7rem; white-space: nowrap; flex-shrink: 0; }
.empty { padding: 3rem; text-align: center; color: #aaa; }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: .6rem; }
.editor-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.form-group { margin-bottom: .9rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }

.helper { font-size: .8rem; color: #888; margin-bottom: .35rem; }
.placeholder-box { border: 1px solid #dde3ea; border-radius: 8px; padding: .5rem .85rem; margin-bottom: .5rem; background: #f8fafc; font-size: .82rem; }
.placeholder-box summary { cursor: pointer; font-weight: 600; color: #2c3e50; user-select: none; padding: .25rem 0; }
.ph-hint { font-weight: 400; color: #999; font-size: .78rem; margin-left: .35rem; }
.ph-groups { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; margin-top: .65rem; }
.ph-group { min-width: 160px; }
.ph-group-title { font-size: .75rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .3rem; }
.ph-group code { display: inline-block; background: #e8f0fe; color: #1a5276; border-radius: 4px; padding: .1rem .4rem; margin: .15rem .15rem .15rem 0; font-size: .78rem; cursor: pointer; transition: background .15s; }
.ph-group code:hover { background: #bcd4f7; }
.ph-desc { display: block; font-size: .76rem; color: #777; margin-top: .2rem; line-height: 1.4; max-width: 240px; }
.ph-copiato { margin-top: .4rem; font-size: .78rem; color: #27ae60; font-weight: 600; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }

@media (max-width: 768px) {
  .layout { grid-template-columns: 1fr; }
  .sidebar { padding: .25rem; }
  .tipo-item { padding: .55rem .75rem; }
  .editor-header { flex-direction: column; align-items: flex-start; }
  .editor-actions { width: 100%; }
  .editor-actions .btn { flex: 1; text-align: center; }
}
</style>
