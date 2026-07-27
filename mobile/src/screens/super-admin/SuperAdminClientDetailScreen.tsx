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
import { useNavigation, useRoute } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>
type Route = RouteProp<RootStackParamList, 'SaClientDetail'>

type Detail = {
  id: number
  clientName: string
  companyName: string | null
  contactPerson: string
  email: string
  phone: string
  address: string | null
  city: string | null
  state: string | null
  country: string | null
  pincode: string | null
  status: string
  subscriptionStatus: string | null
  subscriptionEndDate: string | null
  maxGroups: number | null
  maxMembersPerGroup: number | null
  groupCount: number
  memberCount: number
  subscriptions: {
    id: number
    planName: string
    startDate: string
    endDate: string
    status: string
    paymentAmount: number
  }[]
}

export function SuperAdminClientDetailScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const clientId = route.params.clientId
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [form, setForm] = useState({
    clientName: '',
    companyName: '',
    contactPerson: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    country: '',
    pincode: '',
    maxGroups: '',
    maxMembersPerGroup: '',
    status: 'active',
  })
  const [subs, setSubs] = useState<Detail['subscriptions']>([])
  const [meta, setMeta] = useState({ groupCount: 0, memberCount: 0, subscriptionStatus: '' })

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const d = await apiFetch<Detail>(`/api/super-admin/clients/${clientId}`, {}, user.accessToken)
      setForm({
        clientName: d.clientName,
        companyName: d.companyName ?? '',
        contactPerson: d.contactPerson,
        email: d.email,
        phone: d.phone,
        address: d.address ?? '',
        city: d.city ?? '',
        state: d.state ?? '',
        country: d.country ?? '',
        pincode: d.pincode ?? '',
        maxGroups: d.maxGroups != null ? String(d.maxGroups) : '',
        maxMembersPerGroup: d.maxMembersPerGroup != null ? String(d.maxMembersPerGroup) : '',
        status: d.status,
      })
      setSubs(d.subscriptions)
      setMeta({
        groupCount: d.groupCount,
        memberCount: d.memberCount,
        subscriptionStatus: d.subscriptionStatus ?? '—',
      })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken, clientId])

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
        `/api/super-admin/clients/${clientId}`,
        {
          method: 'PUT',
          body: JSON.stringify({
            clientName: form.clientName.trim(),
            companyName: form.companyName.trim() || null,
            contactPerson: form.contactPerson.trim(),
            email: form.email.trim(),
            phone: form.phone.trim(),
            address: form.address.trim() || null,
            city: form.city.trim() || null,
            state: form.state.trim() || null,
            country: form.country.trim() || null,
            pincode: form.pincode.trim() || null,
            maxGroups: form.maxGroups ? Number(form.maxGroups) : null,
            maxMembersPerGroup: form.maxMembersPerGroup ? Number(form.maxMembersPerGroup) : null,
            status: form.status,
          }),
        },
        user.accessToken,
      )
      setMessage('Client updated.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  async function toggleStatus() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const res = await apiFetch<{ status: string }>(
        `/api/super-admin/clients/${clientId}/status`,
        { method: 'PATCH' },
        user.accessToken,
      )
      setMessage(`Status → ${res.status}`)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Toggle failed')
    } finally {
      setSaving(false)
    }
  }

  const fields: { key: keyof typeof form; label: string }[] = [
    { key: 'clientName', label: 'Client name' },
    { key: 'companyName', label: 'Company' },
    { key: 'contactPerson', label: 'Contact' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'address', label: 'Address' },
    { key: 'city', label: 'City' },
    { key: 'state', label: 'State' },
    { key: 'country', label: 'Country' },
    { key: 'pincode', label: 'Pincode' },
    { key: 'maxGroups', label: 'Max groups' },
    { key: 'maxMembersPerGroup', label: 'Max members / group' },
    { key: 'status', label: 'Status' },
  ]

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Client"
        subtitle={`#${clientId} · ${meta.groupCount} groups · ${meta.memberCount} members`}
        onBack={() => navigation.goBack()}
      />
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {error ? <ErrorBanner message={error} /> : null}
          <Text style={styles.meta}>Subscription: {meta.subscriptionStatus}</Text>

          {fields.map((f) => (
            <View key={f.key}>
              <Text style={styles.label}>{f.label}</Text>
              <TextInput
                style={styles.input}
                value={form[f.key]}
                onChangeText={(v) => setForm((prev) => ({ ...prev, [f.key]: v }))}
                autoCapitalize={f.key === 'email' ? 'none' : 'sentences'}
              />
            </View>
          ))}

          <View style={styles.row}>
            <Pressable style={[styles.secondaryBtn, { flex: 1 }]} disabled={saving} onPress={() => void toggleStatus()}>
              <Text style={styles.secondaryBtnText}>Toggle status</Text>
            </Pressable>
            <Pressable style={[styles.btn, { flex: 1, marginTop: 0 }]} disabled={saving} onPress={() => void save()}>
              <Text style={styles.btnText}>{saving ? 'Saving…' : 'Save'}</Text>
            </Pressable>
          </View>

          <Text style={styles.section}>Subscriptions</Text>
          {subs.length === 0 ? <Text style={styles.muted}>No subscriptions.</Text> : null}
          {subs.map((s) => (
            <View key={s.id} style={styles.subCard}>
              <Text style={styles.name}>{s.planName}</Text>
              <Text style={styles.muted}>
                {s.startDate} → {s.endDate} · {s.status} · {formatInr(s.paymentAmount)}
              </Text>
            </View>
          ))}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 4 },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  row: { flexDirection: 'row', gap: 8, marginTop: 14 },
  btn: {
    marginTop: 14,
    backgroundColor: COLORS.teal,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, color: COLORS.text },
  section: { marginTop: 18, fontFamily: FONTS.bodyBold, color: COLORS.teal },
  subCard: {
    marginTop: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    padding: 12,
    backgroundColor: COLORS.white,
  },
  name: { fontFamily: FONTS.bodyBold, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  meta: { fontFamily: FONTS.bodyMed, fontSize: 13, color: COLORS.teal, marginBottom: 6 },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
