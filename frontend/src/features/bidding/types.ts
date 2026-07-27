export type MonthBidding = {
  monthNumber: number
  biddingStatus: string
  biddingStartDate: string | null
  biddingEndDate: string | null
  minimumBidAmount: number
  maximumBidAmount: number
  winnerMemberId: number | null
  winnerGroupMemberId: number | null
  winnerMemberName: string | null
  winningBidAmount: number | null
  totalBids: number
  randomAmount?: number | null
  boliStartAmount?: number | null
  nextBoliAmount?: number | null
  currentBestBoliAmount?: number | null
  /** All active seats paid for this month (no pending left). */
  paymentDone?: boolean
}

export type GroupBiddingOverview = {
  groupId: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  months: MonthBidding[]
  organiserMemberId?: number | null
  organiserGroupMemberId?: number | null
  organiserName?: string | null
  month1Allocated?: boolean
  boliStepAmount?: number
}

export type BidItem = {
  id: number
  memberId: number
  groupMemberId: number | null
  memberName: string
  memberNumber: number
  handLabel: string | null
  bidAmount: number
  bidStatus: string
  bidDate: string
  boliAmount?: number | null
}

export type GroupMonthChartRow = {
  monthNumber: number
  randomAmount: number
  boliStartAmount: number | null
  perMemberIfRandom: number
  perMemberIfBoli: number | null
}

export type GroupBcChart = {
  groupId: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  boliStepAmount: number
  months: GroupMonthChartRow[]
}
