import type {
  ApiErrorBody,
  BriefResponse,
  Channel,
  ContentIdea,
  HandoffResponse,
  WeeklyPlan,
} from './types'

const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '')

export class ApiError extends Error {
  status: number
  body: ApiErrorBody

  constructor(message: string, status: number, body: ApiErrorBody = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  let response: Response
  try {
    const headers = new Headers(options.headers)
    if (!headers.has('Accept')) headers.set('Accept', 'application/ld+json, application/json')
    if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json')
    response = await fetch(API_BASE + path, {
      ...options,
      headers,
    })
  } catch {
    throw new ApiError('Cannot reach the Symfony API. Check that Docker services are running.', 0)
  }

  const text = await response.text()
  let body: unknown = {}
  if (text) {
    try {
      body = JSON.parse(text)
    } catch {
      body = { error: text }
    }
  }

  if (!response.ok) {
    const errorBody = body as ApiErrorBody
    throw new ApiError(
      errorBody.error || 'The API request failed.',
      response.status,
      errorBody,
    )
  }

  return body as T
}

function normalizeIdea(item: Record<string, unknown>): ContentIdea {
  return {
    id: String(item.id ?? ''),
    title: String(item.title ?? ''),
    topic: String(item.topic ?? ''),
    targetAudience: String(item.targetAudience ?? item.target_audience ?? ''),
    tone: String(item.tone ?? ''),
    channels: Array.isArray(item.channels) ? item.channels as Channel[] : [],
    status: String(item.status ?? 'draft') as ContentIdea['status'],
    createdAt: typeof item.createdAt === 'string' ? item.createdAt : undefined,
    updatedAt: typeof item.updatedAt === 'string' ? item.updatedAt : undefined,
  }
}

export const api = {
  async listIdeas(): Promise<ContentIdea[]> {
    const result = await request<Record<string, unknown>>('/content_ideas')
    const members = (result['hydra:member'] ?? result.member ?? result.items ?? []) as Record<string, unknown>[]
    return members.map(normalizeIdea)
  },

  async createIdea(payload: {
    title: string
    topic: string
    targetAudience: string
    tone: string
    channels: Channel[]
  }): Promise<ContentIdea> {
    const result = await request<Record<string, unknown>>('/content_ideas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/ld+json' },
      body: JSON.stringify(payload),
    })
    return normalizeIdea(result)
  },

  createWeeklyPlan(payload: {
    weekStart: string
    capacityPersonA: number
    capacityPersonB: number
    slots: Array<{
      ideaId: string
      type: 'priority' | 'reserve'
      assignee: 'A' | 'B'
      estimatedMinutes: number
    }>
  }): Promise<WeeklyPlan> {
    return request('/workflow/weekly-plans', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  getWeeklyPlan(id: string): Promise<WeeklyPlan> {
    return request('/workflow/weekly-plans/' + encodeURIComponent(id))
  },

  generateBrief(id: string, payload: { mediaReference: string; scheduledAt: string }): Promise<BriefResponse> {
    return request('/workflow/content-ideas/' + encodeURIComponent(id) + '/brief', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  getBrief(id: string): Promise<BriefResponse> {
    return request('/workflow/content-ideas/' + encodeURIComponent(id) + '/brief')
  },

  review(id: string): Promise<{ ideaId: string; status: string }> {
    return request('/workflow/content-ideas/' + encodeURIComponent(id) + '/review', { method: 'POST' })
  },

  approve(id: string): Promise<{ ideaId: string; status: string; approvedVersionHash: string }> {
    return request('/workflow/content-ideas/' + encodeURIComponent(id) + '/approve', { method: 'POST' })
  },

  handoff(id: string, idempotencyKey: string): Promise<HandoffResponse> {
    return request('/workflow/content-ideas/' + encodeURIComponent(id) + '/handoff', {
      method: 'POST',
      headers: { 'Idempotency-Key': idempotencyKey },
    })
  },

  editIdea(id: string, payload: Partial<Pick<ContentIdea, 'title' | 'topic' | 'targetAudience' | 'tone' | 'channels'>>): Promise<{ ideaId: string; status: string }> {
    return request('/workflow/content-ideas/' + encodeURIComponent(id), {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json' },
      body: JSON.stringify(payload),
    })
  },
}
