const DAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']

function parseLocalDate(d: string): Date {
  return new Date(d + 'T12:00:00')
}

export function formatDate(d: string): string {
  const date = parseLocalDate(d)
  return `${DAYS[date.getDay()]}, ${date.toLocaleDateString('de-CH')}`
}

export function formatDateShort(d: string): string {
  const date = parseLocalDate(d)
  return `${DAYS[date.getDay()]}, ${date.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit' })}`
}