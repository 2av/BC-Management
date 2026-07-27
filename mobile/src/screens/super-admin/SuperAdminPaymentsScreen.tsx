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
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

type Payment = {
  id: number
  clientId: number
  clientName: string
  subscriptionId: number
  amount: number
  currency: string
  paymentMethod: string | null
  paymentReference: string | null
  paymentStatus: string
  paymentDate: string | null
  createdAt: string
}

export function SuperAdminPaymentsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [status, setStatus] = useState<'pending' | 'completed' | 'all'>('pending')
  const [items, setItems] = useState<Payment[]>([])
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const q = status === 'all' ? '' : `?status=${status}`
      const next = await apiFetch<Payment[]>(`/api/super-admin/payments${q}`, {}, user.accessToken)
      setItems(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load payments')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, status])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  async function markComplete(id: number) {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(`/api/super-admin/payments/${id}/complete`, { method: 'PATCH' }, user.accessToken)
      setMessage('Payment marked complete.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Update failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Platform payments" subtitle="Subscription fees" onBack={() => navigation.goBack()} />
      <View style={styles.tabs}>
        {(['pending', 'completed', 'all'] as const).map((s) => (
          <Pressable
            key={s}
            style={[styles.tab, status === s && styles.tabOn]}
            onPress={() => setStatus(s)}
          >
            <Text style={[styles.tabText, status === s && styles.tabTextOn]}>{s}</Text>
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
          {items.length === 0 ? <Text style={styles.muted}>No payments.</Text> : null}
          {items.map((p) => (
            <SoftCard key={p.id}>
              <Text style={styles.name}>{p.clientName}</Text>
              <Text style={styles.muted}>
                {formatInr(p.amount)} · {p.paymentStatus}
                {p.paymentMethod ? ` · ${p.paymentMethod}` : ''}
              </Text>
              {p.paymentStatus !== 'completed' ? (
                <Pressable style={styles.btn} disabled={saving} onPress={() => void markComplete(p.id)}>
                  <Text style={styles.btnText}>Mark complete</Text>
                </Pressable>
              ) : null}
            </SoftCard>
          ))}
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
  tabText: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted, textTransform: 'capitalize' },
  tabTextOn: { color: COLORS.teal, fontFamily: FONTS.bodyBold },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
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
