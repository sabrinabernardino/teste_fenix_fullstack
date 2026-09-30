import { session, logout } from './session'

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

function qs(params = {}) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') query.set(key, value)
  })
  const text = query.toString()
  return text ? `?${text}` : ''
}

async function request(method, path, body) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (session.role) headers['X-Role'] = session.role
  if (session.role === 'student' && session.studentId) headers['X-Student-Id'] = String(session.studentId)

  let response
  try {
    response = await fetch(`/api${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new ApiError(0, 'Não foi possível conectar à API. Verifique se os containers estão rodando.')
  }

  if (response.status === 204) return null

  const json = await response.json().catch(() => ({}))

  if (!response.ok) {
    // Sessão inválida (ex.: banco recriado): volta para a tela de entrada.
    if ((response.status === 401 || response.status === 403) && session.role) {
      logout()
      window.location.assign('/')
    }
    throw new ApiError(response.status, json.message || 'Erro inesperado.', json.errors || {})
  }

  return json
}

export const api = {
  students: () => request('GET', '/students'),

  // Professor
  exams: () => request('GET', '/exams'),
  exam: (id) => request('GET', `/exams/${id}`),
  createExam: (payload) => request('POST', '/exams', payload),
  updateExam: (id, payload) => request('PUT', `/exams/${id}`, payload),
  deleteExam: (id) => request('DELETE', `/exams/${id}`),
  stats: (examId) => request('GET', `/dashboard/stats${qs({ exam_id: examId })}`),
  ranking: (params) => request('GET', `/dashboard/ranking${qs(params)}`),

  // Aluno
  studentExams: () => request('GET', '/student/exams'),
  studentExam: (id) => request('GET', `/student/exams/${id}`),
  submitAttempt: (examId, answers) => request('POST', `/student/exams/${examId}/attempts`, { answers }),
  attempts: () => request('GET', '/student/attempts'),
  attempt: (id) => request('GET', `/student/attempts/${id}`),
}
