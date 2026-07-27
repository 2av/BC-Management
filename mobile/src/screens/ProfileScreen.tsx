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
import { useFocusEffect } from '@react-navigation/native'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { COLORS, FONTS } from '../theme'
import { ScreenHeader, ErrorBanner, SuccessBanner } from '../components/ui'
import type { MemberProfile } from '../types'

export function ProfileScreen() {
  const { user, updateUser } = useAuth()

  const [profile, setProfile] = useState<MemberProfile | null>(null)
  const [memberName, setMemberName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [address, setAddress] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [savingProfile, setSavingProfile] = useState(false)
  const [savingPassword, setSavingPassword] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
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
      setError(e instanceof Error ? e.message : 'Failed to load profile')
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

  useEffect(() => {
    setMessage(null)
  }, [])

  async function saveProfile() {
    if (!user?.accessToken || !memberName.trim()) {
      setError('Full name is required.')
      return
    }
    setSavingProfile(true)
    setError(null)
    setMessage(null)
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
      setMessage('Profile updated.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save profile')
    } finally {
      setSavingProfile(false)
    }
  }

  async function savePassword() {
    if (!user?.accessToken) return
    if (newPassword.length < 6) {
      setError('New password must be at least 6 characters.')
      return
    }
    if (newPassword !== confirmPassword) {
      setError('New passwords do not match.')
      return
    }
    setSavingPassword(true)
    setError(null)
    setMessage(null)
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
      setMessage('Password updated.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to change password')
    } finally {
      setSavingPassword(false)
    }
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
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <ErrorBanner message={error} /> : null}
          {message ? <SuccessBanner message={message} /> : null}

          <View style={styles.card}>
            <Text style={styles.cardTitle}>Contact details</Text>
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
              <Text style={styles.submitText}>
                {savingProfile ? 'Saving…' : 'Save profile'}
              </Text>
            </Pressable>
          </View>

          <View style={styles.card}>
            <Text style={styles.cardTitle}>Change password</Text>
            <Text style={styles.hint}>At least 6 characters.</Text>
            <Field
              label="Current password"
              value={currentPassword}
              onChangeText={setCurrentPassword}
              secureTextEntry
            />
            <Field
              label="New password"
              value={newPassword}
              onChangeText={setNewPassword}
              secureTextEntry
            />
            <Field
              label="Confirm new password"
              value={confirmPassword}
              onChangeText={setConfirmPassword}
              secureTextEntry
            />
            <Pressable
              style={[
                styles.submitBtn,
                (savingPassword || !currentPassword || !newPassword) && styles.submitDisabled,
              ]}
              disabled={savingPassword || !currentPassword || !newPassword}
              onPress={() => void savePassword()}
            >
              <Text style={styles.submitText}>
                {savingPassword ? 'Updating…' : 'Update password'}
              </Text>
            </Pressable>
          </View>
        </ScrollView>
      )}
    </View>
  )
}

function Field({
  label,
  value,
  onChangeText,
  editable = true,
  secureTextEntry,
  keyboardType,
  autoCapitalize,
}: {
  label: string
  value: string
  onChangeText?: (v: string) => void
  editable?: boolean
  secureTextEntry?: boolean
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
        secureTextEntry={secureTextEntry}
        keyboardType={keyboardType}
        autoCapitalize={autoCapitalize}
        placeholderTextColor={COLORS.muted}
      />
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
  title: { fontSize: 22, fontWeight: '700', color: COLORS.text },
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
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardTitle: { fontSize: 16, fontWeight: '700', color: COLORS.text, marginBottom: 10 },
  hint: { fontSize: 13, color: COLORS.muted, marginBottom: 8 },
  field: { marginBottom: 12 },
  label: { fontSize: 12, fontWeight: '700', color: COLORS.muted, marginBottom: 6 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
    color: COLORS.text,
    backgroundColor: COLORS.bg,
  },
  inputDisabled: { opacity: 0.7 },
  submitBtn: {
    marginTop: 4,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 13,
    alignItems: 'center',
  },
  submitDisabled: { opacity: 0.5 },
  submitText: { color: COLORS.white, fontWeight: '700', fontSize: 15 },
})
