import { reactive } from 'vue'

// "Login" simplificado (o desafio dispensa autenticação): guardamos só o perfil escolhido.
const KEY = 'fenix-session'

function load() {
  try {
    return JSON.parse(localStorage.getItem(KEY)) ?? {}
  } catch {
    return {}
  }
}

const saved = load()

export const session = reactive({
  role: saved.role ?? null, // 'teacher' | 'student'
  studentId: saved.studentId ?? null,
  studentName: saved.studentName ?? null,
})

function persist() {
  localStorage.setItem(KEY, JSON.stringify(session))
}

export function loginAsTeacher() {
  Object.assign(session, { role: 'teacher', studentId: null, studentName: null })
  persist()
}

export function loginAsStudent(student) {
  Object.assign(session, { role: 'student', studentId: student.id, studentName: student.name })
  persist()
}

export function logout() {
  Object.assign(session, { role: null, studentId: null, studentName: null })
  localStorage.removeItem(KEY)
}
