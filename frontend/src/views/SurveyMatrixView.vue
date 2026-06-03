<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { surveyApi, assignmentApi, type MatrixData, type MatrixStaff, type Event } from '../api'

const route = useRoute()
const router = useRouter()

const matrix = ref<MatrixData | null>(null)
const loading = ref(true)

// Popover state: which cell is open {staffId, eventId}
const activeCell = ref<{ staffId: number; eventId: number } | null>(null)
const saving = ref(false)

async function load() {
  matrix.value = await surveyApi.matrix(Number(route.params.id))
  loading.value = false
}

function cellAvailability(staff: MatrixStaff, event: Event): boolean | null {
  return staff.availability[event.id] ?? null
}

function cellAssignments(staff: MatrixStaff, event: Event) {
  return staff.assignments.filter(a => a.eventId === event.id)
}

function isAssigned(staff: MatrixStaff, event: Event, skillId: number): boolean {
  return staff.assignments.some(a => a.eventId === event.id && a.skillId === skillId)
}

function toggleCell(staff: MatrixStaff, event: Event) {
  if (activeCell.value?.staffId === staff.id && activeCell.value?.eventId === event.id) {
    activeCell.value = null
  } else {
    activeCell.value = { staffId: staff.id, eventId: event.id }
  }
}

async function toggleAssignment(staff: MatrixStaff, event: Event, skillId: number) {
  if (saving.value) return
  saving.value = true
  const existing = staff.assignments.find(a => a.eventId === event.id && a.skillId === skillId)
  try {
    if (existing) {
      await assignmentApi.remove(existing.assignmentId)
      staff.assignments = staff.assignments.filter(a => a.assignmentId !== existing.assignmentId)
    } else {
      const res = await assignmentApi.create({ staffId: staff.id, eventId: event.id, skillId })
      staff.assignments.push({ eventId: event.id, skillId, assignmentId: res.id })
    }
  } finally {
    saving.value = false
  }
}

function cellBg(staff: MatrixStaff, event: Event): string {
  const avail = cellAvailability(staff, event)
  if (avail === true) return 'bg-green-50'
  if (avail === false) return 'bg-red-50'
  return 'bg-white'
}

function closePopover(e: MouseEvent) {
  if (!(e.target as HTMLElement).closest('[data-matrix-cell]')) {
    activeCell.value = null
  }
}

onMounted(() => {
  load()
  document.addEventListener('click', closePopover)
})
onUnmounted(() => document.removeEventListener('click', closePopover))

const formatDate = (d: string) => new Date(d).toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit' })
</script>

<template>
  <div>
    <div class="flex items-center gap-3 mb-6">
      <button @click="router.back()" class="text-gray-400 hover:text-gray-600 text-sm">← Zurück</button>
      <h1 class="text-xl font-semibold text-gray-800">Einteilungs-Matrix</h1>
      <span v-if="matrix" class="text-sm text-gray-500 ml-2">
        Deadline: {{ new Date(matrix.survey.deadline).toLocaleString('de-CH') }}
        <span class="ml-2 text-xs font-medium px-2 py-0.5 rounded-full" :class="matrix.survey.isExpired ? 'bg-gray-100 text-gray-500' : 'bg-green-100 text-green-700'">
          {{ matrix.survey.isExpired ? 'Abgelaufen' : 'Aktiv' }}
        </span>
      </span>
    </div>

    <div v-if="loading" class="text-gray-400 text-sm">Laden…</div>

    <template v-else-if="matrix">
      <!-- Legend -->
      <div class="flex items-center gap-4 mb-4 text-xs text-gray-500">
        <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-green-50 border border-green-200 inline-block" /> Verfügbar</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-red-50 border border-red-200 inline-block" /> Nicht verfügbar</span>
        <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded bg-white border border-gray-200 inline-block" /> Keine Antwort</span>
        <span class="ml-2 text-gray-400">Farbige Badges = eingeteilt</span>
      </div>

      <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="text-xs border-collapse" style="min-width: max-content">
          <thead>
            <tr class="bg-gray-50">
              <!-- Sticky staff column header -->
              <th class="sticky left-0 z-20 bg-gray-50 border-b border-r border-gray-200 px-4 py-3 text-left font-medium text-gray-600 min-w-48">
                Staff
              </th>
              <!-- Event columns -->
              <th
                v-for="event in matrix.events"
                :key="event.id"
                class="border-b border-r border-gray-200 px-2 py-2 font-medium text-gray-600 text-center whitespace-nowrap min-w-24"
              >
                <div>{{ event.title }}</div>
                <div class="text-gray-400 font-normal">{{ formatDate(event.date) }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="staff in matrix.staffs" :key="staff.id" class="group">
              <!-- Sticky staff info cell -->
              <td class="sticky left-0 z-10 bg-white group-hover:bg-gray-50 border-b border-r border-gray-200 px-4 py-2">
                <div class="font-medium text-gray-800">
                  {{ staff.name }}
                  <span v-if="staff.isLeader" class="ml-1 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">L</span>
                </div>
                <div class="flex flex-wrap gap-1 mt-1">
                  <span
                    v-for="skill in staff.skills" :key="skill.id"
                    class="px-1.5 py-0.5 rounded-full text-white font-medium"
                    :style="{ backgroundColor: skill.skillType.color }"
                  >{{ skill.name }}</span>
                </div>
              </td>

              <!-- Event cells -->
              <td
                v-for="event in matrix.events"
                :key="event.id"
                class="border-b border-r border-gray-200 relative"
                :class="cellBg(staff, event)"
                data-matrix-cell
              >
                <div
                  class="min-h-12 w-full h-full px-2 py-1.5 cursor-pointer flex flex-col gap-1"
                  @click.stop="toggleCell(staff, event)"
                >
                  <!-- Assigned skill badges -->
                  <div class="flex flex-wrap gap-0.5">
                    <span
                      v-for="a in cellAssignments(staff, event)"
                      :key="a.skillId"
                      class="px-1.5 py-0.5 rounded-full text-white text-xs font-medium"
                      :style="{ backgroundColor: staff.skills.find(s => s.id === a.skillId)?.skillType.color ?? '#6b7280' }"
                    >{{ staff.skills.find(s => s.id === a.skillId)?.name ?? '?' }}</span>
                  </div>
                </div>

                <!-- Skill-toggle popover -->
                <div
                  v-if="activeCell?.staffId === staff.id && activeCell?.eventId === event.id"
                  class="absolute z-30 top-full left-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-3 min-w-36"
                  data-matrix-cell
                  @click.stop
                >
                  <div class="text-xs font-medium text-gray-500 mb-2">Skills zuteilen</div>
                  <div v-if="staff.skills.length === 0" class="text-xs text-gray-400">Keine Skills</div>
                  <div class="space-y-1">
                    <button
                      v-for="skill in staff.skills"
                      :key="skill.id"
                      @click="toggleAssignment(staff, event, skill.id)"
                      class="w-full flex items-center gap-2 text-xs px-2 py-1.5 rounded transition-colors text-left"
                      :class="isAssigned(staff, event, skill.id) ? 'text-white' : 'bg-gray-50 hover:bg-gray-100 text-gray-700'"
                      :style="isAssigned(staff, event, skill.id) ? { backgroundColor: skill.skillType.color } : {}"
                    >
                      <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: skill.skillType.color }" />
                      {{ skill.name }}
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>