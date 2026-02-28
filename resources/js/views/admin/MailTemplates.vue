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
            <div class="helper">Usa <code v-pre>{{nome}}</code>, <code v-pre>{{codice}}</code>, <code v-pre>{{evento}}</code>, <code v-pre>{{link}}</code>, ecc.</div>
            <textarea v-model="form.corpo_html" rows="16" class="input mono-area"></textarea>
          </div>

          <div v-if="errore" class="alert-error">{{ errore }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { mailTemplatesApi } from '@/api/admin'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const tipiDisponibili = [
  'CONFERMA_PRENOTAZIONE','ANNULLAMENTO_PRENOTAZIONE','PROMEMORIA',
  'LISTA_ATTESA_NOTIFICA','LISTA_ATTESA_CONFERMA','RICHIESTA_APPROVAZIONE',
  'APPROVAZIONE_ADMIN','RIFIUTO_ADMIN','MODIFICA_EVENTO','ANNULLAMENTO_EVENTO',
  'RESET_PASSWORD','BENVENUTO',
]

const templates            = ref([])
const templatePersonalizzati = ref(new Set())
const tipoAttivo           = ref(null)
const saving               = ref(false)
const errore               = ref('')
const form                 = reactive({ oggetto: '', corpo_html: '' })

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
    Object.assign(form, { oggetto: res.data.oggetto, corpo_html: res.data.corpo_html })
  } catch {
    Object.assign(form, { oggetto: '', corpo_html: '' })
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
  CONFERMA_PRENOTAZIONE:   '✅ Conferma prenotazione',
  ANNULLAMENTO_PRENOTAZIONE:'❌ Annullamento prenotazione',
  PROMEMORIA:              '⏰ Promemoria',
  LISTA_ATTESA_NOTIFICA:   '🔔 Lista attesa — notifica',
  LISTA_ATTESA_CONFERMA:   '✔ Lista attesa — conferma',
  RICHIESTA_APPROVAZIONE:  '📩 Richiesta approvazione',
  APPROVAZIONE_ADMIN:      '👍 Approvazione admin',
  RIFIUTO_ADMIN:           '👎 Rifiuto admin',
  MODIFICA_EVENTO:         '✏ Modifica evento',
  ANNULLAMENTO_EVENTO:     '🚫 Annullamento evento',
  RESET_PASSWORD:          '🔑 Reset password',
  BENVENUTO:               '👋 Benvenuto',
}[tipo] ?? tipo)

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.layout { display: grid; grid-template-columns: 240px 1fr; gap: 1rem; align-items: start; }
.sidebar { padding: .5rem; }
.tipo-item { padding: .6rem .85rem; border-radius: 6px; cursor: pointer; margin-bottom: .2rem; display: flex; justify-content: space-between; align-items: center; }
.tipo-item:hover { background: #f0f4f8; }
.tipo-item.active { background: #ebf5fb; color: #1a5276; font-weight: 600; }
.tipo-nome { font-size: .88rem; }
.badge-custom { background: #d6eaf8; color: #1a5276; border-radius: 8px; padding: .1rem .45rem; font-size: .7rem; }
.empty { padding: 3rem; text-align: center; color: #aaa; }
.editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.editor-actions { display: flex; gap: .5rem; }
.form-group { margin-bottom: .9rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.mono-area { font-family: 'Courier New', monospace; font-size: .85rem; }
.helper { font-size: .8rem; color: #888; margin-bottom: .35rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }
</style>
