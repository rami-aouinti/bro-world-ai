export type IdeaStatus =
  | 'draft'
  | 'ready_for_planning'
  | 'review'
  | 'approved'
  | 'handed_off'

export type Channel = 'instagram' | 'facebook' | 'tiktok' | 'linkedin'
export type SlotType = 'priority' | 'reserve'
export type Assignee = 'A' | 'B'

export interface ContentIdea {
  id: string
  title: string
  topic: string
  targetAudience: string
  tone: string
  channels: Channel[]
  status: IdeaStatus
  createdAt?: string
  updatedAt?: string
}

export interface PlanSlot {
  ideaId: string
  title?: string
  type: SlotType
  assignee: Assignee
  estimatedMinutes: number
}

export interface WeeklyPlan {
  id: string
  weekStart: string
  status: string
  capacity: { A: number; B: number }
  allocatedMinutes: { A: number; B: number }
  remainingMinutes?: { A: number; B: number }
  slots: PlanSlot[]
}

export interface ChannelVariant {
  copy: string
  format: string
  cta: string
}

export interface ContentBriefPayload {
  idea: {
    id: string
    title: string
    topic: string
    audience: string
    tone: string
  }
  channels: Channel[]
  scheduledAt: string
  hook: string
  masterCopy: string
  productionPlan: {
    format: string
    steps: string[]
  }
  visual: {
    overlay: string
    titleImage: string
    mediaReference: string
  }
  bookingLink: string
  channelVariants: Record<string, ChannelVariant>
  effortMinutes: {
    research: number
    copyAndVariants: number
    production: number
    coordinationAndApproval: number
    total: number
  }
  assumptions: string[]
}

export interface BriefResponse {
  ideaId: string
  versionHash: string
  createdAt?: string
  brief: ContentBriefPayload
}

export interface HandoffResponse {
  handoffId: string
  idempotentReplay: boolean
  status: 'handed_off'
  payload: {
    contentIdeaId: string
    versionHash: string
    channels: Channel[]
    scheduledAt: string
    mode: 'simulated'
    brief: ContentBriefPayload
  }
}

export interface ApiErrorBody {
  error?: string
  capacityWarnings?: Record<string, {
    plannedMinutes: number
    capacityMinutes: number
    overByMinutes: number
  }>
  violations?: Array<{ field: string; message: string }>
}
