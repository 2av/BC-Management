import { useCallback, useState } from 'react'
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
import { formatInr, type MemberInvoice, type RootStackParamList } from '../types'

type Nav = NativeStackNavigationProp<RootStackParamList, 'Invoice'>
type Route = RouteProp<RootStackParamList, 'Invoice'>

export function InvoiceScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const groupId = route.params.groupId
  const memberId = route.params.memberId ?? user?.id ?? 0

  const [data, setData] = useState<MemberInvoice | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken || !memberId) return
    setError(null)
    try {
      const next = await apiFetch<MemberInvoice>(
        `/api/groups/${groupId}/members/${memberId}/invoice`,
        {},
        user.accessToken,
      )
      setData(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load invoice')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, groupId, memberId])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
          <Text style={styles.back}>← Back</Text>
        </Pressable>
        <Text style={styles.title}>Invoice</Text>
        <Text style={styles.sub}>{data?.invoiceNumber ?? 'Group ledger invoice'}</Text>
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
              <View style={styles.card}>
                <Text style={styles.groupName}>{data.groupName}</Text>
                <Text style={styles.muted}>
                  {formatInr(data.monthlyContribution)} / month · collection{' '}
                  {formatInr(data.monthlyContribution * data.totalMembers)}
                </Text>
                <View style={styles.divider} />
                <Text style={styles.memberName}>
                  {data.memberName} #{data.memberNumber}
                </Text>
                <Text style={styles.muted}>
                  {[data.phone, data.email].filter(Boolean).join(' · ') || 'No contact on file'}
                </Text>
                <Text style={styles.muted}>Date: {data.invoiceDate}</Text>
              </View>

              <View style={styles.card}>
                <Text style={styles.cardTitle}>Month-wise</Text>
                {data.lines.map((l) => (
                  <View key={l.monthNumber} style={styles.lineRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.lineMonth}>Month {l.monthNumber}</Text>
                      <Text style={styles.muted}>
                        Expected {formatInr(l.expectedAmount)} ·{' '}
                        {l.status.replace('_', ' ')}
                      </Text>
                    </View>
                    <Text style={styles.linePaid}>{formatInr(l.paidAmount)}</Text>
                  </View>
                ))}
                {data.lines.length === 0 ? (
                  <Text style={styles.muted}>No invoice lines yet.</Text>
                ) : null}
              </View>

              <View style={styles.totals}>
                <Total label="Total paid" value={formatInr(data.totalPaid)} />
                <Total label="Received" value={formatInr(data.givenAmount)} />
                <Total
                  label="Net profit"
                  value={formatInr(data.profit)}
                  accent={data.profit >= 0}
                  danger={data.profit < 0}
                />
              </View>
            </>
          ) : null}
        </ScrollView>
      )}
    </View>
  )
}

function Total({
  label,
  value,
  accent,
  danger,
}: {
  label: string
  value: string
  accent?: boolean
  danger?: boolean
}) {
  return (
    <View style={styles.totalCard}>
      <Text style={styles.totalLabel}>{label}</Text>
      <Text
        style={[
          styles.totalValue,
          accent && { color: '#047857' },
          danger && { color: COLORS.danger },
        ]}
      >
        {value}
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
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  groupName: { fontSize: 17, fontWeight: '800', color: COLORS.text },
  memberName: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 2 },
  muted: { fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  divider: { height: 1, backgroundColor: COLORS.border, marginVertical: 12 },
  cardTitle: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 4 },
  lineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    gap: 10,
  },
  lineMonth: { fontSize: 14, fontWeight: '700', color: COLORS.text },
  linePaid: { fontSize: 14, fontWeight: '700', color: COLORS.text },
  totals: { gap: 8 },
  totalCard: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: COLORS.border,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  totalLabel: { fontSize: 13, color: COLORS.muted, fontWeight: '600' },
  totalValue: { fontSize: 16, fontWeight: '800', color: COLORS.text },
})
