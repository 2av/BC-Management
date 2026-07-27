export type GroupListItem = {
  id: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  startDate: string
  status: string
  completedMonths: number
  pendingAmount: number
  organiserMemberId?: number | null
  organiserGroupMemberId?: number | null
  organiserName?: string | null
  month1PaymentDone?: boolean
}

export type DashboardPaymentItem = {
  id: number
  groupId: number
  groupName: string
  memberName: string
  memberNumber: number
  handLabel: string | null
  monthNumber: number
  paymentAmount: number
  paymentStatus: string
  transactionId: string | null
  updatedAt: string
}

export type DashboardStats = {
  totalGroups: number
  activeGroups: number
  completedGroups: number
  totalMembers: number
  totalCollected: number
  totalDistributed: number
  cashInHand: number
  thisMonthCollected: number
  recentGroups: GroupListItem[]
  recentPayments: DashboardPaymentItem[]
}

export type MonthlyBid = {
  monthNumber: number
  takenByMemberName: string | null
  takenByMemberId: number | null
  takenByGroupMemberId: number | null
  isBid: boolean
  bidAmount: number
  netPayable: number
  gainPerMember: number
  paymentDate: string | null
}

export type MemberLedgerRow = {
  groupMemberId: number
  memberId: number
  memberNumber: number
  memberName: string
  handLabel: string | null
  paymentsByMonth: Record<string, number | null>
  totalPaid: number
  givenAmount: number
  profit: number
}

export type GroupLedger = {
  id: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  startDate: string
  status: string
  monthlyBids: MonthlyBid[]
  members: MemberLedgerRow[]
  organiserMemberId?: number | null
  organiserGroupMemberId?: number | null
  organiserName?: string | null
  month1Allocated?: boolean
}

export type MemberDashboard = {
  fullName: string
  groupCount: number
  totalPaid: number
  totalReceived: number
  pendingDues: number
  groups: {
    groupMemberId: number
    groupId: number
    groupName: string
    memberNumber: number
    handLabel: string | null
    status: string
    monthlyContribution: number
    totalMembers: number
    startDate: string
    endDate: string
    completedMonths: number
    pendingMonths: number
    totalPaid: number
    givenAmount: number
    profit: number
    pendingAmount: number
    pendingPaymentCount: number
    nextPendingMonth: number | null
  }[]
}

export function formatInr(amount: number) {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
  }).format(amount)
}
