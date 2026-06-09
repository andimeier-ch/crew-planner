<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { surveyPublicApi } from '../api'
import { formatDate } from '../utils/date'

const route = useRoute()
const token = route.params.token as string

const survey = ref<any>(null)
const loading = ref(true)
const notFound = ref(false)
const responses = ref<Record<number, boolean>>({})
const remark = ref('')
const saving = ref(false)
const saved = ref(false)
const error = ref('')

async function load() {
  try {
    survey.value = await surveyPublicApi.get(token)
    const loaded: Record<number, boolean> = {}
    for (const event of survey.value.events) {
      loaded[event.id] = survey.value.responses[event.id] ?? false
    }
    responses.value = loaded
    remark.value = survey.value.remark ?? ''
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  saved.value = false
  try {
    await surveyPublicApi.respond(token, { responses: responses.value, remark: remark.value })
    saved.value = true
  } catch (e: any) {
    error.value = e.response?.data?.error ?? 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

const formatDeadline = (d: string) => new Date(d).toLocaleString('de-CH')

onMounted(load)
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-2xl mx-auto px-4 py-10">
      <h1 class="text-2xl font-semibold text-gray-800 mb-2">Verfügbarkeits-Umfrage</h1>

      <div v-if="loading" class="text-gray-400 mt-8">Laden…</div>

      <div v-else-if="notFound" class="mt-8 text-gray-500">
        Umfrage nicht gefunden oder Link ungültig.
      </div>

      <template v-else-if="survey">
        <p class="text-sm text-gray-500 mb-1">Hallo <strong>{{ survey.staff.name }}</strong></p>
        <p class="text-sm text-gray-500 mb-6">
          Bitte gib an, an welchen Events du verfügbar bist. Deadline: <strong>{{ formatDeadline(survey.deadline) }}</strong>
        </p>

        <div v-if="survey.isExpired" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-700 mb-6">
          Die Deadline ist abgelaufen. Deine Antworten können nicht mehr geändert werden.
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th class="text-left px-4 py-3 font-medium text-gray-600">Event</th>
                  <th class="text-left px-4 py-3 font-medium text-gray-600">Datum</th>
                  <th class="px-4 py-3 font-medium text-gray-600 text-center">Verfügbar</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="event in survey.events" :key="event.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-800">{{ event.title }}</td>
                  <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ formatDate(event.date) }}</td>
                  <td class="px-4 py-3 text-center">
                    <input
                      type="checkbox"
                      v-model="responses[event.id]"
                      :disabled="survey.isExpired"
                      class="w-5 h-5 accent-green-500 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bemerkung (optional)</label>
            <textarea
              v-model="remark"
              :disabled="survey.isExpired"
              rows="3"
              placeholder="z. B. 'Am 15.03 komme ich etwas später…'"
              class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400"
            />
          </div>

          <div v-if="!survey.isExpired" class="flex items-center gap-4">
            <button
              type="submit"
              :disabled="saving"
              class="bg-indigo-600 text-white text-sm px-6 py-2 rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >{{ saving ? 'Speichern…' : 'Antworten speichern' }}</button>
            <span v-if="saved" class="text-sm text-green-600 font-medium">✓ Gespeichert</span>
            <span v-if="error" class="text-sm text-red-600">{{ error }}</span>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>