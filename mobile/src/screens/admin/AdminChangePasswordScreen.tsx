import { useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native'
import { useNavigation } from '@react-navigation/native'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ScreenHeader } from '../../components/ui'
import { PasswordField } from '../../components/PasswordField'
import { KeyboardForm } from '../../components/KeyboardForm'
import { showError, showSuccess } from '../../utils/alerts'

export function AdminChangePasswordScreen() {
  const { user, updateUser } = useAuth()
  const navigation = useNavigation()
  const [currentPassword, setCurrent] = useState('')
  const [newPassword, setNew] = useState('')
  const [confirm, setConfirm] = useState('')
  const [loading, setLoading] = useState(false)

  async function submit() {
    if (!currentPassword.trim()) {
      showError('Enter your current password.', 'Missing password')
      return
    }
    if (!newPassword.trim()) {
      showError('Enter a new password.', 'Validation')
      return
    }
    if (!confirm.trim()) {
      showError('Confirm your new password.', 'Validation')
      return
    }
    if (newPassword.length < 6) {
      showError('New password must be at least 6 characters.', 'Invalid password')
      return
    }
    if (newPassword !== confirm) {
      showError('New password and confirm password do not match.', 'Mismatch')
      return
    }
    if (!user?.accessToken) return
    setLoading(true)
    try {
      await apiFetch(
        '/api/auth/change-password',
        {
          method: 'POST',
          body: JSON.stringify({ currentPassword, newPassword }),
        },
        user.accessToken,
      )
      await updateUser({ mustChangePassword: false })
      setCurrent('')
      setNew('')
      setConfirm('')
      const forced = Boolean(user.mustChangePassword)
      showSuccess('Your password was updated.', 'Password changed', () => {
        if (!forced && navigation.canGoBack()) navigation.goBack()
      })
    } catch (e) {
      showError(e instanceof Error ? e.message : 'Failed to change password')
    } finally {
      setLoading(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Change password"
        subtitle={
          user?.mustChangePassword
            ? 'Required after admin reset'
            : 'Update your login password'
        }
        onBack={user?.mustChangePassword ? undefined : () => navigation.goBack()}
      />
      <KeyboardForm contentContainerStyle={styles.content}>
        {user?.mustChangePassword ? (
          <View style={styles.warnBox}>
            <Ionicons name="warning-outline" size={18} color="#92400E" />
            <Text style={styles.warn}>
              You must change your password before continuing.
            </Text>
          </View>
        ) : null}
        <PasswordField
          label="Current password"
          value={currentPassword}
          onChangeText={setCurrent}
          autoComplete="password"
          textContentType="password"
        />
        <PasswordField
          label="New password"
          value={newPassword}
          onChangeText={setNew}
          autoComplete="password-new"
          textContentType="newPassword"
        />
        <PasswordField
          label="Confirm password"
          value={confirm}
          onChangeText={setConfirm}
          autoComplete="password-new"
          textContentType="newPassword"
          returnKeyType="done"
          onSubmitEditing={() => void submit()}
        />
        <Pressable
          style={[styles.btn, loading && styles.btnDisabled]}
          onPress={() => void submit()}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color={COLORS.white} />
          ) : (
            <>
              <Ionicons name="lock-closed-outline" size={18} color={COLORS.white} />
              <Text style={styles.btnText}>Update password</Text>
            </>
          )}
        </Pressable>
      </KeyboardForm>
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md },
  warnBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    backgroundColor: '#FEF3C7',
    padding: 12,
    borderRadius: 10,
    marginBottom: 8,
  },
  warn: {
    flex: 1,
    color: '#92400E',
    fontFamily: FONTS.body,
    fontSize: 13,
    lineHeight: 18,
  },
  btn: {
    marginTop: 18,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  btnDisabled: { opacity: 0.6 },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 15 },
})
