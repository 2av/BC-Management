import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useFocusEffect } from '@react-navigation/native'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'

type MemberItem = {
  id: number
  memberName: string
  username: string | null
  phone: string | null
  email: string | null
  address: string | null
  status: string
}

export function AdminMembersScreen() {
  const { user } = useAuth()
  const [members, setMembers] = useState<MemberItem[]>([])
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [editing, setEditing] = useState<MemberItem | null>(null)
  const [form, setForm] = useState({
    memberName: '',
    username: '',
    phone: '',
    email: '',
    address: '',
    status: 'active',
    newPassword: '',
    confirmPassword: '',
  })
  const [showPwd, setShowPwd] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<MemberItem[]>('/api/members', {}, user.accessToken)
      setMembers(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load members')
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

  function openEdit(m: MemberItem) {
    setEditing(m)
    setMessage(null)
    setError(null)
    setForm({
      memberName: m.memberName,
      username: m.username ?? '',
      phone: m.phone ?? '',
      email: m.email ?? '',
      address: m.address ?? '',
      status: m.status,
      newPassword: '',
      confirmPassword: '',
    })
  }

  async function save() {
    if (!user?.accessToken || !editing) return
    if (form.newPassword || form.confirmPassword) {
      if (form.newPassword.length < 6) {
        setError('New password must be at least 6 characters.')
        return
      }
      if (form.newPassword !== form.confirmPassword) {
        setError('Passwords do not match.')
        return
      }
    }
    setSaving(true)
    setError(null)
    try {
      await apiFetch(`/api/members/${editing.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          memberName: form.memberName,
          username: form.username || null,
          phone: form.phone || null,
          email: form.email || null,
          address: form.address || null,
          status: form.status,
          newPassword: form.newPassword.trim() || null,
        }),
      }, user.accessToken)
      setMessage(form.newPassword ? 'Member updated · password reset.' : 'Member updated.')
      setEditing(null)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Members" subtitle="Edit profile · reset password" />
      {loading && members.length === 0 ? (
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
          {error && !editing ? <ErrorBanner message={error} /> : null}
          {members.map((m) => (
            <SoftCard key={m.id} style={styles.card}>
              <Text style={styles.name}>{m.memberName}</Text>
              <Text style={styles.muted}>
                @{m.username ?? '—'} · {m.phone ?? 'no phone'} · {m.status}
              </Text>
              <Pressable style={styles.editBtn} onPress={() => openEdit(m)}>
                <Text style={styles.editBtnText}>Edit</Text>
              </Pressable>
            </SoftCard>
          ))}
        </ScrollView>
      )}

      <Modal visible={!!editing} animationType="slide" transparent>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Edit {editing?.memberName}</Text>
            {error ? <Text style={styles.err}>{error}</Text> : null}
            <Field label="Name" value={form.memberName} onChange={(v) => setForm({ ...form, memberName: v })} />
            <Field label="Username" value={form.username} onChange={(v) => setForm({ ...form, username: v })} />
            <Field label="Phone" value={form.phone} onChange={(v) => setForm({ ...form, phone: v })} />
            <Field label="Email" value={form.email} onChange={(v) => setForm({ ...form, email: v })} />
            <Field label="Address" value={form.address} onChange={(v) => setForm({ ...form, address: v })} />
            <Text style={styles.label}>Reset password (optional)</Text>
            <View style={styles.pwdWrap}>
              <TextInput
                style={[styles.input, { flex: 1 }]}
                secureTextEntry={!showPwd}
                value={form.newPassword}
                onChangeText={(v) => setForm({ ...form, newPassword: v })}
                placeholder="New password"
                placeholderTextColor={COLORS.muted}
              />
              <Pressable onPress={() => setShowPwd((v) => !v)} hitSlop={8}>
                <Ionicons name={showPwd ? 'eye-off-outline' : 'eye-outline'} size={20} color={COLORS.muted} />
              </Pressable>
            </View>
            <TextInput
              style={styles.input}
              secureTextEntry={!showPwd}
              value={form.confirmPassword}
              onChangeText={(v) => setForm({ ...form, confirmPassword: v })}
              placeholder="Confirm password"
              placeholderTextColor={COLORS.muted}
            />
            <View style={styles.modalActions}>
              <Pressable style={styles.cancel} onPress={() => setEditing(null)}>
                <Text style={styles.cancelText}>Cancel</Text>
              </Pressable>
              <Pressable style={styles.save} onPress={() => void save()} disabled={saving}>
                {saving ? <ActivityIndicator color={COLORS.white} /> : <Text style={styles.saveText}>Save</Text>}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  )
}

function Field({
  label,
  value,
  onChange,
}: {
  label: string
  value: string
  onChange: (v: string) => void
}) {
  return (
    <>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={styles.input}
        value={value}
        onChangeText={onChange}
        placeholderTextColor={COLORS.muted}
      />
    </>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  card: { marginBottom: 0 },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  editBtn: {
    marginTop: 10,
    alignSelf: 'flex-start',
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  editBtnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed, marginBottom: 4 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  modalCard: {
    backgroundColor: COLORS.white,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 18,
    maxHeight: '92%',
  },
  modalTitle: { fontFamily: FONTS.bodyBold, fontSize: 18, color: COLORS.text, marginBottom: 8 },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginTop: 4,
    color: COLORS.text,
    backgroundColor: COLORS.sand,
  },
  pwdWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingRight: 12,
    marginTop: 4,
    backgroundColor: COLORS.sand,
  },
  modalActions: { flexDirection: 'row', gap: 10, marginTop: 16 },
  cancel: {
    flex: 1,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  cancelText: { fontFamily: FONTS.bodyMed, color: COLORS.text },
  save: {
    flex: 1,
    backgroundColor: COLORS.teal,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  saveText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  err: { color: COLORS.danger, marginBottom: 6 },
})
