<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { surveyApi, eventApi, staffApi, type SurveyDetail, type SurveyParticipant, type Event, type Staff, type MailError } from '../api'
import AppModal from '../components/AppModal.vue'
import SurveyStatusBadge from '../components/SurveyStatusBadge.vue'
import { formatDate as formatEventDate } from '../utils/date'

const route = useRoute()
const router = useRouter()
const survey = ref<SurveyDetail | null>(null)
const loading = ref(true)
const copied = ref<number | null>(null)

async function load() {
  survey.value = await surveyApi.get(Number(route.params.id))
  resetInvitationForm()
  loading.value = false
}

async function copyLink(p: SurveyParticipant) {
  await navigator.clipboard.writeText(p.surveyLink)
  copied.value = p.id
  setTimeout(() => { if (copied.value === p.id) copied.value = null }, 2000)
}

const formatDate = (d: string) => new Date(d).toLocaleString('de-CH')

function errorMessage(e: any): string {
  if (e.response?.data?.errors) return Object.values(e.response.data.errors).join(', ')
  return e.response?.data?.error ?? 'Fehler beim Speichern.'
}

/** Describes the data that gets deleted along with an event or participant; empty if there is none. */
function dataLossText(responseCount = 0, assignmentCount = 0): string {
  const parts: string[] = []
  if (responseCount > 0) parts.push(`${responseCount} ${responseCount === 1 ? 'Antwort' : 'Antworten'}`)
  if (assignmentCount > 0) parts.push(`${assignmentCount} ${assignmentCount === 1 ? 'Einteilung' : 'Einteilungen'}`)
  return parts.join(' und ')
}

// ── Survey status ────────────────────────────────────────────────────────────

const statusChanging = ref(false)

async function toggleClosed() {
  if (!survey.value) return
  const closing = survey.value.status !== 'closed'
  if (closing) {
    const message = `Umfrage «${survey.value.title}» schliessen?\n\n`
      + 'Teilnehmende können ihre Antworten danach nicht mehr ändern.'
    if (!confirm(message)) return
  }
  statusChanging.value = true
  try {
    const updated = closing ? await surveyApi.close(survey.value.id) : await surveyApi.reopen(survey.value.id)
    survey.value.status = updated.status
  } finally {
    statusChanging.value = false
  }
}

// ── Invitations ──────────────────────────────────────────────────────────────

const PLACEHOLDERS = ['{firstName}', '{lastName}', '{surveyTitle}', '{surveyLink}', '{deadline}']

const invitationsSending = ref(false)
const mailErrors = ref<MailError[]>([])
const sendError = ref('')
const sendingParticipantId = ref<number | null>(null)
const sentParticipantId = ref<number | null>(null)

async function sendInvitations() {
  if (!survey.value) return
  const withEmail = survey.value.participants.filter(p => p.staff.email).length
  const withoutEmail = survey.value.participants.length - withEmail
  let message = `Einladung an ${withEmail} ${withEmail === 1 ? 'Teilnehmende:n' : 'Teilnehmende'} senden?`
  if (withoutEmail > 0) {
    message += `\n\n${withoutEmail} ${withoutEmail === 1 ? 'Person hat' : 'Personen haben'} keine E-Mail-Adresse `
      + 'und ' + (withoutEmail === 1 ? 'erhält' : 'erhalten') + ' keine Einladung. Den Link kannst du manuell kopieren.'
  }
  message += '\n\nDie Umfrage wird danach geöffnet.'
  if (!confirm(message)) return

  // Never send an outdated text: save pending edits first
  if (invitationDirty.value && !(await saveInvitation())) {
    invitationOpen.value = true
    return
  }

  invitationsSending.value = true
  mailErrors.value = []
  sendError.value = ''
  try {
    const result = await surveyApi.sendInvitations(survey.value.id)
    mailErrors.value = result.mailErrors
    survey.value.status = result.status
    survey.value.participants = result.participants
  } catch (e: any) {
    sendError.value = errorMessage(e)
  } finally {
    invitationsSending.value = false
  }
}

