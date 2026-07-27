export type UserRole = 'SuperAdmin' | 'ClientAdmin' | 'Member'

export type AuthUser = {
  id: number
  username: string
  fullName: string
  role: UserRole
  clientId: number | null
  accessToken: string
  mustChangePassword?: boolean
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

export type MemberPaymentItem = {
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
}

export type MemberPayments = {
  totalPending: number
  totalPaid: number
  payments: MemberPaymentItem[]
}

export type PaymentMethods = {
  qrEnabled: boolean
  upiId: string
  payeeName: string
  paymentNote: string
  qrImageUrl: string | null
  upiUrl: string | null
}

export type PaymentDetail = {
  paymentId: number | null
  groupId: number
  groupName: string
  monthNumber: number
  amount: number
  memberName: string
  winnerName: string | null
  paymentStatus: string
  transactionId: string | null
  payeeName: string
  paymentNote: string
  qrEnabled: boolean
  upiId: string | null
  qrImageUrl: string | null
  upiUrl: string | null
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

export type AppNotification = {
  id: number
  title: string
  message: string
  type: string
  isRead: boolean
  readAt: string | null
  createdAt: string
}

export type NotificationCounts = {
  total: number
  unread: number
  warning: number
  danger: number
}

export type MemberProfile = {
  id: number
  memberName: string
  username: string | null
  phone: string | null
  email: string | null
  address: string | null
  status: string
  createdAt: string
}

export type RandomPick = {
  id: number
  monthNumber: number
  selectedMemberId: number
  selectedGroupMemberId: number | null
  selectedMemberName: string
  adminOverrideMemberId: number | null
  adminOverrideGroupMemberId: number | null
  adminOverrideMemberName: string | null
  effectiveMemberId: number
  effectiveGroupMemberId: number | null
  effectiveMemberName: string
  pickedByType: string
  pickedAt: string
}

export type AvailableRandomMember = {
  memberId: number
  groupMemberId: number
  memberName: string
  memberNumber: number
  handLabel: string | null
}

export type AvailableRandomMembers = {
  activeMonth: number | null
  canCustomPick: boolean
  canPlacePick: boolean
  blockReason: string | null
  members: AvailableRandomMember[]
}

export type InvoiceLine = {
  monthNumber: number
  expectedAmount: number
  paidAmount: number
  status: string
  paymentDate: string | null
}

export type MemberInvoice = {
  invoiceNumber: string
  invoiceDate: string
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  memberName: string
  memberNumber: number
  phone: string | null
  email: string | null
  lines: InvoiceLine[]
  totalPaid: number
  givenAmount: number
  profit: number
}

export type MainTabParamList = {
  Home: undefined
  Pay: undefined
  Alerts: undefined
  Profile: undefined
}

export type RootStackParamList = {
  Login: undefined
  MainTabs:
    | undefined
    | {
        screen?: keyof MainTabParamList
        params?: MainTabParamList[keyof MainTabParamList]
      }
  AdminTabs: undefined
  PayDetail: { groupId: number; month: number }
  GroupLedger: { groupId: number }
  RandomPicks: { groupId: number }
  Invoice: { groupId: number; memberId?: number }
  AdminBidding: { groupId: number }
  AdminChangePassword: undefined
  MemberForceChangePassword: undefined
  AdminCreateGroup: undefined
  AdminEditGroup: { groupId: number }
  AdminNotifications: undefined
  AdminBcChart: { groupId: number }
  AdminReports: undefined
  AdminPaymentConfig: undefined
  AdminSettings: undefined
  AdminGroupRoster: { groupId: number }
  AdminCloneGroup: { groupId: number }
  SuperAdminTabs: undefined
  SaClientDetail: { clientId: number }
  SaPayments: undefined
  SaAudit: undefined
}

export function formatInr(amount: number) {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
  }).format(amount)
}
