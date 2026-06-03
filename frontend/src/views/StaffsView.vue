<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { staffApi, skillApi, type Staff, type Skill } from '../api'
import AppModal from '../components/AppModal.vue'

const items = ref<Staff[]>([])
const allSkills = ref<Skill[]>([])
const loading = ref(false)
const modalOpen = ref(false)
const editing = ref<Staff | null>(null)
const form = ref({ name: '', isLeader: false, skillIds: [] as number[] })
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  ;[items.value, allSkills.value] = await Promise.all([staffApi.list(), skillApi.list()])
  loading.value = false
}

function openCreate() {
  editing.value = null
  form.value = { name: '', isLeader: false, skillIds: [] }
  error.value = ''
  modalOpen.value = true
}

function openEdit(item: Staff) {
  editing.value = item
  form.value = { name: item.name, isLeader: item.isLeader, skillIds: item.skills.map(s => s.id) }
  error.value = ''
  modalOpen.value = true
}

function toggleSkill(id: number) {
  const idx = form.value.skillIds.indexOf(id)
  if (idx === -1) form.value.skillIds.push(id)
  else form.value.skillIds.splice(idx, 1)
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      const updated = await staffApi.update(editing.value.id, form.value)
      const idx = items.value.findIndex(i => i.id === updated.id)
      if (idx !== -1) items.value[idx] = updated
    } else {
      items.value.push(await staffApi.create(form.value))
    }
    modalOpen.value = false
  } catch (e: any) {
    error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).join(', ') : 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

async function remove(item: Staff) {
  if (!confirm(`Staff "${item.name}" wirklich löschen?`)) return
  await staffApi.remove(item.id)
  items.value = items.value.filter(i => i.id !== item.id)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold text-gray-800">Staffs</h1>
      <button @click="openCreate" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">Neu</button>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Name</th>
            <th class="text-left px-4 py-3 font-medium text-gray-600">Skills</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading"><td colspan="3" class="px-4 py-6 text-center text-gray-400">Laden…</td></tr>
          <tr v-else-if="items.length === 0"><td colspan="3" class="px-4 py-6 text-center text-gray-400">Noch keine Staffs vorhanden.</td></tr>
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <span class="text-gray-800">{{ item.name }}</span>
              <span v-if="item.isLeader" class="ml-2 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">Leader</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="skill in item.skills" :key="skill.id"
                  class="text-xs px-2 py-0.5 rounded-full font-medium"
                  :style="{ backgroundColor: skill.skillType.color + '22', color: skill.skillType.color }"
                >{{ skill.name }}</span>
              </div>
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

  <AppModal v-if="modalOpen" :title="editing ? 'Staff bearbeiten' : 'Neuer Staff'" @close="modalOpen = false">
    <form @submit.prevent="save" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.isLeader" type="checkbox" id="isLeader" class="rounded" />
        <label for="isLeader" class="text-sm text-gray-700">Leader</label>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Skills</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="skill in allSkills" :key="skill.id"
            type="button"
            @click="toggleSkill(skill.id)"
            class="text-xs px-2.5 py-1 rounded-full border font-medium transition-colors"
            :class="form.skillIds.includes(skill.id) ? 'text-white border-transparent' : 'bg-white border-gray-300 text-gray-600'"
            :style="form.skillIds.includes(skill.id) ? { backgroundColor: skill.skillType.color, borderColor: skill.skillType.color } : {}"
          >{{ skill.name }}</button>
        </div>
      </div>
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="modalOpen = false" class="text-sm px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">Abbrechen</button>
        <button type="submit" :disabled="saving" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">{{ saving ? 'Speichern…' : 'Speichern' }}</button>
      </div>
    </form>
  </AppModal>
</template>