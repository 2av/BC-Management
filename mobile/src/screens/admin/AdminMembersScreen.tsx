import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
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
import { ScreenHeader, SoftCard } from '../../components/ui'
import { PasswordField } from '../../components/PasswordField'
import { showError, showSuccess } from '../../utils/alerts'

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
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    try {
      const next = await apiFetch<MemberItem[]>('/api/members', {}, user.accessToken)
      setMembers(next)
    } catch (e) {
      showError(e instanceof Error ? e.message : 'Failed to load members')
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
    if (!form.memberName.trim()) {
      showError('Member name is required.', 'Validation')
      return
    }
    if (form.newPassword || form.confirmPassword) {
      if (form.newPassword.length < 6) {
        showError('New password must be at least 6 characters.', 'Validation')
        return
      }
      if (form.newPassword !== form.confirmPassword) {
        showError('Passwords do not match.', 'Validation')
        return
      }
    }
    setSaving(true)
    try {
      await apiFetch(
        `/api/members/${editing.id}`,
        {
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
        },
        user.accessToken,
      )
      setEditing(null)
      showSuccess(
        form.newPassword ? 'Member updated and password reset.' : 'Member details saved.',
        'Saved',
      )
      await load()
    } catch (e) {
      showError(e instanceof Error ? e.message : 'Save failed')
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
          {members.map((m) => (
            <SoftCard key={m.id} style={styles.card}>
              <View style={styles.memberRow}>
                <View style={styles.avatar}>
                  <Ionicons name="person" size={18} color={COLORS.teal} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.name}>{m.memberName}</Text>
                  <Text style={styles.muted}>
                    @{m.username ?? '—'} · {m.phone ?? 'no phone'} · {m.status}
                  </Text>
                </View>
              </View>
              <Pressable style={styles.editBtn} onPress={() => openEdit(m)}>
                <Ionicons name="create-outline" size={16} color={COLORS.white} />
                <Text style={styles.editBtnText}>Edit</Text>
              </Pressable>
            </SoftCard>
          ))}
        </ScrollView>
      )}

      <Modal visible={!!editing} animationType="slide" transparent>
        <KeyboardAvoidingView
          style={styles.modalBackdrop}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
          <View style={styles.modalCard}>
            <View style={styles.modalTitleRow}>
              <Ionicons name="person-circle-outline" size={22} color={COLORS.teal} />
              <Text style={styles.modalTitle}>Edit {editing?.memberName}</Text>
            </View>
            <ScrollView
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="on-drag"
              showsVerticalScrollIndicator={false}
            >
              <Field
                label="Name"
                value={form.memberName}
                onChange={(v) => setForm({ ...form, memberName: v })}
              />
              <Field
                label="Username"
                value={form.username}
                onChange={(v) => setForm({ ...form, username: v })}
              />
              <Field
                label="Phone"
                value={form.phone}
                onChange={(v) => setForm({ ...form, phone: v })}
              />
              <Field
                label="Email"
                value={form.email}
                onChange={(v) => setForm({ ...form, email: v })}
              />
              <Field
                label="Address"
                value={form.address}
                onChange={(v) => setForm({ ...form, address: v })}
              />
              <Text style={styles.sectionHint}>Reset password (optional)</Text>
              <PasswordField
                label="New password"
                value={form.newPassword}
                onChangeText={(v) => setForm({ ...form, newPassword: v })}
                placeholder="New password"
                variant="sand"
              />
              <PasswordField
                label="Confirm password"
                value={form.confirmPassword}
                onChangeText={(v) => setForm({ ...form, confirmPassword: v })}
                placeholder="Confirm password"
                variant="sand"
              />
            </ScrollView>
            <View style={styles.modalActions}>
              <Pressable style={styles.cancel} onPress={() => setEditing(null)}>
                <Text style={styles.cancelText}>Cancel</Text>
              </Pressable>
              <Pressable style={styles.save} onPress={() => void save()} disabled={saving}>
                {saving ? (
                  <ActivityIndicator color={COLORS.white} />
                ) : (
                  <>
                    <Ionicons name="checkmark" size={18} color={COLORS.white} />
                    <Text style={styles.saveText}>Save</Text>
                  </>
                )}
              </Pressable>
            </View>
          </View>
        </KeyboardAvoidingView>
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
  memberRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  avatar: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: COLORS.tealSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  editBtn: {
    marginTop: 10,
    alignSelf: 'flex-start',
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  editBtnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
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
  modalTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  modalTitle: { fontFamily: FONTS.bodyBold, fontSize: 18, color: COLORS.text },
  sectionHint: {
    marginTop: 12,
    marginBottom: 2,
    fontFamily: FONTS.bodyMed,
    fontSize: 13,
    color: COLORS.text,
  },
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
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 6,
  },
  saveText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
})
