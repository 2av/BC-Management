import { useCallback, useMemo, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native'
import { StatusBar } from 'expo-status-bar'
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { COLORS } from '../theme'
import {
  formatInr,
  type GroupLedger,
  type RootStackParamList,
} from '../types'
type Nav = NativeStackNavigationProp<RootStackParamList, 'GroupLedger'>
type Route = RouteProp<RootStackParamList, 'GroupLedger'>
export function GroupLedgerScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const { groupId } = route.params
  const [data, setData] = useState<GroupLedger | null>(null)
  const [canSpin, setCanSpin] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [tab, setTab] = useState<'months' | 'members'>('months')
  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<GroupLedger>(
        `/api/groups/${groupId}/ledger`,
        {},
        user.accessToken,
      )
      setData(next)
      try {
        const avail = await apiFetch<{ canCustomPick: boolean }>(
          `/api/groups/${groupId}/random-picks/available-members`,
          {},
          user.accessToken,
        )
        setCanSpin(Boolean(avail.canCustomPick))
      } catch {
        setCanSpin(false)
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load ledger')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, groupId])
  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )
  const myRow = useMemo(
    () => data?.members.find((m) => m.memberId === user?.id) ?? null,
    [data, user?.id],
  )
  const months = useMemo(() => {
    if (!data) return [] as number[]
    const fromBids = data.monthlyBids.map((b) => b.monthNumber)
    if (fromBids.length > 0) return fromBids
    return Array.from({ length: data.totalMembers }, (_, i) => i + 1)
  }, [data])
  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
          <Text style={styles.back}>← Back</Text>
        </Pressable>
        <Text style={styles.title}>{data?.groupName ?? 'Group ledger'}</Text>
        <Text style={styles.sub}>
          {data
            ? `${data.totalMembers} members · ${formatInr(data.monthlyContribution)} / month`
            : 'Loading…'}
        </Text>
      </View>
      {loading && !data ? (
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
          {error ? <Text style={styles.error}>{error}</Text> : null}
          {data ? (
            <>
              <View style={styles.statsRow}>
                <View style={styles.stat}>
                  <Text style={styles.statLabel}>Collection</Text>
                  <Text style={styles.statValue}>
                    {formatInr(data.monthlyContribution * data.totalMembers)}
                  </Text>
                </View>
                <View style={[styles.stat, styles.statAccent]}>
                  <Text style={styles.statLabel}>Status</Text>
                  <Text style={[styles.statValue, { color: COLORS.teal }]}>
                    {data.status}
                  </Text>
                </View>
              </View>
              {data.organiserName ? (
                <Text style={styles.meta}>Organiser: {data.organiserName}</Text>
              ) : null}
              {data.month1Allocated === false ? (
                <Text style={styles.warn}>
                  Month 1 pot is not on the ledger yet
                  {data.organiserName ? ` (organiser: ${data.organiserName})` : ''}.
                </Text>
              ) : null}
              <Pressable
                style={styles.bidBtn}
                onPress={() => navigation.navigate('Invoice', { groupId, memberId: user?.id })}
              >
                <Text style={styles.bidBtnText}>Invoice</Text>
              </Pressable>
              <View style={styles.actionRow}>
                {canSpin ? (
                  <Pressable
                    style={[styles.secondaryBtn, { flex: 1 }]}
                    onPress={() => navigation.navigate('RandomPicks', { groupId })}
                  >
                    <Text style={styles.secondaryBtnText}>Random pick</Text>
                  </Pressable>
                ) : null}
              </View>
              {myRow ? (
                <View style={styles.myCard}>
                  <Text style={styles.myTitle}>Your summary</Text>
                  <Text style={styles.muted}>
                    #{myRow.memberNumber} · {myRow.memberName}
                  </Text>
                  <View style={styles.myStats}>
                    <MiniStat label="Paid" value={formatInr(myRow.totalPaid)} />
                    <MiniStat label="Received" value={formatInr(myRow.givenAmount)} />
                    <MiniStat label="Profit" value={formatInr(myRow.profit)} />
                  </View>
                  <Text style={[styles.section, { marginTop: 14 }]}>Your months</Text>
                  {months.map((m) => {
                    const paid = myRow.paymentsByMonth[String(m)]
                    const bid = data.monthlyBids.find((b) => b.monthNumber === m)
                    const isWinner =
                      bid?.takenByMemberId === user?.id ||
                      bid?.takenByGroupMemberId === myRow.groupMemberId
                    return (
                      <View key={m} style={styles.monthRow}>
                        <View style={{ flex: 1 }}>
                          <Text style={styles.monthLabel}>
                            Month {m}
                            {isWinner ? ' · Winner' : ''}
                          </Text>
                          <Text style={styles.muted}>
                            {bid?.takenByMemberName
                              ? `Pot: ${bid.takenByMemberName}`
                              : 'No winner yet'}
                          </Text>
                        </View>
                        <View style={{ alignItems: 'flex-end', gap: 6 }}>
                          <Text style={styles.amount}>
                            {paid != null ? formatInr(paid) : '—'}
                          </Text>
                          {paid == null && bid ? (
                            <Pressable
                              style={styles.paySmall}
                              onPress={() =>
                                navigation.navigate('PayDetail', { groupId, month: m })
                              }
                            >
                              <Text style={styles.paySmallText}>Pay</Text>
                            </Pressable>
                          ) : null}
                        </View>
                      </View>
                    )
                  })}
                </View>
              ) : null}
              <View style={styles.filters}>
                <Chip
                  label="Winners"
                  active={tab === 'months'}
                  onPress={() => setTab('months')}
                />
                <Chip
                  label="All members"
                  active={tab === 'members'}
                  onPress={() => setTab('members')}
                />
              </View>
              {tab === 'months' ? (
                data.monthlyBids.length === 0 ? (
                  <Text style={styles.muted}>No monthly bids recorded yet.</Text>
                ) : (
                  data.monthlyBids.map((b) => (
                    <View key={b.monthNumber} style={styles.card}>
                      <View style={styles.cardTop}>
                        <Text style={styles.groupName}>Month {b.monthNumber}</Text>
                        <Text style={styles.amount}>{formatInr(b.netPayable)}</Text>
                      </View>
                      <Text style={styles.muted}>
                        Taken by: {b.takenByMemberName ?? '—'}
                        {b.isBid ? ' · Bid' : ' · No bid'}
                      </Text>
                      <Text style={styles.muted}>
                        Bid {formatInr(b.bidAmount)} · Gain/member{' '}
                        {formatInr(b.gainPerMember)}
                      </Text>
                      <Text style={styles.muted}>
                        Date:{' '}
                        {b.paymentDate
                          ? new Date(b.paymentDate).toLocaleDateString('en-IN')
                          : '—'}
                      </Text>
                    </View>
                  ))
                )
              ) : (
                data.members.map((m) => {
                  const mine = m.memberId === user?.id
                  return (
                    <View
                      key={m.groupMemberId}
                      style={[styles.card, mine && styles.cardMine]}
                    >
                      <Text style={styles.groupName}>
                        #{m.memberNumber} {m.memberName}
                        {mine ? ' (You)' : ''}
                      </Text>
                      <Text style={styles.muted}>
                        Paid {formatInr(m.totalPaid)} · Received{' '}
                        {formatInr(m.givenAmount)} · Profit {formatInr(m.profit)}
                      </Text>
                    </View>
                  )
                })
              )}
            </>
          ) : null}
        </ScrollView>
      )}
    </View>
  )
}
function MiniStat({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.miniStat}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={styles.miniValue}>{value}</Text>
    </View>
  )
}
function Chip({
  label,
  active,
  onPress,
}: {
  label: string
  active: boolean
  onPress: () => void
}) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, active && styles.chipActive]}>
      <Text style={[styles.chipText, active && styles.chipTextActive]}>{label}</Text>
    </Pressable>
  )
}
const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  header: {
    paddingTop: 56,
    paddingHorizontal: 20,
    paddingBottom: 14,
    backgroundColor: COLORS.white,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  back: { color: COLORS.teal, fontWeight: '600', marginBottom: 8 },
  title: { fontSize: 20, fontWeight: '700', color: COLORS.text },
  sub: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
  content: { padding: 16, paddingBottom: 40 },
  error: {
    backgroundColor: '#FEF2F2',
    color: COLORS.danger,
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
  },
  warn: {
    backgroundColor: '#FFFBEB',
    color: '#92400E',
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
    fontSize: 13,
    lineHeight: 18,
  },
  meta: { fontSize: 13, color: COLORS.muted, marginBottom: 10 },
  bidBtn: {
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
    marginBottom: 14,
  },
  bidBtnText: { color: COLORS.white, fontWeight: '700', fontSize: 14 },
  actionRow: { flexDirection: 'row', gap: 8, marginBottom: 14 },
  secondaryBtn: {
    flex: 1,
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
  },
  secondaryBtnText: { color: COLORS.teal, fontWeight: '700', fontSize: 13 },
  statsRow: { flexDirection: 'row', gap: 10, marginBottom: 12 },
  stat: {
    flex: 1,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  statAccent: { borderColor: COLORS.tealSoft, backgroundColor: '#F0FDFA' },
  statLabel: { fontSize: 12, color: COLORS.muted, marginBottom: 4 },
  statValue: { fontSize: 16, fontWeight: '700', color: COLORS.text },
  myCard: {
    backgroundColor: COLORS.white,
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.tealSoft,
    marginBottom: 14,
  },
  myTitle: { fontSize: 16, fontWeight: '800', color: COLORS.teal, marginBottom: 2 },
  myStats: { flexDirection: 'row', gap: 8, marginTop: 12 },
  miniStat: {
    flex: 1,
    backgroundColor: COLORS.bg,
    borderRadius: 10,
    padding: 10,
  },
  miniValue: { fontSize: 13, fontWeight: '700', color: COLORS.text },
  section: { fontSize: 14, fontWeight: '700', color: COLORS.text, marginBottom: 8 },
  monthRow: {
    flexDirection: 'row',
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    gap: 10,
  },
  monthLabel: { fontSize: 14, fontWeight: '700', color: COLORS.text },
  amount: { fontSize: 14, fontWeight: '700', color: COLORS.text },
  paySmall: {
    backgroundColor: COLORS.teal,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  paySmallText: { color: COLORS.white, fontWeight: '700', fontSize: 12 },
  filters: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
  },
  chipActive: { backgroundColor: COLORS.teal, borderColor: COLORS.teal },
  chipText: { fontSize: 13, fontWeight: '600', color: COLORS.muted },
  chipTextActive: { color: COLORS.white },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardMine: { borderColor: COLORS.teal, backgroundColor: '#F0FDFA' },
  cardTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  groupName: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 2 },
  muted: { fontSize: 13, color: COLORS.muted, lineHeight: 18 },
})