async function sendInvitation(p: SurveyParticipant) {
  if (!survey.value) return
  sendingParticipantId.value = p.id
  sendError.value = ''
  try {
    const updated = await surveyApi.sendInvitation(survey.value.id, p.id)
    p.lastEmailedAt = updated.lastEmailedAt
    sentParticipantId.value = p.id
    setTimeout(() => { if (sentParticipantId.value === p.id) sentParticipantId.value = null }, 2000)
  } catch (e: any) {
    sendError.value = `${p.staff.fullName}: ${errorMessage(e)}`
  } finally {
    sendingParticipantId.value = null
  }
}

const invitationOpen = ref(false)
const invitationForm = ref({ invitationSubject: '', invitationBody: '' })
const invitationSaving = ref(false)
const invitationSaved = ref(false)
const invitationError = ref('')

const invitationDirty = computed(() => !!survey.value && (
  invitationForm.value.invitationSubject !== survey.value.invitationSubject
  || invitationForm.value.invitationBody !== survey.value.invitationBody
))

function resetInvitationForm() {
  if (!survey.value) return
  invitationForm.value = { invitationSubject: survey.value.invitationSubject, invitationBody: survey.value.invitationBody }
}

/** Saves the invitation text; returns false if saving failed. */
async function saveInvitation(): Promise<boolean> {
  if (!survey.value) return false
  invitationSaving.value = true
  invitationError.value = ''
  try {
    const updated = await surveyApi.update(survey.value.id, invitationForm.value)
    survey.value.invitationSubject = updated.invitationSubject
    survey.value.invitationBody = updated.invitationBody
    resetInvitationForm()
    invitationSaved.value = true
    setTimeout(() => { invitationSaved.value = false }, 2000)
    return true
  } catch (e: any) {
    invitationError.value = errorMessage(e)
    return false
  } finally {
    invitationSaving.value = false
  }
}

// ── Survey title & deadline ──────────────────────────────────────────────────

const settingsModalOpen = ref(false)
const settingsForm = ref({ title: '', deadline: '' })
const settingsSaving = ref(false)
const settingsError = ref('')

