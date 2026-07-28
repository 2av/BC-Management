import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { StatusBar } from 'expo-status-bar'
import { useFocusEffect } from '@react-navigation/native'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { COLORS, FONTS } from '../theme'
import { ScreenHeader } from '../components/ui'
import { PasswordField } from '../components/PasswordField'
import { KeyboardForm } from '../components/KeyboardForm'
import { showConfirm, showError, showSuccess } from '../utils/alerts'
import type { MemberProfile } from '../types'

export function ProfileScreen() {
  const { user, updateUser, logout } = useAuth()

  const [profile, setProfile] = useState<MemberProfile | null>(null)
  const [memberName, setMemberName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [address, setAddress] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [loading, setLoading] = useState(true)
  const [savingProfile, setSavingProfile] = useState(false)
  const [savingPassword, setSavingPassword] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    try {
      const next = await apiFetch<MemberProfile>(
        '/api/members/me/profile',
        {},
        user.accessToken,
      )
      setProfile(next)
      setMemberName(next.memberName)
      setPhone(next.phone ?? '')
      setEmail(next.email ?? '')
      setAddress(next.address ?? '')
    } catch (e) {
      showError(e instanceof Error ? e.message : 'Failed to load profile')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  async function saveProfile() {
    if (!user?.accessToken || !memberName.trim()) {
      showError('Full name is required.', 'Validation')
      return
    }
    setSavingProfile(true)
    try {
      const next = await apiFetch<MemberProfile>(
        '/api/members/me/profile',
        {
          method: 'PATCH',
          body: JSON.stringify({
            memberName: memberName.trim(),
            phone: phone.trim() || null,
            email: email.trim() || null,
            address: address.trim() || null,
          }),
        },
        user.accessToken,
      )
      setProfile(next)
      await updateUser({ fullName: next.memberName })
      showSuccess('Your profile was saved.', 'Profile updated')
    } catch (e) {
      showError(e instanceof Error ? e.message : 'Failed to save profile')
    } finally {
      setSavingProfile(false)
    }
  }

  async function savePassword() {
    if (!user?.accessToken) return
    if (!currentPassword.trim()) {
      showError('Enter your current password.', 'Validation')
      return
    }
    if (!newPassword.trim()) {
      showError('Enter a new password.', 'Validation')
      return
    }
    if (!confirmPassword.trim()) {
      showError('Confirm your new password.', 'Validation')
      return
    }
    if (newPassword.length < 6) {
      showError('New password must be at least 6 characters.', 'Validation')
      return
    }
    if (newPassword !== confirmPassword) {
      showError('New passwords do not match.', 'Validation')
      return
    }
    setSavingPassword(true)
    try {
      await apiFetch(
        '/api/auth/change-password',
        {
          method: 'POST',
          body: JSON.stringify({
            currentPassword,
            newPassword,
          }),
        },
        user.accessToken,
      )
      setCurrentPassword('')
      setNewPassword('')
      setConfirmPassword('')
      showSuccess('Your password was updated.', 'Password changed')
    } catch (e) {
      showError(e instanceof Error ? e.message : 'Failed to change password')
    } finally {
      setSavingPassword(false)
    }
  }

  function onLogout() {
    showConfirm('Sign out?', 'You will need to sign in again to continue.', () => {
      void logout()
    }, 'Sign out')
  }

  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <ScreenHeader
        title="Profile"
        subtitle={`@${profile?.username ?? user?.username ?? 'member'} · ${profile?.status ?? '—'}`}
      />

      {loading && !profile ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <KeyboardForm contentContainerStyle={styles.content}>
          <View style={styles.card}>
            <View style={styles.cardTitleRow}>
              <Ionicons name="person-circle-outline" size={22} color={COLORS.teal} />
              <Text style={styles.cardTitle}>Contact details</Text>
            </View>
            <Field label="Full name" value={memberName} onChangeText={setMemberName} />
            <Field
              label="Username"
              value={profile?.username ?? user?.username ?? ''}
              editable={false}
            />
            <Field
              label="Phone"
              value={phone}
              onChangeText={setPhone}
              keyboardType="phone-pad"
            />
            <Field
              label="Email"
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
            />
            <Field label="Address" value={address} onChangeText={setAddress} />
            <Pressable
              style={[styles.submitBtn, savingProfile && styles.submitDisabled]}
              disabled={savingProfile}
              onPress={() => void saveProfile()}
            >
              <Ionicons name="save-outline" size={18} color={COLORS.white} />
              <Text style={styles.submitText}>
                {savingProfile ? 'Saving…' : 'Save profile'}
              </Text>
            </Pressable>
          </View>

          <View style={styles.card}>
            <View style={styles.cardTitleRow}>
              <Ionicons name="lock-closed-outline" size={20} color={COLORS.teal} />
              <Text style={styles.cardTitle}>Change password</Text>
            </View>
            <Text style={styles.hint}>At least 6 characters. Tap the eye to show/hide.</Text>
            <PasswordField
              label="Current password"
              value={currentPassword}
              onChangeText={setCurrentPassword}
              autoComplete="password"
              textContentType="password"
            />
            <PasswordField
              label="New password"
              value={newPassword}
              onChangeText={setNewPassword}
              autoComplete="password-new"
              textContentType="newPassword"
            />
            <PasswordField
              label="Confirm new password"
              value={confirmPassword}
              onChangeText={setConfirmPassword}
              autoComplete="password-new"
              textContentType="newPassword"
            />
            <Pressable
              style={[styles.submitBtn, savingPassword && styles.submitDisabled]}
              disabled={savingPassword}
              onPress={() => void savePassword()}
            >
              <Ionicons name="key-outline" size={18} color={COLORS.white} />
              <Text style={styles.submitText}>
                {savingPassword ? 'Updating…' : 'Update password'}
              </Text>
            </Pressable>
          </View>

          <Pressable style={styles.logoutBtn} onPress={onLogout}>
            <Ionicons name="log-out-outline" size={18} color={COLORS.danger} />
            <Text style={styles.logoutText}>Sign out</Text>
          </Pressable>
        </KeyboardForm>
      )}
    </View>
  )
}

