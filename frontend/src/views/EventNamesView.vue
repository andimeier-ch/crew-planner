<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { eventNameApi, type EventName } from '../api'
import AppModal from '../components/AppModal.vue'

const items = ref<EventName[]>([])
const loading = ref(false)
const modalOpen = ref(false)
const editing = ref<EventName | null>(null)
const form = ref({ name: '' })
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  items.value = await eventNameApi.list()
  loading.value = false
}

function openCreate() {
  editing.value = null
  form.value = { name: '' }
  error.value = ''
  modalOpen.value = true
}

function openEdit(item: EventName) {
  editing.value = item
  form.value = { name: item.name }
  error.value = ''
  modalOpen.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      const updated = await eventNameApi.update(editing.value.id, form.value)
      const idx = items.value.findIndex(i => i.id === updated.id)
      if (idx !== -1) items.value[idx] = updated
    } else {
      const created = await eventNameApi.create(form.value)
      items.value.push(created)
    }
    items.value.sort((a, b) => a.name.localeCompare(b.name, 'de'))
    modalOpen.value = false
  } catch (e: any) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).join(', ') : 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

async function remove(item: EventName) {
  if (!confirm(`Event-Name «${item.name}» löschen?\n\nBestehende Events behalten ihren Titel.`)) return
  await eventNameApi.remove(item.id)
  items.value = items.value.filter(i => i.id !== item.id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-gray-800">Event-Namen</h1>
      <button @click="openCreate" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
        Neu
      </button>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Name</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="2" class="px-4 py-6 text-center text-gray-400">Laden…</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="2" class="px-4 py-6 text-center text-gray-400">Noch keine Event-Namen vorhanden. Sie werden beim Erfassen von Events als Vorschläge angezeigt.</td>
          </tr>
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-800">{{ item.name }}</td>
            <td class="px-4 py-3 text-right space-x-2">
              <button @click="openEdit(item)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Bearbeiten</button>
              <button @click="remove(item)" class="text-red-500 hover:text-red-700 text-xs font-medium">Löschen</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <AppModal v-if="modalOpen" :title="editing ? 'Event-Name bearbeiten' : 'Neuer Event-Name'" @close="modalOpen = false">
    <form @submit.prevent="save" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input v-model="form.name" type="text" required maxlength="200" placeholder="z.B. Gottesdienst" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="modalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="saving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? 'Speichern…' : 'Speichern' }}
        </button>
      </div>
    </form>
  </AppModal>
</template>