<template>
  <div class="page-container">
    <div class="page-header">
      <h1>🏪 Impostazioni Vetrina</h1>
    </div>

    <div v-if="loading" class="loading-state">Caricamento…</div>

    <template v-else>
      <!-- Link vetrina -->
      <div class="card layout-section">
        <h2 class="layout-section-title">🔗 Link pubblico</h2>
        <p style="color:#888;font-size:.82rem;margin:-.25rem 0 .85rem">
          Indirizzo pubblico della tua vetrina eventi.
        </p>
        <div v-if="linkVetrina" class="link-row">
          <a :href="linkVetrina" target="_blank" class="link-value">{{ linkVetrina }}</a>
          <button type="button" class="btn btn-outline btn-sm" @click="copiaLink">
            {{ copiato ? '✓ Copiato' : '📋 Copia' }}
          </button>
        </div>
        <div v-else class="alert-error">
          Nessuna vetrina configurata per questo ente (shop_url mancante).
        </div>
      </div>

      <!-- Colori -->
      <div class="card layout-section">
        <h2 class="layout-section-title">🎨 Colori (gradiente hero)</h2>
        <p style="color:#888;font-size:.82rem;margin:-.25rem 0 .85rem">
          Usati come sfondo hero della vetrina quando non è presente un'immagine di copertina.
        </p>
        <div class="gradient-row">
          <div class="color-picker-group">
            <label>Colore primario</label>
            <div class="color-picker-wrap">
              <input type="color" v-model="form.colore_primario" class="color-input" />
              <input type="text" v-model="form.colore_primario" class="input color-text" placeholder="#4a1fa8" maxlength="7" />
              <button type="button" class="btn-reset-color" @click="form.colore_primario = ''" title="Ripristina default">✕</button>
            </div>
          </div>
          <div class="color-picker-group">
            <label>Colore secondario</label>
            <div class="color-picker-wrap">
              <input type="color" v-model="form.colore_secondario" class="color-input" />
              <input type="text" v-model="form.colore_secondario" class="input color-text" placeholder="#3a8ef6" maxlength="7" />
              <button type="button" class="btn-reset-color" @click="form.colore_secondario = ''" title="Ripristina default">✕</button>
            </div>
          </div>
          <div class="color-picker-group">
            <label>Anteprima</label>
            <div class="gradient-preview" :style="{ background: gradientPreview }"></div>
          </div>
        </div>
        <div style="margin-top:1rem">
          <button class="btn btn-primary btn-sm" :disabled="savingColors" @click="salvaColori">
            {{ savingColors ? 'Salvataggio…' : 'Salva colori' }}
          </button>
        </div>
        <div v-if="erroreColori" class="alert-error" style="margin-top:.6rem">{{ erroreColori }}</div>
        <div v-if="successoColori" class="alert-success" style="margin-top:.6rem">{{ successoColori }}</div>
      </div>

      <!-- Copertina -->
      <div class="card layout-section">
        <h2 class="layout-section-title">🖼 Immagine di copertina</h2>
        <p style="color:#888;font-size:.82rem;margin:-.25rem 0 .85rem">
          Immagine hero mostrata sulla pagina della vetrina.
        </p>
        <div class="copertina-wrap">
          <div class="copertina-preview" :style="copertinaBg">
            <span v-if="!copertina" class="copertina-placeholder">Nessuna immagine</span>
            <button v-if="copertina" type="button" @click="eliminaCopertina" :disabled="uploadingImg" class="copertina-rm" title="Rimuovi immagine">✕</button>
          </div>
          <div class="copertina-actions">
            <label class="btn btn-secondary btn-sm" style="cursor:pointer">
              {{ uploadingImg ? 'Caricamento…' : '📁 Scegli immagine' }}
              <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" :disabled="uploadingImg" @change="onCopertinaFile" />
            </label>
            <span style="color:#aaa;font-size:.8rem">JPG, PNG, WEBP, GIF — max 3 MB</span>
            <div v-if="erroreImg" class="alert-error" style="margin-top:.4rem">{{ erroreImg }}</div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import api from '@/api'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const enteId = computed(() => route.params.enteId)

const loading      = ref(true)
const copertina    = ref('')
const copiato      = ref(false)
const linkVetrina  = ref('')

const form = reactive({ colore_primario: '', colore_secondario: '' })

const savingColors  = ref(false)
const erroreColori  = ref('')
const successoColori = ref('')

const uploadingImg = ref(false)
const erroreImg    = ref('')

const DEFAULT_GRADIENT = 'linear-gradient(135deg,#4a1fa8 0%,#6c63ff 55%,#3a8ef6 100%)'

const gradientPreview = computed(() => {
  const p = form.colore_primario
  const s = form.colore_secondario
  if (p && s) return `linear-gradient(135deg, ${p} 0%, ${s} 100%)`
  if (p)      return `linear-gradient(135deg, ${p} 0%, #3a8ef6 100%)`
  if (s)      return `linear-gradient(135deg, #4a1fa8 0%, ${s} 100%)`
  return DEFAULT_GRADIENT
})

