<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../../api'
import { dateTime, pct } from '../../format'
import ErrorBox from '../../components/ErrorBox.vue'

const route = useRoute()
const attempt = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    attempt.value = (await api.attempt(route.params.id)).data
  } catch (e) {
    error.value = e
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <ErrorBox :error="error" />
  <p v-if="loading" class="muted">Carregando…</p>

  <template v-else-if="attempt">
    <div class="card result">
      <span class="muted">{{ attempt.exam?.title }} · {{ dateTime(attempt.submitted_at) }}</span>
      <div class="score">{{ attempt.score }} <small>/ {{ attempt.total_questions }}</small></div>
      <div class="percent">{{ pct(attempt.percentage) }} de acertos</div>
      <div class="progress"><div :style="{ width: `${attempt.percentage}%` }"></div></div>
    </div>

    <h2>Gabarito</h2>
    <div
      v-for="(d, i) in attempt.details"
      :key="d.question_id"
      class="card stack review"
      :class="d.is_correct ? 'review-ok' : 'review-wrong'"
    >
      <div class="row-between">
        <h3>{{ i + 1 }}. {{ d.statement }}</h3>
        <span class="badge" :class="d.is_correct ? 'badge-ok' : 'badge-wrong'">
          {{ d.is_correct ? 'Acertou' : 'Errou' }}
        </span>
      </div>
      <div>Sua resposta: <strong>{{ d.selected_option.text }}</strong></div>
      <div v-if="!d.is_correct">
        Resposta correta: <strong>{{ d.correct_option?.text }}</strong>
      </div>
    </div>

    <router-link to="/student/exams" class="btn btn-ghost">← Voltar às provas</router-link>
  </template>
</template>
