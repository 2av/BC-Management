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
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { COLORS, FONTS } from '../theme'
import { ScreenHeader, ErrorBanner } from '../components/ui'
import { formatInr, type MemberPayments } from '../types'

type Nav = any

export function PaymentsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [data, setData] = useState<MemberPayments | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [filter, setFilter] = useState<'pending' | 'all'>('pending')

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<MemberPayments>(
        '/api/members/me/payments',
        {},
        user.accessToken,
      )
      setData(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load payments')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  const rows = useMemo(() => {
    const list = data?.payments ?? []
    if (filter === 'pending') return list.filter((p) => p.paymentStatus === 'pending')
    return list
  }, [data, filter])

  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <ScreenHeader title="Pay dues" subtitle="UPI QR, copy ID, and submit UTR" />

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
          {error ? <ErrorBanner message={error} /> : null}

          <View style={styles.statsRow}>
            <View style={[styles.stat, styles.statAccent]}>
              <Text style={styles.statLabel}>Pending</Text>
              <Text style={[styles.statValue, { color: COLORS.teal }]}>
                {formatInr(data?.totalPending ?? 0)}
              </Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Paid</Text>
              <Text style={styles.statValue}>{formatInr(data?.totalPaid ?? 0)}</Text>
            </View>
          </View>

          <View style={styles.filters}>
            <Chip
              label="Pending"
              active={filter === 'pending'}
              onPress={() => setFilter('pending')}
            />
            <Chip label="All" active={filter === 'all'} onPress={() => setFilter('all')} />
          </View>

          {rows.length === 0 ? (
            <Text style={styles.muted}>
              {filter === 'pending' ? 'No pending dues.' : 'No payment records yet.'}
            </Text>
          ) : (
            rows.map((p) => (
              <View key={p.id} style={styles.card}>
                <View style={styles.cardTop}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.groupName}>{p.groupName}</Text>
                    <Text style={styles.muted}>
                      Month {p.monthNumber}
                      {p.memberNumber != null ? ` · #${p.memberNumber}` : ''}
                      {p.handLabel ? ` ${p.handLabel}` : ''}
                    </Text>
                    <Text style={styles.muted}>Winner: {p.winnerName ?? '—'}</Text>
                  </View>
                  <View style={{ alignItems: 'flex-end' }}>
                    <Text style={styles.amount}>{formatInr(p.paymentAmount)}</Text>
                    <StatusPill status={p.paymentStatus} />
                  </View>
                </View>
                <View style={styles.actions}>
                  {p.paymentStatus === 'pending' ? (
                    <Pressable
                      style={styles.payBtn}
                      onPress={() =>
                        navigation.navigate('PayDetail', {
                          groupId: p.groupId,
                          month: p.monthNumber,
                        })
                      }
                    >
                      <Text style={styles.payBtnText}>Pay now</Text>
                    </Pressable>
                  ) : null}
                  <Pressable
                    style={styles.ledgerBtn}
                    onPress={() =>
                      navigation.navigate('GroupLedger', { groupId: p.groupId })
                    }
                  >
                    <Text style={styles.ledgerBtnText}>Ledger</Text>
                  </Pressable>
                </View>
              </View>
            ))
          )}
        </ScrollView>
      )}
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

function StatusPill({ status }: { status: string }) {
  const paid = status === 'paid'
  return (
    <View style={[styles.pill, paid ? styles.pillPaid : styles.pillPending]}>
      <Text style={[styles.pillText, paid ? styles.pillTextPaid : styles.pillTextPending]}>
        {status}
      </Text>
    </View>
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
  title: { fontSize: 22, fontWeight: '700', color: COLORS.text },
  sub: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
  content: { padding: 16, paddingBottom: 40 },
  error: {
    backgroundColor: '#FEF2F2',
    color: COLORS.danger,
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
  },
  statsRow: { flexDirection: 'row', gap: 10, marginBottom: 14 },
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
  filters: { flexDirection: 'row', gap: 8, marginBottom: 14 },
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
  cardTop: { flexDirection: 'row', gap: 10 },
  groupName: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 2 },
  muted: { fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  amount: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 6 },
  pill: { borderRadius: 999, paddingHorizontal: 8, paddingVertical: 3 },
  pillPaid: { backgroundColor: '#DCFCE7' },
  pillPending: { backgroundColor: '#FEF3C7' },
  pillText: { fontSize: 11, fontWeight: '700', textTransform: 'capitalize' },
  pillTextPaid: { color: '#166534' },
  pillTextPending: { color: '#92400E' },
  actions: { flexDirection: 'row', gap: 8, marginTop: 12 },
  payBtn: {
    flex: 1,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
  },
  payBtnText: { color: COLORS.white, fontWeight: '700', fontSize: 14 },
  ledgerBtn: {
    flex: 1,
    borderRadius: 10,
    paddingVertical: 11,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.bg,
  },
  ledgerBtnText: { color: COLORS.teal, fontWeight: '700', fontSize: 14 },
})
