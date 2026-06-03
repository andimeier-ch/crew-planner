<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { surveyApi, type SurveyDetail } from '../api'

const route = useRoute()
const router = useRouter()
const survey = ref<SurveyDetail | null>(null)
const loading = ref(true)
const copied = ref<number | null>(null)

async function load() {
  survey.value = await surveyApi.get(Number(route.params.id))
  loading.value = false
}

function surveyLink(token: string) {
  return `${window.location.origin}/survey/${token}`
}

async function copyLink(token: string, id: number) {
  await navigator.clipboard.writeText(surveyLink(token))
  copied.value = id
  setTimeout(() => { if (copied.value === id) copied.value = null }, 2000)
}

const formatDate = (d: string) => new Date(d).toLocaleString('de-CH')

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.back()" class="text-gray-400 hover:text-gray-600 text-sm">← Zurück</button>
      <h1 class="text-xl font-semibold text-gray-800">Umfrage-Details</h1>
    </div>

    <div v-if="loading" class="text-gray-400 text-sm">Laden…</div>

    <template v-else-if="survey">
      <div class="bg-white border border-gray-200 rounded-lg p-5 mb-6 flex items-center gap-8 text-sm">
        <div>
          <div class="text-xs text-gray-500 mb-0.5">Deadline</div>
          <div class="font-medium text-gray-800">{{ formatDate(survey.deadline) }}</div>
        </div>
        <div>
          <div class="text-xs text-gray-500 mb-0.5">Status</div>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="survey.isExpired ? 'bg-gray-100 text-gray-500' : 'bg-green-100 text-green-700'">
            {{ survey.isExpired ? 'Abgelaufen' : 'Aktiv' }}
          </span>
        </div>
        <div>
          <div class="text-xs text-gray-500 mb-0.5">Events</div>
          <div class="font-medium text-gray-800">{{ survey.events.length }}</div>
        </div>
        <RouterLink :to="`/surveys/${survey.id}/matrix`" class="ml-auto bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
          Matrix öffnen
        </RouterLink>
      </div>

      <h2 class="text-sm font-semibold text-gray-700 mb-3">Teilnehmer & Links</h2>
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Staff</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Umfrage-Link</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Bemerkung</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="p in survey.participants" :key="p.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-800">
                {{ p.staff.name }}
                <span v-if="p.staff.isLeader" class="ml-1.5 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">Leader</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <code class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded truncate max-w-xs">{{ surveyLink(p.token) }}</code>
                  <button
                    @click="copyLink(p.token, p.id)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium shrink-0"
                  >{{ copied === p.id ? '✓ Kopiert' : 'Kopieren' }}</button>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ p.remark ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>