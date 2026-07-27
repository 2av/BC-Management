import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

type Client = {
  id: number
  clientName: string
  companyName: string | null
  contactPerson: string
  email: string
  phone: string
  status: string
  subscriptionStatus: string | null
  subscriptionEndDate: string | null
  groupCount: number
  memberCount: number
}

export function SuperAdminClientsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [clients, setClients] = useState<Client[]>([])
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [showCreate, setShowCreate] = useState(false)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    clientName: '',
    companyName: '',
    contactPerson: '',
    email: '',
    phone: '',
    adminUsername: '',
    adminPassword: 'admin123',
  })

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<Client[]>('/api/super-admin/clients', {}, user.accessToken)
      setClients(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load clients')
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

  async function create() {
    if (!user?.accessToken) return
    if (!form.clientName.trim() || !form.contactPerson.trim() || !form.email.trim() || !form.phone.trim()) {
      setError('Name, contact, email, and phone are required.')
      return
    }
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const created = await apiFetch<{ id: number }>(
        '/api/super-admin/clients',
        { method: 'POST', body: JSON.stringify(form) },
        user.accessToken,
      )
      setShowCreate(false)
      setMessage('Client created.')
      setForm({
        clientName: '',
        companyName: '',
        contactPerson: '',
        email: '',
        phone: '',
        adminUsername: '',
        adminPassword: 'admin123',
      })
      await load()
      navigation.navigate('SaClientDetail', { clientId: created.id })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Clients"
        subtitle="Organisations on the platform"
        right={
          <Pressable style={styles.addBtn} onPress={() => setShowCreate((v) => !v)}>
            <Text style={styles.addBtnText}>{showCreate ? 'Close' : '+ New'}</Text>
          </Pressable>
        }
      />
      {loading && clients.length === 0 ? (
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

          {showCreate ? (
            <SoftCard>
              <Text style={styles.formTitle}>New client</Text>
              {(
                [
                  ['clientName', 'Client name'],
                  ['companyName', 'Company'],
                  ['contactPerson', 'Contact person'],
                  ['email', 'Email'],
                  ['phone', 'Phone'],
                  ['adminUsername', 'Admin username'],
                  ['adminPassword', 'Admin password'],
                ] as const
              ).map(([key, label]) => (
                <View key={key}>
                  <Text style={styles.label}>{label}</Text>
                  <TextInput
                    style={styles.input}
                    value={form[key]}
                    onChangeText={(v) => setForm((f) => ({ ...f, [key]: v }))}
                    autoCapitalize={key === 'email' || key === 'adminUsername' ? 'none' : 'sentences'}
                    secureTextEntry={key === 'adminPassword'}
                  />
                </View>
              ))}
              <Pressable style={styles.btn} disabled={saving} onPress={() => void create()}>
                <Text style={styles.btnText}>{saving ? 'Creating…' : 'Create client'}</Text>
              </Pressable>
            </SoftCard>
          ) : null}

          {clients.map((c) => (
            <Pressable
              key={c.id}
              onPress={() => navigation.navigate('SaClientDetail', { clientId: c.id })}
            >
              <SoftCard>
                <Text style={styles.name}>{c.clientName}</Text>
                <Text style={styles.muted}>
                  {c.contactPerson} · {c.phone} · {c.status}
                </Text>
                <Text style={styles.meta}>
                  {c.groupCount} groups · {c.memberCount} members
                  {c.subscriptionStatus ? ` · sub ${c.subscriptionStatus}` : ''}
                </Text>
              </SoftCard>
            </Pressable>
          ))}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  addBtn: {
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  addBtnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
  formTitle: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text, marginBottom: 6 },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: COLORS.bg,
    color: COLORS.text,
  },
  btn: {
    marginTop: 14,
    backgroundColor: COLORS.teal,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  name: { fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  meta: { marginTop: 6, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.teal },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
