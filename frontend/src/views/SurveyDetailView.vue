<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { surveyApi, eventApi, type SurveyDetail, type Event } from '../api'
import AppModal from '../components/AppModal.vue'
import { formatDate as formatEventDate } from '../utils/date'

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

function errorMessage(e: any): string {
  return e.response?.data?.errors ? Object.values(e.response.data.errors).join(', ') : 'Fehler beim Speichern.'
}

// ── Survey title ─────────────────────────────────────────────────────────────

const titleModalOpen = ref(false)
const titleForm = ref('')
const titleSaving = ref(false)
const titleError = ref('')

function openTitleEdit() {
  titleForm.value = survey.value?.title ?? ''
  titleError.value = ''
  titleModalOpen.value = true
}

async function saveTitle() {
  if (!survey.value) return
  titleSaving.value = true
  titleError.value = ''
  try {
    survey.value.title = (await surveyApi.update(survey.value.id, { title: titleForm.value })).title
    titleModalOpen.value = false
  } catch (e: any) {
    titleError.value = errorMessage(e)
  } finally {
    titleSaving.value = false
  }
}

// ── Events ───────────────────────────────────────────────────────────────────

const eventModalOpen = ref(false)
const editingEvent = ref<Event | null>(null)
const eventForm = ref({ title: '', date: '', description: '' })
const eventSaving = ref(false)
const eventError = ref('')

function openEventCreate() {
  editingEvent.value = null
  eventForm.value = { title: '', date: '', description: '' }
  eventError.value = ''
  eventModalOpen.value = true
}

function openEventEdit(item: Event) {
  editingEvent.value = item
  eventForm.value = { title: item.title, date: item.date, description: item.description ?? '' }
  eventError.value = ''
  eventModalOpen.value = true
}

function sortEvents() {
  survey.value?.events.sort((a, b) => a.date.localeCompare(b.date))
}

async function saveEvent() {
  if (!survey.value) return
  eventSaving.value = true
  eventError.value = ''
  try {
    const payload = { ...eventForm.value, description: eventForm.value.description || null }
    if (editingEvent.value) {
      const updated = await eventApi.update(editingEvent.value.id, payload)
      const idx = survey.value.events.findIndex(i => i.id === updated.id)
      if (idx !== -1) survey.value.events[idx] = updated
    } else {
      survey.value.events.push(await eventApi.create(survey.value.id, payload))
    }
    sortEvents()
    eventModalOpen.value = false
  } catch (e: any) {
    eventError.value = errorMessage(e)
  } finally {
    eventSaving.value = false
  }
}

async function removeEvent(item: Event) {
  if (!survey.value) return
  const message = `Event «${item.title}» löschen?\n\n`
    + 'Alle Antworten und Einteilungen für dieses Event werden ebenfalls gelöscht.'
  if (!confirm(message)) return
  await eventApi.remove(item.id)
  survey.value.events = survey.value.events.filter(i => i.id !== item.id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.back()" class="text-gray-400 hover:text-gray-600 text-sm">← Zurück</button>
      <h1 class="text-xl font-semibold text-gray-800">{{ survey?.title ?? 'Umfrage-Details' }}</h1>
      <button v-if="survey" @click="openTitleEdit" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Umbenennen</button>
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

      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-700">Events</h2>
        <button @click="openEventCreate" class="bg-indigo-600 text-white text-xs px-3 py-1.5 rounded-md hover:bg-indigo-700 transition-colors">Neues Event</button>
      </div>
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-6">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Datum</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Titel</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Beschreibung</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="survey.events.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-400">Noch keine Events erfasst.</td></tr>
            <tr v-for="item in survey.events" :key="item.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ formatEventDate(item.date) }}</td>
              <td class="px-4 py-3 text-gray-800">{{ item.title }}</td>
              <td class="px-4 py-3 text-gray-500 truncate max-w-xs">{{ item.description ?? '—' }}</td>
              <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                <button @click="openEventEdit(item)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Bearbeiten</button>
                <button @click="removeEvent(item)" class="text-red-500 hover:text-red-700 text-xs font-medium">Löschen</button>
              </td>
            </tr>
          </tbody>
        </table>
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

  <AppModal v-if="titleModalOpen" title="Umfrage umbenennen" @close="titleModalOpen = false">
    <form @submit.prevent="saveTitle" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
        <input v-model="titleForm" type="text" required maxlength="200" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <p v-if="titleError" class="text-sm text-red-600">{{ titleError }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="titleModalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="titleSaving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ titleSaving ? 'Speichern…' : 'Speichern' }}</button>
      </div>
    </form>
  </AppModal>

  <AppModal v-if="eventModalOpen" :title="editingEvent ? 'Event bearbeiten' : 'Neues Event'" @close="eventModalOpen = false">
    <form @submit.prevent="saveEvent" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
        <input v-model="eventForm.title" type="text" required maxlength="200" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
        <input v-model="eventForm.date" type="date" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung (optional)</label>
        <textarea v-model="eventForm.description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <p v-if="eventError" class="text-sm text-red-600">{{ eventError }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="eventModalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="eventSaving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ eventSaving ? 'Speichern…' : 'Speichern' }}</button>
      </div>
    </form>
  </AppModal>
</template>
