<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { eventApi, type Event } from '../api'
import AppModal from '../components/AppModal.vue'

const items = ref<Event[]>([])
const loading = ref(false)
const modalOpen = ref(false)
const editing = ref<Event | null>(null)
const form = ref({ title: '', date: '', description: '' })
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  items.value = await eventApi.list()
  loading.value = false
}

function openCreate() {
  editing.value = null
  form.value = { title: '', date: '', description: '' }
  error.value = ''
  modalOpen.value = true
}

function openEdit(item: Event) {
  editing.value = item
  form.value = { title: item.title, date: item.date, description: item.description ?? '' }
  error.value = ''
  modalOpen.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = { ...form.value, description: form.value.description || null }
    if (editing.value) {
      const updated = await eventApi.update(editing.value.id, payload)
      const idx = items.value.findIndex(i => i.id === updated.id)
      if (idx !== -1) items.value[idx] = updated
    } else {
      items.value.push(await eventApi.create(payload as { title: string; date: string }))
    }
    modalOpen.value = false
  } catch (e: any) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).join(', ') : 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

async function remove(item: Event) {
  if (!confirm(`Event "${item.title}" wirklich löschen?`)) return
  await eventApi.remove(item.id)
  items.value = items.value.filter(i => i.id !== item.id)
}

const formatDate = (d: string) => new Date(d).toLocaleDateString('de-CH')

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-gray-800">Events</h1>
      <button @click="openCreate" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">Neu</button>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
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
          <tr v-if="loading"><td colspan="4" class="px-4 py-6 text-center text-gray-400">Laden…</td></tr>
          <tr v-else-if="items.length === 0"><td colspan="4" class="px-4 py-6 text-center text-gray-400">Noch keine Events vorhanden.</td></tr>
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ formatDate(item.date) }}</td>
            <td class="px-4 py-3 text-gray-800">{{ item.title }}</td>
            <td class="px-4 py-3 text-gray-500 truncate max-w-xs">{{ item.description ?? '—' }}</td>
            <td class="px-4 py-3 text-right space-x-2">
              <button @click="openEdit(item)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Bearbeiten</button>
              <button @click="remove(item)" class="text-red-500 hover:text-red-700 text-xs font-medium">Löschen</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <AppModal v-if="modalOpen" :title="editing ? 'Event bearbeiten' : 'Neues Event'" @close="modalOpen = false">
    <form @submit.prevent="save" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Titel</label>
        <input v-model="form.title" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
        <input v-model="form.date" type="date" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung (optional)</label>
        <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="modalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="saving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ saving ? 'Speichern…' : 'Speichern' }}</button>
      </div>
    </form>
  </AppModal>
</template>