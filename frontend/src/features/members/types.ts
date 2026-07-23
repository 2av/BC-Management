export type MemberGroupBrief = {
  groupMemberId: number
  groupId: number
  groupName: string
  memberNumber: number
  handLabel: string | null
  status: string
  joinedDate: string
}

export type MemberListItem = {
  id: number
  memberName: string
  username: string | null
  phone: string | null
  email: string | null
  address: string | null
  status: string
  groupCount: number
  groups: MemberGroupBrief[]
}

export type ImportMembersResult = {
  imported: number
  skipped: number
  errors: string[]
}

export type GroupMemberRosterItem = {
  groupMemberId: number
  memberId: number
  memberName: string
  username: string | null
  phone: string | null
  memberNumber: number
  handLabel: string | null
  status: string
  joinedDate: string
}
