import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('jwt_token'))
  const isAuthenticated = computed(() => !!token.value)

  async function login(email: string, password: string) {
    const res = await api.post('/auth/login', { username: email, password })
    token.value = res.data.token
    localStorage.setItem('jwt_token', token.value!)
  }

  function logout() {
    token.value = null
    localStorage.removeItem('jwt_token')
  }

  return { token, isAuthenticated, login, logout }
})