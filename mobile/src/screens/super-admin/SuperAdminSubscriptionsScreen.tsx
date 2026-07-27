import { useCallback, useState } from 'react'
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

type Client = { id: number; clientName: string }
type Plan = { id: number; planName: string; price: number; isActive: boolean }
type Sub = {
  id: number
  planId: number
  planName: string
  startDate: string
  endDate: string
  status: string
  paymentAmount: number
}

export function SuperAdminSubscriptionsScreen() {
  const { user } = useAuth()
  const [clients, setClients] = useState<Client[]>([])
  const [plans, setPlans] = useState<Plan[]>([])
  const [subs, setSubs] = useState<Sub[]>([])
  const [clientId, setClientId] = useState<number | null>(null)
  const [planId, setPlanId] = useState<number | null>(null)
  const [amount, setAmount] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [c, p, s] = await Promise.all([
        apiFetch<Client[]>('/api/super-admin/clients', {}, user.accessToken),
        apiFetch<Plan[]>('/api/super-admin/plans', {}, user.accessToken),
        apiFetch<Sub[]>(
          `/api/super-admin/subscriptions${clientId ? `?clientId=${clientId}` : ''}`,
          {},
          user.accessToken,
        ),
      ])
      setClients(c)
      setPlans(p.filter((x) => x.isActive))
      setSubs(s)
      if (!clientId && c[0]) setClientId(c[0].id)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, clientId])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  async function assign() {
    if (!user?.accessToken || !clientId || !planId) {
      setError('Pick client and plan.')
      return
    }
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        '/api/super-admin/subscriptions',
        {
          method: 'POST',
          body: JSON.stringify({
            clientId,
            planId,
            paymentAmount: Number(amount) || 0,
            paymentMethod: 'manual',
          }),
        },
        user.accessToken,
      )
      setMessage('Subscription assigned.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Assign failed')
    } finally {
      setSaving(false)
    }
  }

  async function extend(id: number) {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    try {
      await apiFetch(
        `/api/super-admin/subscriptions/${id}/extend`,
        { method: 'POST', body: JSON.stringify({ months: 3 }) },
        user.accessToken,
      )
      setMessage('Extended 3 months.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Extend failed')
    } finally {
      setSaving(false)
    }
  }

  function confirmCancel(id: number) {
    Alert.alert('Cancel subscription?', 'This will cancel the selected subscription.', [
      { text: 'Keep', style: 'cancel' },
      { text: 'Cancel sub', style: 'destructive', onPress: () => void cancel(id) },
    ])
  }

  async function cancel(id: number) {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    try {
      await apiFetch(`/api/super-admin/subscriptions/${id}/cancel`, { method: 'POST' }, user.accessToken)
      setMessage('Subscription cancelled.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Cancel failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Subscriptions" subtitle="Assign · extend · cancel" />
      {loading && subs.length === 0 ? (
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
                void load()
              }}
              tintColor={COLORS.teal}
            />
          }
        >
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {error ? <ErrorBanner message={error} /> : null}

          <SoftCard>
            <Text style={styles.formTitle}>Assign plan</Text>
            <Text style={styles.label}>Client</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {clients.map((c) => (
                <Pressable
                  key={c.id}
                  style={[styles.chip, clientId === c.id && styles.chipOn]}
                  onPress={() => setClientId(c.id)}
                >
                  <Text style={[styles.chipText, clientId === c.id && styles.chipTextOn]}>
                    {c.clientName}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
            <Text style={styles.label}>Plan</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {plans.map((p) => (
                <Pressable
                  key={p.id}
                  style={[styles.chip, planId === p.id && styles.chipOn]}
                  onPress={() => {
                    setPlanId(p.id)
                    setAmount(String(p.price))
                  }}
                >
                  <Text style={[styles.chipText, planId === p.id && styles.chipTextOn]}>
                    {p.planName}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
            <Text style={styles.label}>Amount</Text>
            <TextInput
              style={styles.input}
              keyboardType="number-pad"
              value={amount}
              onChangeText={setAmount}
            />
            <Pressable style={styles.btn} disabled={saving} onPress={() => void assign()}>
              <Text style={styles.btnText}>{saving ? 'Working…' : 'Assign'}</Text>
            </Pressable>
          </SoftCard>

          {subs.map((s) => (
            <SoftCard key={s.id}>
              <Text style={styles.name}>{s.planName}</Text>
              <Text style={styles.muted}>
                {s.startDate} → {s.endDate} · {s.status} · {formatInr(s.paymentAmount)}
              </Text>
              <View style={styles.row}>
                <Pressable style={styles.secondaryBtn} disabled={saving} onPress={() => void extend(s.id)}>
                  <Text style={styles.secondaryBtnText}>Extend 3mo</Text>
                </Pressable>
                <Pressable style={styles.dangerBtn} disabled={saving} onPress={() => confirmCancel(s.id)}>
                  <Text style={styles.dangerText}>Cancel</Text>
                </Pressable>
              </View>
            </SoftCard>
          ))}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  formTitle: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  label: { marginTop: 10, marginBottom: 6, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
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
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: COLORS.bg,
    color: COLORS.text,
  },
  btn: { marginTop: 12, backgroundColor: COLORS.teal, borderRadius: 12, paddingVertical: 12, alignItems: 'center' },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  row: { flexDirection: 'row', gap: 8, marginTop: 10 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: COLORS.white,
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.text },
  dangerBtn: {
    borderWidth: 1,
    borderColor: '#FECACA',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  dangerText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.danger },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
