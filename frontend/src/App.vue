<script setup>
import { useRouter } from 'vue-router'
import { session, logout } from './session'

const router = useRouter()

function exit() {
  logout()
  router.push('/')
}
</script>

<template>
  <header class="topbar">
    <div class="container topbar-inner">
      <router-link to="/" class="brand">🔥 Fênix <span>Provas</span></router-link>

      <nav v-if="session.role === 'teacher'" class="nav">
        <router-link to="/teacher/exams">Provas</router-link>
        <router-link to="/teacher/exams/new">Nova prova</router-link>
        <router-link to="/teacher/dashboard">Dashboard</router-link>
      </nav>
      <nav v-else-if="session.role === 'student'" class="nav">
        <router-link to="/student/exams">Minhas provas</router-link>
      </nav>

      <div class="user">
        <a href="/docs/" target="_blank" rel="noopener">API docs</a>
        <template v-if="session.role">
          <span class="muted">
            {{ session.role === 'teacher' ? 'Professor' : session.studentName }}
          </span>
          <button class="btn btn-ghost" @click="exit">Sair</button>
        </template>
      </div>
    </div>
  </header>

  <main class="container">
    <router-view />
  </main>
</template>
