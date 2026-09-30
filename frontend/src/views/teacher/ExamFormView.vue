<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../../api'
import ErrorBox from '../../components/ErrorBox.vue'

const MIN_OPTIONS = 2
const MAX_OPTIONS = 6

const route = useRoute()
const router = useRouter()
const id = route.params.id
const isEdit = Boolean(id)

const loading = ref(isEdit)
const saving = ref(false)
const error = ref(null)
const problems = ref([])
const locked = ref(false) // prova com tentativas: só título/descrição podem mudar

function newQuestion() {
  return { statement: '', correct: 0, options: [{ text: '' }, { text: '' }] }
}

const form = reactive({ title: '', description: '', questions: [newQuestion()] })

onMounted(async () => {
  if (!isEdit) return
  try {
    const { data } = await api.exam(id)
    form.title = data.title
    form.description = data.description ?? ''
    form.questions = data.questions.map((q) => ({
      statement: q.statement,
      correct: Math.max(0, q.options.findIndex((o) => o.is_correct)),
      options: q.options.map((o) => ({ text: o.text })),
    }))
    locked.value = data.attempts_count > 0
  } catch (e) {
    error.value = e
  } finally {
    loading.value = false
  }
})

function addQuestion() {
  form.questions.push(newQuestion())
}

function removeQuestion(index) {
  if (form.questions.length > 1) form.questions.splice(index, 1)
}

function addOption(question) {
  if (question.options.length < MAX_OPTIONS) question.options.push({ text: '' })
}

function removeOption(question, index) {
  if (question.options.length <= MIN_OPTIONS) return
  question.options.splice(index, 1)
  if (index < question.correct) question.correct -= 1
  else if (index === question.correct) question.correct = 0
}

function validate() {
  const list = []
  if (!form.title.trim()) list.push('Informe o título da prova.')
  if (!locked.value) {
    form.questions.forEach((q, qi) => {
      if (!q.statement.trim()) list.push(`Questão ${qi + 1}: informe o enunciado.`)
      if (q.options.some((o) => !o.text.trim())) list.push(`Questão ${qi + 1}: preencha todas as alternativas.`)
    })
  }
  problems.value = list
  return list.length === 0
}

function buildPayload() {
  const base = { title: form.title.trim(), description: form.description.trim() || null }
  if (locked.value) return base

  return {
    ...base,
    questions: form.questions.map((q) => ({
      statement: q.statement.trim(),
      options: q.options.map((o, i) => ({ text: o.text.trim(), is_correct: i === q.correct })),
    })),
  }
}

async function save() {
  error.value = null
  if (!validate()) return

  saving.value = true
  try {
    const payload = buildPayload()
    if (isEdit) await api.updateExam(id, payload)
    else await api.createExam(payload)
    router.push('/teacher/exams')
  } catch (e) {
    error.value = e
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="page-head">
    <h1>{{ isEdit ? 'Editar prova' : 'Nova prova' }}</h1>
  </div>

  <p v-if="loading" class="muted">Carregando…</p>

  <form v-else class="stack" @submit.prevent="save">
    <div v-if="locked" class="alert alert-warn">
      Esta prova já possui tentativas de alunos. Só o título e a descrição podem ser alterados;
      as questões ficam bloqueadas para não corromper o histórico.
    </div>

    <ErrorBox :error="error" />
    <div v-if="problems.length" class="alert alert-error" role="alert">
      <ul>
        <li v-for="(p, i) in problems" :key="i">{{ p }}</li>
      </ul>
    </div>

    <div class="card stack">
      <label class="field">
        <span>Título</span>
        <input v-model="form.title" type="text" maxlength="255" placeholder="Ex.: Fundamentos de PHP" />
      </label>
      <label class="field">
        <span>Descrição (opcional)</span>
        <textarea v-model="form.description" rows="2" maxlength="2000"></textarea>
      </label>
    </div>

    <div v-for="(q, qi) in form.questions" :key="qi" class="card stack">
      <div class="row-between">
        <h3>Questão {{ qi + 1 }}</h3>
        <button
          v-if="!locked && form.questions.length > 1"
          type="button"
          class="btn btn-ghost"
          @click="removeQuestion(qi)"
        >
          Remover questão
        </button>
      </div>

      <label class="field">
        <span>Enunciado</span>
        <textarea v-model="q.statement" rows="2" maxlength="1000" :disabled="locked"></textarea>
      </label>

      <div class="field">
        <span>Alternativas <small class="muted">(marque a correta)</small></span>
        <div v-for="(o, oi) in q.options" :key="oi" class="option-row">
          <input
            v-model="q.correct"
            type="radio"
            :name="`correct-${qi}`"
            :value="oi"
            :disabled="locked"
            :title="'Marcar como correta'"
          />
          <input
            v-model="o.text"
            type="text"
            maxlength="500"
            :placeholder="`Alternativa ${oi + 1}`"
            :disabled="locked"
          />
          <button
            v-if="!locked && q.options.length > MIN_OPTIONS"
            type="button"
            class="btn btn-ghost"
            title="Remover alternativa"
            @click="removeOption(q, oi)"
          >
            ✕
          </button>
        </div>
        <button
          v-if="!locked && q.options.length < MAX_OPTIONS"
          type="button"
          class="btn btn-ghost"
          @click="addOption(q)"
        >
          + Alternativa
        </button>
      </div>
    </div>

    <div class="row-between">
      <button v-if="!locked" type="button" class="btn btn-ghost" @click="addQuestion">+ Questão</button>
      <span v-else></span>
      <div class="row">
        <router-link to="/teacher/exams" class="btn btn-ghost">Cancelar</router-link>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Salvando…' : 'Salvar prova' }}
        </button>
      </div>
    </div>
  </form>
</template>