const copertinaBg = computed(() => {
  if (copertina.value) return { backgroundImage: `url(${copertina.value})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  return { background: gradientPreview.value }
})

onMounted(async () => {
  try {
    const res = await api.get(`/enti/${enteId.value}`)
    const ente = res.data
    linkVetrina.value = ente.shop_url ? `${window.location.origin}/vetrina/${ente.shop_url}` : ''
    form.colore_primario  = ente.config?.colore_primario  ?? ''
    form.colore_secondario = ente.config?.colore_secondario ?? ''
    copertina.value = ente.copertina ?? ''
  } finally {
    loading.value = false
  }
})

const copiaLink = async () => {
  try {
    await navigator.clipboard.writeText(linkVetrina.value)
    copiato.value = true
    setTimeout(() => { copiato.value = false }, 2000)
  } catch {
    /* ignora errori clipboard */
  }
}

const salvaColori = async () => {
  savingColors.value  = true
  erroreColori.value  = ''
  successoColori.value = ''
  try {
    await api.patch(`/enti/${enteId.value}/vetrina`, {
      colore_primario:   form.colore_primario  || null,
      colore_secondario: form.colore_secondario || null,
    })
    successoColori.value = 'Colori salvati.'
    setTimeout(() => { successoColori.value = '' }, 3000)
  } catch (err) {
    erroreColori.value = err.response?.data?.message ?? 'Errore durante il salvataggio.'
  } finally {
    savingColors.value = false
  }
}

const onCopertinaFile = async (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingImg.value = true
  erroreImg.value    = ''
  try {
    const fd = new FormData()
    fd.append('file', file)
    const res = await api.post(`/enti/${enteId.value}/vetrina/copertina`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    copertina.value = res.data.copertina
  } catch (err) {
    erroreImg.value = err.response?.data?.message ?? 'Errore durante il caricamento.'
  } finally {
    uploadingImg.value = false
    e.target.value = ''
  }
}

const eliminaCopertina = async () => {
  if (!confirm('Rimuovere l\'immagine di copertina?')) return
  uploadingImg.value = true
  erroreImg.value    = ''
  try {
    await api.delete(`/enti/${enteId.value}/vetrina/copertina`)
    copertina.value = ''
  } catch (err) {
    erroreImg.value = err.response?.data?.message ?? 'Errore durante la rimozione.'
  } finally {
    uploadingImg.value = false
  }
}
</script>

<style scoped>
.page-container { max-width: 860px; margin: 0 auto; padding: 1.5rem 1rem; }
.page-header { margin-bottom: 1.5rem; }
.page-header h1 { font-size: 1.4rem; font-weight: 700; margin: 0; }
.loading-state { color: #888; padding: 2rem 0; }

.card { background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.5rem; margin-bottom: 1.25rem; }

.layout-section-title { font-size: 1rem; font-weight: 700; margin: 0 0 .85rem; color: #2c3e50; }

/* Link */
.link-row { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
.link-value { font-size: .9rem; color: #4a1fa8; word-break: break-all; }

/* Colori */
.gradient-row { display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: flex-end; }
.color-picker-group { display: flex; flex-direction: column; gap: .35rem; }
.color-picker-group label { font-size: .85rem; font-weight: 500; }
.color-picker-wrap { display: flex; align-items: center; gap: .45rem; }
.color-input { width: 38px; height: 38px; padding: 2px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; background: white; }
.color-text { width: 100px; font-family: monospace; font-size: .88rem; }
.btn-reset-color { border: none; background: #eee; border-radius: 4px; padding: .2rem .45rem; cursor: pointer; color: #888; font-size: .82rem; }
.btn-reset-color:hover { background: #fadbd8; color: #c0392b; }
.gradient-preview { width: 140px; height: 38px; border-radius: 8px; border: 1px solid #e8eaed; }

/* Copertina */
.copertina-wrap { display: flex; gap: 1.25rem; align-items: flex-start; flex-wrap: wrap; }
.copertina-preview { width: 220px; height: 130px; border-radius: 10px; overflow: hidden; position: relative; border: 2px solid #e8eaed; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.copertina-placeholder { color: #bbb; font-size: .82rem; }
.copertina-rm { position: absolute; top: 6px; right: 6px; border: none; background: rgba(0,0,0,.55); color: white; border-radius: 50%; width: 22px; height: 22px; font-size: .8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.copertina-rm:hover { background: #c0392b; }
.copertina-actions { display: flex; flex-direction: column; gap: .55rem; justify-content: center; }

/* Alert */
.alert-error   { background: #fdf2f2; color: #c0392b; padding: .5rem .75rem; border-radius: 6px; font-size: .85rem; }
.alert-success { background: #f0fdf4; color: #27ae60; padding: .5rem .75rem; border-radius: 6px; font-size: .85rem; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1rem; border-radius: 8px; font-size: .88rem; font-weight: 500; cursor: pointer; border: none; text-decoration: none; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
.btn-primary  { background: #4a1fa8; color: #fff; }
.btn-primary:hover:not(:disabled)  { background: #3a189a; }
.btn-secondary { background: #f4f4f5; color: #333; }
.btn-secondary:hover:not(:disabled) { background: #e8e8ea; }
.btn-outline   { background: transparent; color: #4a1fa8; border: 1.5px solid #4a1fa8; }
.btn-outline:hover:not(:disabled)  { background: #f0eaf9; }
.btn-sm { padding: .35rem .75rem; font-size: .82rem; }

.input { padding: .42rem .65rem; border: 1.5px solid #dde0e6; border-radius: 8px; font-size: .88rem; color: #1a1a2e; background: #fff; outline: none; }
.input:focus { border-color: #4a1fa8; box-shadow: 0 0 0 3px rgba(74,31,168,.12); }
</style>
