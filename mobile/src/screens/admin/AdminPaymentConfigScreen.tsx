import { useCallback, useEffect, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader } from '../../components/ui'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

type Config = {
  upiId: string
  bankAccountName: string
  paymentNote: string
  qrEnabled: boolean
}

export function AdminPaymentConfigScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [form, setForm] = useState<Config>({
    upiId: '',
    bankAccountName: '',
    paymentNote: '',
    qrEnabled: true,
  })

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<Config>('/api/settings/payment-config', {}, user.accessToken)
      setForm(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken])

  useEffect(() => {
    void load()
  }, [load])

  async function save() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        '/api/settings/payment-config',
        { method: 'PUT', body: JSON.stringify(form) },
        user.accessToken,
      )
      setMessage('Payment configuration saved.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Payment config"
        subtitle="UPI / QR for members"
        onBack={() => navigation.goBack()}
      />
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <ErrorBanner message={error} /> : null}
          {message ? <Text style={styles.ok}>{message}</Text> : null}

          <Text style={styles.label}>UPI ID</Text>
          <TextInput
            style={styles.input}
            value={form.upiId}
            onChangeText={(upiId) => setForm((f) => ({ ...f, upiId }))}
            autoCapitalize="none"
            placeholder="name@upi"
            placeholderTextColor={COLORS.muted}
          />

          <Text style={styles.label}>Payee name</Text>
          <TextInput
            style={styles.input}
            value={form.bankAccountName}
            onChangeText={(bankAccountName) => setForm((f) => ({ ...f, bankAccountName }))}
          />

          <Text style={styles.label}>Payment note</Text>
          <TextInput
            style={styles.input}
            value={form.paymentNote}
            onChangeText={(paymentNote) => setForm((f) => ({ ...f, paymentNote }))}
          />

          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Enable QR payments</Text>
            <Switch
              value={form.qrEnabled}
              onValueChange={(qrEnabled) => setForm((f) => ({ ...f, qrEnabled }))}
              trackColor={{ true: COLORS.teal, false: COLORS.border }}
            />
          </View>

          <Pressable style={styles.btn} disabled={saving} onPress={() => void save()}>
            {saving ? (
              <ActivityIndicator color={COLORS.white} />
            ) : (
              <Text style={styles.btnText}>Save configuration</Text>
            )}
          </Pressable>
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 6 },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  switchRow: {
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  switchLabel: { fontFamily: FONTS.bodyMed, color: COLORS.text },
  btn: {
    marginTop: 20,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
