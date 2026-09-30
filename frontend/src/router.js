import { createRouter, createWebHistory } from 'vue-router'
import { session } from './session'

import LoginView from './views/LoginView.vue'
import ExamListView from './views/teacher/ExamListView.vue'
import ExamFormView from './views/teacher/ExamFormView.vue'
import DashboardView from './views/teacher/DashboardView.vue'
import StudentExamsView from './views/student/StudentExamsView.vue'
import TakeExamView from './views/student/TakeExamView.vue'
import ResultView from './views/student/ResultView.vue'

const routes = [
  { path: '/', component: LoginView },

  { path: '/teacher/exams', component: ExamListView, meta: { role: 'teacher' } },
  { path: '/teacher/exams/new', component: ExamFormView, meta: { role: 'teacher' } },
  { path: '/teacher/exams/:id/edit', component: ExamFormView, meta: { role: 'teacher' } },
  { path: '/teacher/dashboard', component: DashboardView, meta: { role: 'teacher' } },

  { path: '/student/exams', component: StudentExamsView, meta: { role: 'student' } },
  { path: '/student/exams/:id', component: TakeExamView, meta: { role: 'student' } },
  { path: '/student/results/:id', component: ResultView, meta: { role: 'student' } },

  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
  const required = to.meta.role
  if (!required) return true
  if (session.role !== required) return '/'
  if (required === 'student' && !session.studentId) return '/'
  return true
})

export default router
