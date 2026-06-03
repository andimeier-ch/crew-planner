<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { skillTypeApi, type SkillType } from '../api'
import AppModal from '../components/AppModal.vue'

const items = ref<SkillType[]>([])
const loading = ref(false)
const modalOpen = ref(false)
const editing = ref<SkillType | null>(null)
const form = ref({ name: '', color: '#3b82f6' })
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  items.value = await skillTypeApi.list()
  loading.value = false
}

function openCreate() {
  editing.value = null
  form.value = { name: '', color: '#3b82f6' }
  error.value = ''
  modalOpen.value = true
}

function openEdit(item: SkillType) {
  editing.value = item
  form.value = { name: item.name, color: item.color }
  error.value = ''
  modalOpen.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      const updated = await skillTypeApi.update(editing.value.id, form.value)
      const idx = items.value.findIndex(i => i.id === updated.id)
      if (idx !== -1) items.value[idx] = updated
    } else {
      const created = await skillTypeApi.create(form.value)
      items.value.push(created)
    }
    modalOpen.value = false
  } catch (e: any) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).join(', ') : 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

async function remove(item: SkillType) {
  if (!confirm(`Skill-Typ "${item.name}" wirklich löschen?`)) return
  await skillTypeApi.remove(item.id)
  items.value = items.value.filter(i => i.id !== item.id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-gray-800">Skill-Typen</h1>
      <button @click="openCreate" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
        Neu
      </button>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Bezeichnung</th>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Farbe</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="3" class="px-4 py-6 text-center text-gray-400">Laden…</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="3" class="px-4 py-6 text-center text-gray-400">Noch keine Skill-Typen vorhanden.</td>
          </tr>
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-800">{{ item.name }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-2">
                <span class="w-5 h-5 rounded border border-gray-200" :style="{ backgroundColor: item.color }" />
                {{ item.color }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <button @click="openEdit(item)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Bearbeiten</button>
              <button @click="remove(item)" class="text-red-500 hover:text-red-700 text-xs font-medium">Löschen</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <AppModal v-if="modalOpen" :title="editing ? 'Skill-Typ bearbeiten' : 'Neuer Skill-Typ'" @close="modalOpen = false">
    <form @submit.prevent="save" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Bezeichnung</label>
        <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Farbe</label>
        <div class="flex items-center gap-3">
          <input v-model="form.color" type="color" class="w-10 h-10 rounded border border-gray-300 cursor-pointer" />
          <input v-model="form.color" type="text" pattern="^#[0-9a-fA-F]{6}$" class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
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