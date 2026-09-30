<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { loginAsStudent, loginAsTeacher } from '../session'
import ErrorBox from '../components/ErrorBox.vue'

const router = useRouter()
const students = ref([])
const studentId = ref('')
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    students.value = (await api.students()).data
    studentId.value = students.value[0]?.id ?? ''
  } catch (e) {
    error.value = e
  } finally {
    loading.value = false
  }
})

function enterAsTeacher() {
  loginAsTeacher()
  router.push('/teacher/exams')
}

function enterAsStudent() {
  const student = students.value.find((s) => s.id === Number(studentId.value))
  if (!student) return
  loginAsStudent(student)
  router.push('/student/exams')
}
</script>

<template>
  <section class="hero">
    <h1>Provas online</h1>
    <p class="muted">Escolha como deseja entrar.</p>
  </section>

  <ErrorBox :error="error" />

  <div class="grid-2">
    <div class="card">
      <h2>👩‍🏫 Professor</h2>
      <p class="muted">Cadastre provas, gerencie questões e acompanhe o desempenho da turma.</p>
      <button class="btn btn-primary" @click="enterAsTeacher">Entrar como professor</button>
    </div>

    <div class="card">
      <h2>🎓 Aluno</h2>
      <p class="muted">Faça as provas disponíveis e veja sua pontuação.</p>
      <p v-if="loading" class="muted">Carregando alunos…</p>
      <template v-else-if="students.length">
        <label class="field">
          <span>Quem é você?</span>
          <select v-model="studentId">
            <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </label>
        <button class="btn btn-primary" @click="enterAsStudent">Entrar como aluno</button>
      </template>
    </div>
  </div>
</template>
