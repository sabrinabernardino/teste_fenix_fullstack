<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '../../api'
import { dateTime, pct } from '../../format'
import ErrorBox from '../../components/ErrorBox.vue'

const exams = ref([])
const examId = ref('')
const perPage = ref(10)
const page = ref(1)

const stats = ref(null)
const ranking = ref({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const [s, r] = await Promise.all([
      api.stats(examId.value),
      api.ranking({ exam_id: examId.value, page: page.value, per_page: perPage.value }),
    ])
    stats.value = s.data
    ranking.value = r
  } catch (e) {
    error.value = e
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    exams.value = (await api.exams()).data
  } catch (e) {
    error.value = e
  }
  await load()
})

watch([examId, perPage], () => {
  page.value = 1
  load()
})

function go(target) {
  if (target < 1 || target > ranking.value.meta.last_page || target === page.value) return
  page.value = target
  load()
}

const pages = computed(() => {
  const { current_page: current, last_page: last } = ranking.value.meta
  const from = Math.max(1, current - 2)
  const to = Math.min(last, current + 2)
  return Array.from({ length: to - from + 1 }, (_, i) => from + i)
})
</script>

<template>
  <div class="page-head">
    <h1>Dashboard</h1>
    <label class="inline-field">
      <span>Prova</span>
      <select v-model="examId">
        <option value="">Todas as provas</option>
        <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.title }}</option>
      </select>
    </label>
  </div>

  <ErrorBox :error="error" />

  <template v-if="stats">
    <div class="stats">
      <div class="card stat">
        <span class="muted">Tentativas</span>
        <strong>{{ stats.total_attempts }}</strong>
      </div>
      <div class="card stat">
        <span class="muted">Média da turma</span>
        <strong>{{ pct(stats.average_percentage) }}</strong>
        <small class="muted">nota média {{ stats.average_score }}</small>
      </div>
      <div class="card stat stat-best">
        <span class="muted">🏆 Melhor pontuação</span>
        <template v-if="stats.best">
          <strong>{{ pct(stats.best.percentage) }}</strong>
          <small>{{ stats.best.student }} · {{ stats.best.score }}/{{ stats.best.total_questions }}</small>
        </template>
        <strong v-else>—</strong>
      </div>
      <div class="card stat">
        <span class="muted">Pior pontuação</span>
        <template v-if="stats.worst">
          <strong>{{ pct(stats.worst.percentage) }}</strong>
          <small>{{ stats.worst.student }} · {{ stats.worst.score }}/{{ stats.worst.total_questions }}</small>
        </template>
        <strong v-else>—</strong>
      </div>
    </div>
  </template>

  <div class="card">
    <div class="row-between">
      <h2>Ranking</h2>
      <label class="inline-field">
        <span>Por página</span>
        <select v-model.number="perPage">
          <option :value="5">5</option>
          <option :value="10">10</option>
          <option :value="20">20</option>
        </select>
      </label>
    </div>

    <p v-if="loading && !ranking.data.length" class="muted">Carregando…</p>
    <p v-else-if="!ranking.data.length" class="muted">Nenhuma tentativa registrada ainda.</p>

    <div v-else class="table-wrap" :class="{ dim: loading }">
      <table>
        <thead>
          <tr>
            <th class="num">#</th>
            <th>Aluno</th>
            <th>Prova</th>
            <th class="num">Acertos</th>
            <th class="num">Percentual</th>
            <th>Realizada em</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in ranking.data" :key="row.attempt_id">
            <td class="num">
              <span class="rank" :class="{ gold: row.rank === 1 }">{{ row.rank }}</span>
            </td>
            <td>{{ row.student }}</td>
            <td>{{ row.exam }}</td>
            <td class="num">{{ row.score }}/{{ row.total_questions }}</td>
            <td class="num"><strong>{{ pct(row.percentage) }}</strong></td>
            <td class="muted small">{{ dateTime(row.submitted_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="ranking.meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost" :disabled="page === 1" @click="go(page - 1)">‹ Anterior</button>
      <button
        v-for="p in pages"
        :key="p"
        class="btn"
        :class="p === page ? 'btn-primary' : 'btn-ghost'"
        @click="go(p)"
      >
        {{ p }}
      </button>
      <button class="btn btn-ghost" :disabled="page === ranking.meta.last_page" @click="go(page + 1)">
        Próxima ›
      </button>
      <span class="muted small">{{ ranking.meta.total }} resultados</span>
    </div>
  </div>
</template>
