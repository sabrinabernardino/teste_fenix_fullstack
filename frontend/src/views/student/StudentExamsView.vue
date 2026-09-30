<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../api'
import { dateTime, pct } from '../../format'
import ErrorBox from '../../components/ErrorBox.vue'

const exams = ref([])
const attempts = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const [e, a] = await Promise.all([api.studentExams(), api.attempts()])
    exams.value = e.data
    attempts.value = a.data
  } catch (err) {
    error.value = err
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page-head"><h1>Provas disponíveis</h1></div>

  <ErrorBox :error="error" />
  <p v-if="loading" class="muted">Carregando…</p>
  <div v-else-if="!exams.length && !error" class="card empty">Nenhuma prova disponível no momento.</div>

  <div v-else class="grid-2">
    <div v-for="exam in exams" :key="exam.id" class="card stack">
      <div>
        <h3>{{ exam.title }}</h3>
        <p class="muted">{{ exam.description }}</p>
        <span class="muted small">{{ exam.questions_count }} questões</span>
      </div>
      <div class="row-between">
        <span class="badge" :class="exam.already_taken ? 'badge-ok' : 'badge-todo'">
          {{ exam.already_taken ? 'Realizada' : 'Pendente' }}
        </span>
        <router-link
          v-if="exam.already_taken"
          :to="`/student/results/${exam.attempt_id}`"
          class="btn btn-ghost"
        >
          Ver resultado
        </router-link>
        <router-link v-else :to="`/student/exams/${exam.id}`" class="btn btn-primary">
          Fazer prova
        </router-link>
      </div>
    </div>
  </div>

  <div v-if="attempts.length" class="card">
    <h2>Meu histórico</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Prova</th>
            <th class="num">Acertos</th>
            <th class="num">Percentual</th>
            <th>Data</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in attempts" :key="a.id">
            <td>{{ a.exam?.title }}</td>
            <td class="num">{{ a.score }}/{{ a.total_questions }}</td>
            <td class="num"><strong>{{ pct(a.percentage) }}</strong></td>
            <td class="muted small">{{ dateTime(a.submitted_at) }}</td>
            <td class="actions">
              <router-link :to="`/student/results/${a.id}`" class="btn btn-ghost">Detalhes</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
