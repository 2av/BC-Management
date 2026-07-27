import { useCallback, useMemo, useState } from 'react'
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useFocusEffect } from '@react-navigation/native'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import { formatInr } from '../../types'

type GroupItem = { id: number; groupName: string; totalMembers: number; monthlyContribution: number }

type PaymentRow = {
  id: number
  memberName: string
  monthNumber: number
  paymentAmount: number
  paymentStatus: string
  transactionId: string | null
}

type PaymentsPayload = {
  payments: PaymentRow[]
  monthlyContribution?: number
  monthDues?: { monthNumber: number; effectiveAmount: number }[]
}

export function AdminPaymentsScreen() {
  const { user } = useAuth()
  const [groups, setGroups] = useState<GroupItem[]>([])
  const [groupId, setGroupId] = useState<number | null>(null)
  const [payments, setPayments] = useState<PaymentRow[]>([])
  const [statusFilter, setStatusFilter] = useState<'pending' | 'all'>('pending')
  const [toolsMonth, setToolsMonth] = useState('1')
  const [dueAmount, setDueAmount] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [saving, setSaving] = useState(false)

  const activeGroup = useMemo(
    () => groups.find((g) => g.id === groupId) ?? null,
    [groups, groupId],
  )

  const monthOptions = useMemo(() => {
    const total = activeGroup?.totalMembers ?? 12
    return Array.from({ length: Math.max(total, 1) }, (_, i) => i + 1)
  }, [activeGroup])

  const loadGroups = useCallback(async () => {
    if (!user?.accessToken) return
    const list = await apiFetch<GroupItem[]>('/api/groups', {}, user.accessToken)
    setGroups(list)
    if (!groupId && list[0]) setGroupId(list[0].id)
  }, [user?.accessToken, groupId])

  const loadPayments = useCallback(async () => {
    if (!user?.accessToken || !groupId) return
    setError(null)
    try {
      const q = statusFilter === 'pending' ? '?status=pending' : ''
      const next = await apiFetch<PaymentsPayload>(
        `/api/groups/${groupId}/payments${q}`,
        {},
        user.accessToken,
      )
      setPayments(next.payments ?? [])
      setDueAmount((prev) => (prev ? prev : next.monthlyContribution != null ? String(next.monthlyContribution) : prev))
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load payments')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, groupId, statusFilter])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void (async () => {
        try {
          await loadGroups()
        } catch (e) {
          setError(e instanceof Error ? e.message : 'Failed to load')
          setLoading(false)
        }
      })()
    }, [loadGroups]),
  )

  useFocusEffect(
    useCallback(() => {
      if (!groupId) return
      setLoading(true)
      void loadPayments()
    }, [groupId, loadPayments]),
  )

  async function markPaid(id: number) {
    if (!user?.accessToken) return
    setMessage(null)
    try {
      await apiFetch(
        `/api/payments/${id}`,
        { method: 'PATCH', body: JSON.stringify({ paymentStatus: 'paid' }) },
        user.accessToken,
      )
      setMessage('Marked paid.')
      await loadPayments()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Mark paid failed')
    }
  }

  function confirmBulk() {
    Alert.alert(
      'Bulk mark paid?',
      `Mark all pending payments for month ${toolsMonth} as paid.`,
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Mark all', onPress: () => void bulkMarkPaid() },
      ],
    )
  }

  async function bulkMarkPaid() {
    if (!user?.accessToken || !groupId) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const res = await apiFetch<{ message?: string }>(
        `/api/groups/${groupId}/payments/bulk-mark-paid`,
        { method: 'POST', body: JSON.stringify({ monthNumber: Number(toolsMonth) }) },
        user.accessToken,
      )
      setMessage(res.message ?? 'Bulk mark done.')
      await loadPayments()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Bulk mark failed')
    } finally {
      setSaving(false)
    }
  }

  async function setMonthDue(clear = false) {
    if (!user?.accessToken || !groupId) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const res = await apiFetch<{ message?: string }>(
        `/api/groups/${groupId}/payments/set-month-amount`,
        {
          method: 'POST',
          body: JSON.stringify({
            monthNumber: Number(toolsMonth),
            paymentAmount: clear ? null : Number(dueAmount),
          }),
        },
        user.accessToken,
      )
      setMessage(res.message ?? 'Month due saved.')
      await loadPayments()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Set due failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Payments" subtitle="Pending · bulk · month due" />
      {loading && payments.length === 0 ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true)
                void loadPayments()
              }}
              tintColor={COLORS.teal}
            />
          }
        >
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {error ? <ErrorBanner message={error} /> : null}

          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chips}>
            {groups.map((g) => (
              <Pressable
                key={g.id}
                style={[styles.chip, groupId === g.id && styles.chipOn]}
                onPress={() => {
                  setGroupId(g.id)
                  setLoading(true)
                }}
              >
                <Text style={[styles.chipText, groupId === g.id && styles.chipTextOn]}>
                  {g.groupName}
                </Text>
              </Pressable>
            ))}
          </ScrollView>

          <View style={styles.statusRow}>
            {(['pending', 'all'] as const).map((s) => (
              <Pressable
                key={s}
                style={[styles.statusChip, statusFilter === s && styles.chipOn]}
                onPress={() => setStatusFilter(s)}
              >
                <Text style={[styles.chipText, statusFilter === s && styles.chipTextOn]}>
                  {s === 'pending' ? 'Pending only' : 'All statuses'}
                </Text>
              </Pressable>
            ))}
          </View>

          <SoftCard>
            <Text style={styles.toolsTitle}>Month tools</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.monthChips}>
              {monthOptions.map((m) => (
                <Pressable
                  key={m}
                  style={[styles.chip, Number(toolsMonth) === m && styles.chipOn]}
                  onPress={() => setToolsMonth(String(m))}
                >
                  <Text
                    style={[styles.chipText, Number(toolsMonth) === m && styles.chipTextOn]}
                  >
                    M{m}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
            <Text style={styles.label}>Due amount for month {toolsMonth}</Text>
            <TextInput
              style={styles.input}
              keyboardType="number-pad"
              value={dueAmount}
              onChangeText={setDueAmount}
              placeholder={String(activeGroup?.monthlyContribution ?? '')}
              placeholderTextColor={COLORS.muted}
            />
            <View style={styles.toolsBtns}>
              <Pressable
                style={[styles.secondaryBtn, { flex: 1 }]}
                disabled={saving}
                onPress={() => void setMonthDue(false)}
              >
                <Text style={styles.secondaryBtnText}>Set due</Text>
              </Pressable>
              <Pressable
                style={[styles.secondaryBtn, { flex: 1 }]}
                disabled={saving}
                onPress={() => void setMonthDue(true)}
              >
                <Text style={styles.secondaryBtnText}>Clear due</Text>
              </Pressable>
            </View>
            <Pressable style={styles.bulkBtn} disabled={saving} onPress={confirmBulk}>
              <Text style={styles.btnText}>Bulk mark month {toolsMonth} paid</Text>
            </Pressable>
          </SoftCard>

          {payments.length === 0 ? (
            <Text style={styles.muted}>No payments for this filter.</Text>
          ) : (
            payments.map((p) => (
              <SoftCard key={p.id} style={styles.card}>
                <Text style={styles.name}>{p.memberName}</Text>
                <Text style={styles.muted}>
                  Month {p.monthNumber} · {formatInr(p.paymentAmount)} · {p.paymentStatus}
                  {p.transactionId ? ` · UTR ${p.transactionId}` : ''}
                </Text>
                {p.paymentStatus !== 'paid' ? (
                  <Pressable style={styles.btn} onPress={() => void markPaid(p.id)}>
                    <Text style={styles.btnText}>Mark paid</Text>
                  </Pressable>
                ) : null}
              </SoftCard>
            ))
          )}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  chips: { marginBottom: 4 },
  monthChips: { marginTop: 8, marginBottom: 4 },
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
  chipText: { fontFamily: FONTS.bodyMed, color: COLORS.muted, fontSize: 12 },
  chipTextOn: { color: COLORS.teal },
  statusRow: { flexDirection: 'row', gap: 8 },
  statusChip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: COLORS.white,
  },
  toolsTitle: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  label: { marginTop: 10, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    marginTop: 4,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: COLORS.bg,
    color: COLORS.text,
  },
  toolsBtns: { flexDirection: 'row', gap: 8, marginTop: 10 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
    backgroundColor: COLORS.white,
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.text },
  bulkBtn: {
    marginTop: 10,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  card: { marginBottom: 0 },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  btn: {
    marginTop: 10,
    alignSelf: 'flex-start',
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