/** Converts an ISO date string to the local `YYYY-MM-DDTHH:mm` format of a datetime-local input. */
function toDateTimeLocal(iso: string): string {
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function openSettingsEdit() {
  if (!survey.value) return
  settingsForm.value = { title: survey.value.title, deadline: toDateTimeLocal(survey.value.deadline) }
  settingsError.value = ''
  settingsModalOpen.value = true
}

async function saveSettings() {
  if (!survey.value) return
  settingsSaving.value = true
  settingsError.value = ''
  try {
    const updated = await surveyApi.update(survey.value.id, settingsForm.value)
    survey.value.title = updated.title
    survey.value.deadline = updated.deadline
    survey.value.isDeadlinePassed = updated.isDeadlinePassed
    settingsModalOpen.value = false
  } catch (e: any) {
    settingsError.value = errorMessage(e)
  } finally {
    settingsSaving.value = false
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
      if (idx !== -1) survey.value.events[idx] = { ...survey.value.events[idx], ...updated }
    } else {
      const created = await eventApi.create(survey.value.id, payload)
      survey.value.events.push({ ...created, responseCount: 0, assignmentCount: 0 })
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
  const loss = dataLossText(item.responseCount, item.assignmentCount)
  if (loss && !confirm(`Event «${item.title}» löschen?\n\nFür dieses Event existieren ${loss}. Diese werden ebenfalls gelöscht.`)) return
  await eventApi.remove(item.id)
  survey.value.events = survey.value.events.filter(i => i.id !== item.id)
}

// ── Participants ─────────────────────────────────────────────────────────────

const participantModalOpen = ref(false)
const allStaffs = ref<Staff[]>([])
const selectedStaffIds = ref<number[]>([])
const participantSaving = ref(false)
const participantError = ref('')

const availableStaffs = computed(() => {
  const taken = new Set(survey.value?.participants.map(p => p.staff.id))
  return allStaffs.value.filter(s => !taken.has(s.id))
})

const allAvailableSelected = computed(() =>
  availableStaffs.value.length > 0 && selectedStaffIds.value.length === availableStaffs.value.length)

function toggleAllAvailable() {
  selectedStaffIds.value = allAvailableSelected.value ? [] : availableStaffs.value.map(s => s.id)
}

async function openParticipantAdd() {
  selectedStaffIds.value = []
  participantError.value = ''
  participantModalOpen.value = true
  allStaffs.value = await staffApi.list()
}

async function saveParticipants() {
  if (!survey.value) return
  participantSaving.value = true
  participantError.value = ''
  try {
    survey.value.participants = (await surveyApi.addParticipants(survey.value.id, selectedStaffIds.value)).participants
    participantModalOpen.value = false
  } catch (e: any) {
    participantError.value = errorMessage(e)
  } finally {
    participantSaving.value = false
  }
}

async function removeParticipant(p: SurveyParticipant) {
  if (!survey.value) return
  const loss = dataLossText(p.responseCount, p.assignmentCount)
  if (loss && !confirm(`${p.staff.fullName} aus der Umfrage entfernen?\n\nFür diese Person existieren ${loss}. Diese werden ebenfalls gelöscht.`)) return
  await surveyApi.removeParticipant(survey.value.id, p.id)
  survey.value.participants = survey.value.participants.filter(i => i.id !== p.id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.back()" class="text-gray-400 hover:text-gray-600 text-sm">← Zurück</button>
      <h1 class="text-xl font-semibold text-gray-800">{{ survey?.title ?? 'Umfrage-Details' }}</h1>
      <button v-if="survey" @click="openSettingsEdit" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Bearbeiten</button>
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
          <div class="flex items-center gap-2">
            <SurveyStatusBadge :status="survey.status" :is-deadline-passed="survey.isDeadlinePassed" />
            <button
              v-if="survey.status === 'draft'"
              @click="sendInvitations"
              :disabled="invitationsSending || survey.participants.length === 0"
              class="bg-indigo-600 text-white text-xs px-3 py-1.5 rounded-md hover:bg-indigo-700 transition-colors disabled:opacity-50"
            >{{ invitationsSending ? 'Senden…' : 'Einladungen senden' }}</button>
            <button
              v-else
              @click="toggleClosed"
              :disabled="statusChanging"
              class="text-indigo-600 hover:text-indigo-800 text-xs font-medium disabled:opacity-50"
            >{{ survey.status === 'closed' ? 'Wieder öffnen' : 'Umfrage schliessen' }}</button>
          </div>
        </div>
        <div>
          <div class="text-xs text-gray-500 mb-0.5">Events</div>
          <div class="font-medium text-gray-800">{{ survey.events.length }}</div>
        </div>
        <RouterLink :to="`/surveys/${survey.id}/matrix`" class="ml-auto bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
          Matrix öffnen
        </RouterLink>
      </div>

      <div v-if="mailErrors.length > 0" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 mb-6">
        <div class="font-medium mb-1">Folgende Einladungen konnten nicht gesendet werden:</div>
        <ul class="list-disc pl-5 space-y-0.5">
          <li v-for="m in mailErrors" :key="m.participantId">{{ m.name }}: {{ m.error }}</li>
        </ul>
      </div>
      <div v-if="sendError" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 mb-6">{{ sendError }}</div>

      <div class="bg-white border border-gray-200 rounded-lg mb-6 text-sm">
        <button
          type="button"
          @click="invitationOpen = !invitationOpen"
          class="w-full flex items-center gap-3 px-5 py-3 text-left hover:bg-gray-50 rounded-lg"
          :aria-expanded="invitationOpen"
        >
          <span class="text-gray-400 text-xs w-3">{{ invitationOpen ? '▾' : '▸' }}</span>
          <span class="font-semibold text-gray-700 shrink-0">E-Mail Text</span>
          <span v-if="invitationDirty" class="text-xs text-amber-600 font-medium">Ungespeicherte Änderungen</span>
        </button>
        <form v-if="invitationOpen" @submit.prevent="saveInvitation" class="px-5 pb-5 space-y-4 border-t border-gray-100 pt-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Betreff</label>
            <input v-model="invitationForm.invitationSubject" type="text" required maxlength="255" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Text</label>
            <textarea v-model="invitationForm.invitationBody" rows="12" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="text-xs text-gray-500">
            Platzhalter:
            <code v-for="ph in PLACEHOLDERS" :key="ph" class="ml-1 bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">{{ ph }}</code>
          </div>
          <p v-if="invitationError" class="text-sm text-red-600">{{ invitationError }}</p>
          <div class="flex items-center justify-end gap-3">
            <span v-if="invitationSaved" class="text-xs text-green-600 font-medium">✓ Gespeichert</span>
            <button type="button" v-if="invitationDirty" @click="resetInvitationForm" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Verwerfen</button>
            <button type="submit" :disabled="invitationSaving || !invitationDirty" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ invitationSaving ? 'Speichern…' : 'Speichern' }}</button>
          </div>
        </form>
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

      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-700">Teilnehmer & Links</h2>
        <button @click="openParticipantAdd" class="bg-indigo-600 text-white text-xs px-3 py-1.5 rounded-md hover:bg-indigo-700 transition-colors">Teilnehmer hinzufügen</button>
      </div>
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Staff</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Umfrage-Link</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Zuletzt gemailt</th>
              <th class="text-left px-4 py-3 font-medium text-gray-600">Bemerkung</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="survey.participants.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-400">Noch keine Teilnehmer.</td></tr>
            <tr v-for="p in survey.participants" :key="p.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-800">
                {{ p.staff.fullName }}
                <span v-if="p.staff.isLeader" class="ml-1.5 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">Leader</span>
                <span v-if="!p.staff.email" class="ml-1.5 text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-medium">Keine E-Mail</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <code class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded truncate max-w-xs">{{ p.surveyLink }}</code>
                  <button
                    @click="copyLink(p)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium shrink-0"
                  >{{ copied === p.id ? '✓ Kopiert' : 'Kopieren' }}</button>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ p.lastEmailedAt ? formatDate(p.lastEmailedAt) : '—' }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ p.remark ?? '—' }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                <button
                  v-if="survey.status === 'open' && p.staff.email"
                  @click="sendInvitation(p)"
                  :disabled="sendingParticipantId === p.id"
                  class="text-indigo-600 hover:text-indigo-800 text-xs font-medium disabled:opacity-50"
                >{{ sentParticipantId === p.id ? '✓ Gesendet' : sendingParticipantId === p.id ? 'Senden…' : p.lastEmailedAt ? 'Erneut senden' : 'Einladung senden' }}</button>
                <button @click="removeParticipant(p)" class="text-red-500 hover:text-red-700 text-xs font-medium">Entfernen</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>

  <AppModal v-if="settingsModalOpen" title="Umfrage bearbeiten" @close="settingsModalOpen = false">
    <form @submit.prevent="saveSettings" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
        <input v-model="settingsForm.title" type="text" required maxlength="200" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deadline</label>
        <input v-model="settingsForm.deadline" type="datetime-local" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <p v-if="settingsError" class="text-sm text-red-600">{{ settingsError }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="settingsModalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="settingsSaving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ settingsSaving ? 'Speichern…' : 'Speichern' }}</button>
      </div>
    </form>
  </AppModal>

  <AppModal v-if="participantModalOpen" title="Teilnehmer hinzufügen" @close="participantModalOpen = false">
    <form @submit.prevent="saveParticipants" class="space-y-4">
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-sm font-medium text-gray-700">Staffs</label>
          <button v-if="availableStaffs.length > 0" type="button" @click="toggleAllAvailable" class="text-xs text-indigo-600 hover:text-indigo-800">{{ allAvailableSelected ? 'Alle abwählen' : 'Alle auswählen' }}</button>
        </div>
        <div v-if="availableStaffs.length === 0" class="text-sm text-gray-400 border border-gray-200 rounded-md px-3 py-4 text-center">Alle Staffs sind bereits Teilnehmer.</div>
        <div v-else class="max-h-60 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100">
          <label v-for="staff in availableStaffs" :key="staff.id" class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
            <input type="checkbox" :value="staff.id" v-model="selectedStaffIds" class="rounded" />
            <span>{{ staff.fullName }}</span>
            <span v-if="staff.isLeader" class="text-xs text-amber-600 font-medium">Leader</span>
          </label>
        </div>
      </div>
      <p v-if="participantError" class="text-sm text-red-600">{{ participantError }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="participantModalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="participantSaving || selectedStaffIds.length === 0" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ participantSaving ? 'Hinzufügen…' : 'Hinzufügen' }}</button>
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
