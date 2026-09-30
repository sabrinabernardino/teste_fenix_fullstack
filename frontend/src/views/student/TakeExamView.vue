<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../../api'
import ErrorBox from '../../components/ErrorBox.vue'

const route = useRoute()
const router = useRouter()

const exam = ref(null)
const answers = reactive({}) // questionId -> optionId
const loading = ref(true)
const submitting = ref(false)
const error = ref(null)

onMounted(async () => {
  try {
    const { data } = await api.studentExam(route.params.id)
    if (data.already_taken) {
      router.replace(`/student/results/${data.attempt_id}`)
      return
    }
    exam.value = data
  } catch (e) {
    error.value = e
  } finally {
    loading.value = false
  }
})

const total = computed(() => exam.value?.questions.length ?? 0)
const answered = computed(() => exam.value?.questions.filter((q) => answers[q.id]).length ?? 0)
const complete = computed(() => total.value > 0 && answered.value === total.value)

async function submit() {
  if (!complete.value) return
  if (!confirm('Enviar as respostas? Não será possível refazer esta prova.')) return

  submitting.value = true
  error.value = null
  try {
    const payload = exam.value.questions.map((q) => ({ question_id: q.id, option_id: answers[q.id] }))
    const { data } = await api.submitAttempt(exam.value.id, payload)
    router.replace(`/student/results/${data.id}`)
  } catch (e) {
    error.value = e
    submitting.value = false
  }
}
</script>

<template>
  <ErrorBox :error="error" />
  <p v-if="loading" class="muted">Carregando…</p>

  <template v-else-if="exam">
    <div class="page-head">
      <div>
        <h1>{{ exam.title }}</h1>
        <p class="muted">{{ exam.description }}</p>
      </div>
      <span class="badge badge-todo">{{ answered }}/{{ total }} respondidas</span>
    </div>

    <div class="progress"><div :style="{ width: `${total ? (answered / total) * 100 : 0}%` }"></div></div>

    <div v-for="(q, qi) in exam.questions" :key="q.id" class="card stack">
      <h3>{{ qi + 1 }}. {{ q.statement }}</h3>
      <label
        v-for="o in q.options"
        :key="o.id"
        class="choice"
        :class="{ selected: answers[q.id] === o.id }"
      >
        <input v-model="answers[q.id]" type="radio" :name="`q-${q.id}`" :value="o.id" />
        <span>{{ o.text }}</span>
      </label>
    </div>

    <div class="row-between">
      <router-link to="/student/exams" class="btn btn-ghost">Voltar</router-link>
      <button class="btn btn-primary" :disabled="!complete || submitting" @click="submit">
        {{ submitting ? 'Enviando…' : 'Enviar respostas' }}
      </button>
    </div>
  </template>
</template>
