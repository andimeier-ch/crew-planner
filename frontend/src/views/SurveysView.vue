<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { surveyApi, staffApi, eventApi, type SurveySummary, type Staff, type Event } from '../api'
import AppModal from '../components/AppModal.vue'
import { formatDate } from '../utils/date'

const router = useRouter()
const items = ref<SurveySummary[]>([])
const allStaffs = ref<Staff[]>([])
const allEvents = ref<Event[]>([])
const loading = ref(false)
const modalOpen = ref(false)
const form = ref({ deadline: '', staffIds: [] as number[], eventIds: [] as number[] })
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  ;[items.value, allStaffs.value, allEvents.value] = await Promise.all([
    surveyApi.list(), staffApi.list(), eventApi.list(),
  ])
  loading.value = false
}

function openCreate() {
  form.value = { deadline: '', staffIds: [], eventIds: [] }
  error.value = ''
  modalOpen.value = true
}


async function save() {
  saving.value = true
  error.value = ''
  try {
    const created = await surveyApi.create(form.value)
    items.value.unshift({
      id: created.id,
      deadline: created.deadline,
      isExpired: created.isExpired,
      eventCount: created.events.length,
      participantCount: created.participants.length,
    })
    modalOpen.value = false
    router.push(`/surveys/${created.id}`)
  } catch (e: any) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).join(', ') : 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

async function remove(item: SurveySummary) {
  if (!confirm('Umfrage wirklich löschen?')) return
  await surveyApi.remove(item.id)
  items.value = items.value.filter(i => i.id !== item.id)
}

const formatDeadline = (d: string) => new Date(d).toLocaleString('de-CH')

const allStaffsSelected = computed(() => allStaffs.value.length > 0 && form.value.staffIds.length === allStaffs.value.length)
const allEventsSelected = computed(() => allEvents.value.length > 0 && form.value.eventIds.length === allEvents.value.length)

function toggleAllStaffs() {
  form.value.staffIds = allStaffsSelected.value ? [] : allStaffs.value.map(s => s.id)
}
function toggleAllEvents() {
  form.value.eventIds = allEventsSelected.value ? [] : allEvents.value.map(e => e.id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-gray-800">Umfragen</h1>
      <button @click="openCreate" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">Neue Umfrage</button>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Deadline</th>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Events</th>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Teilnehmer</th>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading"><td colspan="5" class="px-4 py-6 text-center text-gray-400">Laden…</td></tr>
          <tr v-else-if="items.length === 0"><td colspan="5" class="px-4 py-6 text-center text-gray-400">Noch keine Umfragen vorhanden.</td></tr>
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ formatDeadline(item.deadline) }}</td>
            <td class="px-4 py-3 text-gray-600">{{ item.eventCount }}</td>
            <td class="px-4 py-3 text-gray-600">{{ item.participantCount }}</td>
            <td class="px-4 py-3">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="item.isExpired ? 'bg-gray-100 text-gray-500' : 'bg-green-100 text-green-700'">
                {{ item.isExpired ? 'Abgelaufen' : 'Aktiv' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <RouterLink :to="`/surveys/${item.id}`" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Details</RouterLink>
              <RouterLink :to="`/surveys/${item.id}/matrix`" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Matrix</RouterLink>
              <button @click="remove(item)" class="text-red-500 hover:text-red-700 text-xs font-medium">Löschen</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <AppModal v-if="modalOpen" title="Neue Umfrage" @close="modalOpen = false">
    <form @submit.prevent="save" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deadline</label>
        <input v-model="form.deadline" type="datetime-local" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-sm font-medium text-gray-700">Staffs</label>
          <button type="button" @click="toggleAllStaffs" class="text-xs text-indigo-600 hover:text-indigo-800">{{ allStaffsSelected ? 'Alle abwählen' : 'Alle auswählen' }}</button>
        </div>
        <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100">
          <label v-for="staff in allStaffs" :key="staff.id" class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
            <input type="checkbox" :value="staff.id" v-model="form.staffIds" class="rounded" />
            <span>{{ staff.name }}</span>
            <span v-if="staff.isLeader" class="text-xs text-amber-600 font-medium">Leader</span>
          </label>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-sm font-medium text-gray-700">Events</label>
          <button type="button" @click="toggleAllEvents" class="text-xs text-indigo-600 hover:text-indigo-800">{{ allEventsSelected ? 'Alle abwählen' : 'Alle auswählen' }}</button>
        </div>
        <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100">
          <label v-for="event in allEvents" :key="event.id" class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
            <input type="checkbox" :value="event.id" v-model="form.eventIds" class="rounded" />
            <span class="text-gray-500 w-28 shrink-0">{{ formatDate(event.date) }}</span>
            <span>{{ event.title }}</span>
          </label>
        </div>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="modalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="saving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ saving ? 'Erstellen…' : 'Erstellen' }}</button>
      </div>
    </form>
  </AppModal>
</template>
