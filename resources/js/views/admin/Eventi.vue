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
</template>

<script setup>
import { eventiApi } from '@/api/eventi'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const enteId = route.params.enteId

const eventi = ref([])
const loading = ref(false)
const filtri = reactive({ q: '', stato: '' })

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
.btn-sm { padding: .3rem .65rem; font-size: .82rem; }
.btn-secondary { background: #ecf0f1; color: #2c3e50; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
.btn-success  { background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; }
.btn-warning  { background: #f39c12; color: white; border: none; border-radius: 4px; cursor: pointer; }

@media (max-width: 640px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
  .table tr {
    border: 1px solid #e8eaed;
    border-radius: 8px;
    margin-bottom: .75rem;
    padding: .5rem .75rem;
    background: white;
  }
  .table td {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .5rem;
    padding: .45rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: .9rem;
  }
  .table td:last-child { border-bottom: none; }
  .table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: #777;
    font-size: .78rem;
    white-space: nowrap;
    padding-top: .1rem;
  }
  .actions { justify-content: flex-end; flex-wrap: wrap; }
}
</style>
