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
}

export type GroupBiddingOverview = {
  groupId: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  months: MonthBidding[]
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
}