function Field({
  label,
  value,
  onChangeText,
  editable = true,
  keyboardType,
  autoCapitalize,
}: {
  label: string
  value: string
  onChangeText?: (v: string) => void
  editable?: boolean
  keyboardType?: 'default' | 'email-address' | 'phone-pad'
  autoCapitalize?: 'none' | 'sentences'
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={[styles.input, !editable && styles.inputDisabled]}
        value={value}
        onChangeText={onChangeText}
        editable={editable}
        keyboardType={keyboardType}
        autoCapitalize={autoCapitalize}
        placeholderTextColor={COLORS.muted}
      />
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: 16, paddingBottom: 56 },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 10,
  },
  cardTitle: { fontSize: 16, fontFamily: FONTS.bodyBold, color: COLORS.text },
  hint: { fontSize: 13, fontFamily: FONTS.body, color: COLORS.muted, marginBottom: 8 },
  field: { marginBottom: 12 },
  label: { fontSize: 12, fontFamily: FONTS.bodyBold, color: COLORS.muted, marginBottom: 6 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
    color: COLORS.text,
    backgroundColor: COLORS.bg,
    fontFamily: FONTS.body,
  },
  inputDisabled: { opacity: 0.7 },
  submitBtn: {
    marginTop: 8,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 13,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  submitDisabled: { opacity: 0.5 },
  submitText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 15 },
  logoutBtn: {
    marginTop: 4,
    marginBottom: 12,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#FECACA',
    paddingVertical: 14,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  logoutText: { color: COLORS.danger, fontFamily: FONTS.bodyBold, fontSize: 15 },
})
