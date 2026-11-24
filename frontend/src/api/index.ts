import axios from 'axios'

export const api = axios.create({ baseURL: '/api' })

api.interceptors.request.use(config => {
  const token = localStorage.getItem('jwt_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Types

export interface SkillType {
  id: number
  name: string
  color: string
}

export interface Skill {
  id: number
  name: string
  skillType: SkillType
}

export interface Staff {
  id: number
  name: string
  email: string | null
  isLeader: boolean
  skills: Skill[]
}

export interface Event {
  id: number
  title: string
  date: string
  description: string | null
}

export type SurveyStatus = 'open' | 'closed'

export interface SurveySummary {
  id: number
  title: string
  deadline: string
  status: SurveyStatus
  isDeadlinePassed: boolean
  eventCount: number
  participantCount: number
}

export interface SurveyParticipant {
  id: number
  token: string
  remark: string | null
  staff: Staff
}

export interface SurveyDetail {
  id: number
  title: string
  deadline: string
  status: SurveyStatus
  isDeadlinePassed: boolean
  events: Event[]
  participants: SurveyParticipant[]
}

export interface MatrixStaff extends Staff {
  participantId: number
  remark: string | null
  availability: Record<number, boolean | null>
  assignments: { eventId: number; skillId: number; assignmentId: number }[]
}

export interface MatrixData {
  survey: SurveySummary
  events: Event[]
  staffs: MatrixStaff[]
}

// API functions

export const skillTypeApi = {
  list: () => api.get<SkillType[]>('/skill-types').then(r => r.data),
  create: (data: Partial<SkillType>) => api.post<SkillType>('/skill-types', data).then(r => r.data),
  update: (id: number, data: Partial<SkillType>) => api.put<SkillType>(`/skill-types/${id}`, data).then(r => r.data),
  remove: (id: number) => api.delete(`/skill-types/${id}`),
}

export const skillApi = {
  list: () => api.get<Skill[]>('/skills').then(r => r.data),
  create: (data: { name: string; skillTypeId: number }) => api.post<Skill>('/skills', data).then(r => r.data),
  update: (id: number, data: { name?: string; skillTypeId?: number }) => api.put<Skill>(`/skills/${id}`, data).then(r => r.data),
  remove: (id: number) => api.delete(`/skills/${id}`),
}

export const staffApi = {
  list: () => api.get<Staff[]>('/staffs').then(r => r.data),
  create: (data: { name: string; isLeader: boolean; skillIds: number[] }) => api.post<Staff>('/staffs', data).then(r => r.data),
  update: (id: number, data: { name?: string; isLeader?: boolean; skillIds?: number[] }) => api.put<Staff>(`/staffs/${id}`, data).then(r => r.data),
  remove: (id: number) => api.delete(`/staffs/${id}`),
}

export const eventApi = {
  create: (surveyId: number, data: { title: string; date: string; description?: string | null }) => api.post<Event>(`/surveys/${surveyId}/events`, data).then(r => r.data),
  update: (id: number, data: { title?: string; date?: string; description?: string | null }) => api.put<Event>(`/events/${id}`, data).then(r => r.data),
  remove: (id: number) => api.delete(`/events/${id}`),
}

export const surveyApi = {
  list: () => api.get<SurveySummary[]>('/surveys').then(r => r.data),
  get: (id: number) => api.get<SurveyDetail>(`/surveys/${id}`).then(r => r.data),
  create: (data: { title: string; deadline: string; staffIds: number[] }) => api.post<SurveyDetail>('/surveys', data).then(r => r.data),
  update: (id: number, data: { title: string }) => api.put<SurveyDetail>(`/surveys/${id}`, data).then(r => r.data),
  close: (id: number) => api.post<SurveyDetail>(`/surveys/${id}/close`).then(r => r.data),
  reopen: (id: number) => api.post<SurveyDetail>(`/surveys/${id}/reopen`).then(r => r.data),
  remove: (id: number) => api.delete(`/surveys/${id}`),
  matrix: (id: number) => api.get<MatrixData>(`/surveys/${id}/matrix`).then(r => r.data),
}

export const assignmentApi = {
  create: (data: { staffId: number; eventId: number; skillId: number }) => api.post('/assignments', data).then(r => r.data),
  remove: (id: number) => api.delete(`/assignments/${id}`),
}

export const surveyPublicApi = {
  get: (token: string) => api.get(`/survey/${token}`).then(r => r.data),
  respond: (token: string, data: { responses: Record<number, boolean>; remark?: string }) =>
    api.post(`/survey/${token}`, data).then(r => r.data),
}