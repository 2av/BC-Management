export type PaymentItem = {
  id: number
  groupId: number
  groupName: string
  memberId: number
  groupMemberId: number | null
  memberName: string
  memberNumber: number
  handLabel: string | null
  monthNumber: number
  paymentAmount: number
  expectedAmount: number
  paymentStatus: string
  paymentDate: string | null
  winnerName: string | null
  bidAmount: number | null
  gainPerMember: number | null
}

export type GroupPaymentsOverview = {
  groupId: number
  groupName: string
  monthlyContribution: number
  totalMonthlyCollection: number
  pendingCount: number
  pendingAmount: number
  paidCount: number
  paidAmount: number
  payments: PaymentItem[]
}

export type MemberPayments = {
  totalPending: number
  totalPaid: number
  payments: {
    id: number
    groupId: number
    groupName: string
    groupMemberId: number | null
    memberNumber: number | null
    handLabel: string | null
    monthNumber: number
    paymentAmount: number
    paymentStatus: string
    paymentDate: string | null
    winnerName: string | null
  }[]
}
