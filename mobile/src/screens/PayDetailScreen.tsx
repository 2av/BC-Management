import { useCallback, useEffect, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { StatusBar } from 'expo-status-bar'
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { UpiPayCard } from '../components/UpiPayCard'
import { COLORS } from '../theme'
import { PAYMENT_BRAND } from '../payments/upi'
import {
  formatInr,
  type PaymentDetail,
  type PaymentMethods,
  type RootStackParamList,
} from '../types'
type Nav = NativeStackNavigationProp<RootStackParamList, 'PayDetail'>
type Route = RouteProp<RootStackParamList, 'PayDetail'>
export function PayDetailScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const { groupId, month } = route.params
  const [detail, setDetail] = useState<PaymentDetail | null>(null)
  const [methods, setMethods] = useState<PaymentMethods | null>(null)
  const [utr, setUtr] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [d, m] = await Promise.all([
        apiFetch<PaymentDetail>(
          `/api/members/me/payments/${groupId}/${month}`,
          {},
          user.accessToken,
        ),
        apiFetch<PaymentMethods>(
          '/api/members/me/payment-methods',
          {},
          user.accessToken,
        ),
      ])
      setDetail(d)
      setMethods(m)
      setUtr(d.transactionId ?? '')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load payment')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken, groupId, month])
  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )
  useEffect(() => {
    setMessage(null)
  }, [groupId, month])
  async function submitUtr() {
    if (!user?.accessToken || utr.trim().length < 6) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const res = await apiFetch<{ message?: string }>(
        `/api/members/me/payments/${groupId}/${month}/utr`,
        {
          method: 'POST',
          body: JSON.stringify({ transactionId: utr.trim() }),
        },
        user.accessToken,
      )
      setMessage(res.message ?? 'UTR submitted. Admin will confirm your payment.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to submit UTR')
    } finally {
      setSaving(false)
    }
  }
  const paymentText =
    detail?.paymentNote ||
    (detail?.groupName ? `${PAYMENT_BRAND} - ${detail.groupName}` : PAYMENT_BRAND)
  const detailMethods: PaymentMethods | null =
    detail?.upiId && detail.qrImageUrl
      ? {
          qrEnabled: Boolean(detail.qrImageUrl && detail.upiId),
          upiId: detail.upiId,
          payeeName: detail.payeeName || PAYMENT_BRAND,
          paymentNote: detail.paymentNote || paymentText,
          qrImageUrl: detail.qrImageUrl,
          upiUrl: detail.upiUrl,
        }
      : methods
        ? { ...methods, payeeName: PAYMENT_BRAND, paymentNote: paymentText }
        : null
  const unpaid = detail && detail.paymentStatus !== 'paid'
  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
          <Text style={styles.back}>← Back</Text>
        </Pressable>
        <Text style={styles.title}>Pay — {detail?.groupName ?? '…'}</Text>
        <Text style={styles.sub}>Month {month}</Text>
      </View>
      {loading && !detail ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <Text style={styles.error}>{error}</Text> : null}
          {message ? <Text style={styles.success}>{message}</Text> : null}
          {detail ? (
            <View style={styles.summary}>
              <Text style={styles.amount}>{formatInr(detail.amount)}</Text>
              <Text style={styles.muted}>
                Winner: {detail.winnerName ?? '—'} · {detail.memberName}
              </Text>
              <View
                style={[
                  styles.pill,
                  unpaid ? styles.pillPending : styles.pillPaid,
                ]}
              >
                <Text
                  style={[
                    styles.pillText,
                    unpaid ? styles.pillTextPending : styles.pillTextPaid,
                  ]}
                >
                  {detail.paymentStatus}
                </Text>
              </View>
              {unpaid ? (
                <View style={styles.steps}>
                  <Text style={styles.step}>1. Scan QR or pay via UPI ID</Text>
                  <Text style={styles.step}>2. Use remark: {paymentText}</Text>
                  <Text style={styles.step}>3. Enter UTR below and submit</Text>
                </View>
              ) : (
                <Text style={[styles.muted, { marginTop: 8 }]}>Already marked paid.</Text>
              )}
            </View>
          ) : null}
          {unpaid ? (
            <>
              <UpiPayCard
                methods={detailMethods}
                amount={detail?.amount}
                paymentText={paymentText}
              />
              <View style={styles.utrCard}>
                <Text style={styles.utrTitle}>UTR / Transaction ID</Text>
                <Text style={styles.muted}>Paste the UPI reference after you pay.</Text>
                <TextInput
                  style={styles.input}
                  value={utr}
                  onChangeText={setUtr}
                  placeholder="e.g. 123456789012"
                  placeholderTextColor={COLORS.muted}
                  autoCapitalize="characters"
                  autoCorrect={false}
                />
                <Pressable
                  style={[styles.submitBtn, (saving || utr.trim().length < 6) && styles.submitDisabled]}
                  disabled={saving || utr.trim().length < 6}
                  onPress={() => void submitUtr()}
                >
                  <Text style={styles.submitText}>
                    {saving ? 'Submitting…' : 'Submit UTR'}
                  </Text>
                </Pressable>
              </View>
            </>
          ) : null}
        </ScrollView>
      )}
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
  success: {
    backgroundColor: '#ECFDF5',
    color: '#047857',
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
  },
  summary: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: 14,
  },
  amount: { fontSize: 28, fontWeight: '800', color: COLORS.text, marginBottom: 6 },
  muted: { fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  pill: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
    marginTop: 10,
  },
  pillPaid: { backgroundColor: '#DCFCE7' },
  pillPending: { backgroundColor: '#FEF3C7' },
  pillText: { fontSize: 12, fontWeight: '700', textTransform: 'capitalize' },
  pillTextPaid: { color: '#166534' },
  pillTextPending: { color: '#92400E' },
  steps: { marginTop: 12, gap: 4 },
  step: { fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  utrCard: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  utrTitle: { fontSize: 15, fontWeight: '700', color: COLORS.text, marginBottom: 4 },
  input: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    color: COLORS.text,
    backgroundColor: COLORS.bg,
  },
  submitBtn: {
    marginTop: 12,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 13,
    alignItems: 'center',
  },
  submitDisabled: { opacity: 0.5 },
  submitText: { color: COLORS.white, fontWeight: '700', fontSize: 15 },
})
