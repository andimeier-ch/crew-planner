<script setup lang="ts">
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

function logout() {
  auth.logout()
  router.push('/login')
}

const navLinks = [
  { to: '/skill-types', label: 'Skill-Typen' },
  { to: '/skills', label: 'Skills' },
  { to: '/staffs', label: 'Staffs' },
  { to: '/events', label: 'Events' },
  { to: '/surveys', label: 'Umfragen' },
]
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white border-b border-gray-200">
      <div class="max-w-screen-xl mx-auto px-4 flex items-center gap-6 h-14">
        <span class="font-semibold text-gray-800 mr-2">Crew Planner</span>
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          class="text-sm text-gray-600 hover:text-gray-900 transition-colors"
          active-class="text-indigo-600 font-medium"
        >
          {{ link.label }}
        </RouterLink>
        <button
          @click="logout"
          class="ml-auto text-sm text-gray-500 hover:text-gray-800 transition-colors"
        >
          Abmelden
        </button>
      </div>
    </nav>
    <main class="max-w-screen-xl mx-auto px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>