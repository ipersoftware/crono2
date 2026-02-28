<template>
  <div>
    <div class="page-header">
      <h1>🏷 Tag</h1>
      <button @click="apriModal()" class="btn btn-primary">+ Nuovo tag</button>
    </div>

    <div class="card">
      <div v-if="loading" class="loading">Caricamento…</div>
      <div v-else-if="tags.length === 0" class="empty">Nessun tag. Creane uno per categorizzare gli eventi.</div>
      <table v-else class="table">
        <thead>
          <tr><th>Colore</th><th>Nome</th><th>Slug</th><th>Azioni</th></tr>
        </thead>
        <tbody>
          <tr v-for="t in tags" :key="t.id">
            <td>
              <span
                class="color-dot"
                :style="{ background: t.colore || '#ccc' }"
              ></span>
            </td>
            <td>{{ t.nome }}</td>
            <td class="muted">{{ t.slug }}</td>
            <td class="actions">
              <button @click="apriModal(t)" class="btn btn-sm btn-primary">Modifica</button>
              <button @click="elimina(t)" class="btn btn-sm btn-danger">Elimina</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal-box">
        <h2>{{ form.id ? 'Modifica tag' : 'Nuovo tag' }}</h2>
        <form @submit.prevent="salva">
          <div class="form-group">
            <label>Nome *</label>
            <input v-model="form.nome" required class="input" />
          </div>
          <div class="form-group">
            <label>Colore</label>
            <input v-model="form.colore" type="color" class="input color-picker" />
          </div>
          <div v-if="errore" class="alert-error">{{ errore }}</div>
          <div class="modal-actions">
            <button type="button" @click="modal = false" class="btn btn-secondary">Annulla</button>
            <button type="submit" :disabled="saving" class="btn btn-primary">
              {{ saving ? 'Salvataggio…' : 'Salva' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { tagsApi } from '@/api/admin'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route  = useRoute()
const enteId = route.params.enteId

const tags   = ref([])
const loading = ref(false)
const modal   = ref(false)
const saving  = ref(false)
const errore  = ref('')
const form    = reactive({ id: null, nome: '', colore: '#3498db' })

const carica = async () => {
  loading.value = true
  try {
    const res = await tagsApi.index(enteId)
    tags.value = res.data.data ?? res.data
  } finally { loading.value = false }
}

const apriModal = (t = null) => {
  Object.assign(form, { id: null, nome: '', colore: '#3498db' })
  if (t) Object.assign(form, { id: t.id, nome: t.nome, colore: t.colore ?? '#3498db' })
  errore.value = ''
  modal.value  = true
}

const salva = async () => {
  saving.value = true
  errore.value = ''
  try {
    if (form.id) {
      const res = await tagsApi.update(enteId, form.id, form)
      const idx = tags.value.findIndex(t => t.id === form.id)
      if (idx !== -1) tags.value[idx] = res.data
    } else {
      const res = await tagsApi.store(enteId, form)
      tags.value.push(res.data)
    }
    modal.value = false
  } catch (e) {
    errore.value = e.response?.data?.message ?? 'Errore.'
  } finally { saving.value = false }
}

const elimina = async (t) => {
  if (!confirm(`Eliminare il tag "${t.nome}"?`)) return
  await tagsApi.destroy(enteId, t.id)
  tags.value = tags.value.filter(x => x.id !== t.id)
}

onMounted(carica)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.loading, .empty { padding: 2rem; text-align: center; color: #aaa; }
.muted { color: #999; font-size: .85rem; }
.actions { display: flex; gap: .4rem; }
.color-dot { display: inline-block; width: 18px; height: 18px; border-radius: 50%; vertical-align: middle; }
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-box { background: white; border-radius: 12px; padding: 2rem; width: 420px; max-width: 95vw; }
.form-group { margin-bottom: .75rem; }
.form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
.input { width: 100%; padding: .45rem .75rem; border: 1px solid #ddd; border-radius: 6px; font-size: .9rem; box-sizing: border-box; }
.color-picker { padding: .15rem; height: 2.4rem; }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.25rem; }
.alert-error { background: #fadbd8; color: #922b21; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 6px; padding: .45rem 1rem; cursor: pointer; }
</style>
