<script setup lang="ts">
import { computed } from 'vue'
import type { SurveyStatus } from '../api'

const props = defineProps<{ status: SurveyStatus; isDeadlinePassed: boolean }>()

const badge = computed(() => {
  if (props.status === 'closed') return { label: 'Geschlossen', classes: 'bg-gray-100 text-gray-500' }
  if (props.isDeadlinePassed) return { label: 'Offen · Deadline vorbei', classes: 'bg-amber-100 text-amber-700' }
  return { label: 'Offen', classes: 'bg-green-100 text-green-700' }
})
</script>

<template>
  <span class="text-xs font-medium px-2 py-0.5 rounded-full whitespace-nowrap" :class="badge.classes">{{ badge.label }}</span>
</template>
