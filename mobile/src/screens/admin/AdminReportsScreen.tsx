import { useCallback, useMemo, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import * as Clipboard from 'expo-clipboard'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { API_BASE } from '../../config'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>
type Tab = 'overview' | 'payments' | 'bids'

type Overview = {
  totalGroups: number
  totalMembers: number
  totalCollected: number
  totalPending: number
  groups: {
    groupId: number
    groupName: string
    totalMembers: number
    monthlyContribution: number
    totalCollected: number
    paidCount: number
    pendingCount: number
    pendingAmount: number
    bidCount: number
    totalDistributed: number
  }[]
}

type PaymentRow = {
  groupName: string
  memberName: string
  memberNumber: number
  monthNumber: number
  paymentAmount: number
  paymentStatus: string
  paymentDate: string | null
  winnerName: string | null
}

type BidRow = {
  groupName: string
  monthNumber: number
  winnerName: string | null
  bidAmount: number
  netPayable: number
  gainPerMember: number
  paymentDate: string | null
}

type GroupItem = { id: number; groupName: string }

export function AdminReportsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [tab, setTab] = useState<Tab>('overview')
  const [groupId, setGroupId] = useState('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [groups, setGroups] = useState<GroupItem[]>([])
  const [overview, setOverview] = useState<Overview | null>(null)
  const [payments, setPayments] = useState<PaymentRow[]>([])
  const [bids, setBids] = useState<BidRow[]>([])
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [exporting, setExporting] = useState(false)

  const paymentsPath = useMemo(() => {
    const params = new URLSearchParams()
    if (groupId !== 'all') params.set('groupId', groupId)
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    const q = params.toString()
    return `/api/reports/payments${q ? `?${q}` : ''}`
  }, [groupId, from, to])

  const bidsPath = groupId === 'all' ? '/api/reports/bids' : `/api/reports/bids?groupId=${groupId}`

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const groupList = await apiFetch<GroupItem[]>('/api/groups', {}, user.accessToken)
      setGroups(groupList)

      if (tab === 'overview') {
        const next = await apiFetch<Overview>('/api/reports/overview', {}, user.accessToken)
        setOverview(next)
      } else if (tab === 'payments') {
        const next = await apiFetch<PaymentRow[]>(paymentsPath, {}, user.accessToken)
        setPayments(next)
      } else {
        const next = await apiFetch<BidRow[]>(bidsPath, {}, user.accessToken)
        setBids(next)
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load reports')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, tab, paymentsPath, bidsPath])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  async function exportCsv(type: 'payments' | 'bids' | 'groups' | 'members') {
    if (!user?.accessToken) return
    setExporting(true)
    setError(null)
    setMessage(null)
    try {
      const params = new URLSearchParams()
      if (groupId !== 'all') params.set('groupId', groupId)
      if (from) params.set('from', from)
      if (to) params.set('to', to)
      const q = params.toString()
      const path = `/api/reports/export/${type}${q ? `?${q}` : ''}`
      const res = await fetch(`${API_BASE}${path}`, {
        headers: { Authorization: `Bearer ${user.accessToken}` },
      })
      if (!res.ok) {
        const body = (await res.json().catch(() => ({}))) as { message?: string }
        throw new Error(body.message ?? `Export failed (${res.status})`)
      }
      const csv = await res.text()
      await Clipboard.setStringAsync(csv)
      await Share.share({
        message: csv,
        title: `${type}_export.csv`,
      })
      setMessage(`${type} CSV copied & ready to share.`)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Export failed')
    } finally {
      setExporting(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Reports" subtitle="Overview · payments · bids" onBack={() => navigation.goBack()} />
      <View style={styles.tabs}>
        {(['overview', 'payments', 'bids'] as Tab[]).map((t) => (
          <Pressable
            key={t}
            style={[styles.tab, tab === t && styles.tabOn]}
            onPress={() => {
              setTab(t)
              setLoading(true)
            }}
          >
            <Text style={[styles.tabText, tab === t && styles.tabTextOn]}>
              {t === 'overview' ? 'Overview' : t === 'payments' ? 'Payments' : 'Bids'}
            </Text>
          </Pressable>
        ))}
      </View>

      {tab !== 'overview' ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filters}>
          <Pressable
            style={[styles.chip, groupId === 'all' && styles.chipOn]}
            onPress={() => setGroupId('all')}
          >
            <Text style={[styles.chipText, groupId === 'all' && styles.chipTextOn]}>All groups</Text>
          </Pressable>
          {groups.map((g) => (
            <Pressable
              key={g.id}
              style={[styles.chip, groupId === String(g.id) && styles.chipOn]}
              onPress={() => setGroupId(String(g.id))}
            >
              <Text style={[styles.chipText, groupId === String(g.id) && styles.chipTextOn]}>
                {g.groupName}
              </Text>
            </Pressable>
          ))}
        </ScrollView>
      ) : null}

      {tab === 'payments' ? (
        <View style={styles.dateRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.dateLabel}>From</Text>
            <TextInput
              style={styles.dateInput}
              value={from}
              onChangeText={setFrom}
              placeholder="YYYY-MM-DD"
              placeholderTextColor={COLORS.muted}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.dateLabel}>To</Text>
            <TextInput
              style={styles.dateInput}
              value={to}
              onChangeText={setTo}
              placeholder="YYYY-MM-DD"
              placeholderTextColor={COLORS.muted}
            />
          </View>
        </View>
      ) : null}

      <View style={styles.exportRow}>
        {(tab === 'overview'
          ? (['groups', 'members'] as const)
          : tab === 'payments'
            ? (['payments'] as const)
            : (['bids'] as const)
        ).map((type) => (
          <Pressable
            key={type}
            style={styles.exportBtn}
            disabled={exporting}
            onPress={() => void exportCsv(type)}
          >
            <Text style={styles.exportBtnText}>
              {exporting ? 'Exporting…' : `Export ${type} CSV`}
            </Text>
          </Pressable>
        ))}
      </View>

      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true)
                void load()
              }}
              tintColor={COLORS.teal}
            />
          }
        >
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {error ? <ErrorBanner message={error} /> : null}

          {tab === 'overview' && overview ? (
            <>
              <View style={styles.stats}>
                <SoftCard style={styles.stat}>
                  <Text style={styles.statVal}>{overview.totalGroups}</Text>
                  <Text style={styles.statLabel}>Groups</Text>
                </SoftCard>
                <SoftCard style={styles.stat}>
                  <Text style={styles.statVal}>{overview.totalMembers}</Text>
                  <Text style={styles.statLabel}>Members</Text>
                </SoftCard>
                <SoftCard style={styles.stat}>
                  <Text style={styles.statVal}>{formatInr(overview.totalCollected)}</Text>
                  <Text style={styles.statLabel}>Collected</Text>
                </SoftCard>
                <SoftCard style={styles.stat}>
                  <Text style={[styles.statVal, { color: COLORS.danger }]}>
                    {formatInr(overview.totalPending)}
                  </Text>
                  <Text style={styles.statLabel}>Pending</Text>
                </SoftCard>
              </View>
              {overview.groups.map((g) => (
                <SoftCard key={g.groupId}>
                  <Text style={styles.name}>{g.groupName}</Text>
                  <Text style={styles.muted}>
                    {g.totalMembers} seats · {formatInr(g.monthlyContribution)} / mo
                  </Text>
                  <Text style={styles.meta}>
                    Collected {formatInr(g.totalCollected)} · Pending {formatInr(g.pendingAmount)} (
                    {g.pendingCount}) · Bids {g.bidCount}
                  </Text>
                </SoftCard>
              ))}
            </>
          ) : null}

          {tab === 'payments'
            ? payments.map((p, i) => (
                <SoftCard key={`${p.groupName}-${p.memberNumber}-${p.monthNumber}-${i}`}>
                  <Text style={styles.name}>
                    {p.memberName} · M{p.monthNumber}
                  </Text>
                  <Text style={styles.muted}>
                    {p.groupName} · #{p.memberNumber} · {p.paymentStatus}
                  </Text>
                  <Text style={styles.meta}>
                    {formatInr(p.paymentAmount)}
                    {p.winnerName ? ` · winner ${p.winnerName}` : ''}
                  </Text>
                </SoftCard>
              ))
            : null}

          {tab === 'bids'
            ? bids.map((b, i) => (
                <SoftCard key={`${b.groupName}-${b.monthNumber}-${i}`}>
                  <Text style={styles.name}>
                    {b.groupName} · Month {b.monthNumber}
                  </Text>
                  <Text style={styles.muted}>Winner: {b.winnerName ?? '—'}</Text>
                  <Text style={styles.meta}>
                    Bid {formatInr(b.bidAmount)} · Net {formatInr(b.netPayable)} · Gain/member{' '}
                    {formatInr(b.gainPerMember)}
                  </Text>
                </SoftCard>
              ))
            : null}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  tabs: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACE.md, paddingBottom: 8 },
  tab: {
    flex: 1,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    paddingVertical: 10,
    alignItems: 'center',
  },
  tabOn: { backgroundColor: COLORS.tealSoft, borderColor: COLORS.teal },
  tabText: { fontFamily: FONTS.bodyMed, fontSize: 13, color: COLORS.muted },
  tabTextOn: { color: COLORS.teal, fontFamily: FONTS.bodyBold },
  filters: { paddingHorizontal: SPACE.md, maxHeight: 44, marginBottom: 4 },
  chip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginRight: 8,
    backgroundColor: COLORS.white,
  },
  chipOn: { backgroundColor: COLORS.tealSoft, borderColor: COLORS.teal },
  chipText: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  chipTextOn: { color: COLORS.teal },
  dateRow: { flexDirection: 'row', gap: 8, paddingHorizontal: SPACE.md, marginBottom: 6 },
  dateLabel: { fontFamily: FONTS.bodyMed, fontSize: 11, color: COLORS.muted, marginBottom: 4 },
  dateInput: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    backgroundColor: COLORS.white,
    color: COLORS.text,
    fontSize: 13,
  },
  exportRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    paddingHorizontal: SPACE.md,
    marginBottom: 4,
  },
  exportBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  exportBtnText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.text },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  stats: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  stat: { width: '48%', marginBottom: 0, flexGrow: 1 },
  statVal: { fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  statLabel: { marginTop: 4, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  meta: { marginTop: 6, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.teal },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
