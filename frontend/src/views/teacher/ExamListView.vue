<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../api'
import ErrorBox from '../../components/ErrorBox.vue'

const exams = ref([])
const loading = ref(true)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    exams.value = (await api.exams()).data
  } catch (e) {
    error.value = e
  } finally {
    loading.value = false
  }
}

async function remove(exam) {
  const extra = exam.attempts_count
    ? `\n\nAtenção: ${exam.attempts_count} tentativa(s) de alunos também serão apagadas.`
    : ''
  if (!confirm(`Excluir a prova "${exam.title}"?${extra}`)) return

  try {
    await api.deleteExam(exam.id)
    await load()
  } catch (e) {
    error.value = e
  }
}

onMounted(load)
</script>

<template>
  <div class="page-head">
    <h1>Gerenciar provas</h1>
    <router-link to="/teacher/exams/new" class="btn btn-primary">+ Nova prova</router-link>
  </div>

  <ErrorBox :error="error" />
  <p v-if="loading" class="muted">Carregando…</p>

  <div v-else-if="!exams.length && !error" class="card empty">
    <p>Nenhuma prova cadastrada ainda.</p>
    <router-link to="/teacher/exams/new" class="btn btn-primary">Criar a primeira prova</router-link>
  </div>

  <div v-else class="card table-wrap">
    <table>
      <thead>
        <tr>
          <th>Prova</th>
          <th class="num">Questões</th>
          <th class="num">Tentativas</th>
          <th class="actions">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="exam in exams" :key="exam.id">
          <td>
            <strong>{{ exam.title }}</strong>
            <div class="muted small">{{ exam.description }}</div>
          </td>
          <td class="num">{{ exam.questions_count }}</td>
          <td class="num">{{ exam.attempts_count }}</td>
          <td class="actions">
            <router-link :to="`/teacher/exams/${exam.id}/edit`" class="btn btn-ghost">Editar</router-link>
            <button class="btn btn-danger" @click="remove(exam)">Excluir</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
