import { useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useNavigation } from '@react-navigation/native'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ScreenHeader } from '../../components/ui'

export function AdminChangePasswordScreen() {
  const { user, updateUser } = useAuth()
  const navigation = useNavigation()
  const [currentPassword, setCurrent] = useState('')
  const [newPassword, setNew] = useState('')
  const [confirm, setConfirm] = useState('')
  const [show, setShow] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function submit() {
    setError(null)
    if (newPassword.length < 6) {
      setError('New password must be at least 6 characters.')
      return
    }
    if (newPassword !== confirm) {
      setError('Passwords do not match.')
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
      setMessage('Password updated.')
      setCurrent('')
      setNew('')
      setConfirm('')
      if (user.mustChangePassword) navigation.goBack()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed')
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
      <View style={styles.content}>
        {user?.mustChangePassword ? (
          <Text style={styles.warn}>You must change your password before continuing.</Text>
        ) : null}
        {error ? <Text style={styles.err}>{error}</Text> : null}
        {message ? <Text style={styles.ok}>{message}</Text> : null}
        <Pwd
          label="Current password"
          value={currentPassword}
          onChange={setCurrent}
          show={show}
        />
        <Pwd label="New password" value={newPassword} onChange={setNew} show={show} />
        <Pwd label="Confirm password" value={confirm} onChange={setConfirm} show={show} />
        <Pressable style={styles.eye} onPress={() => setShow((v) => !v)}>
          <Ionicons name={show ? 'eye-off-outline' : 'eye-outline'} size={18} color={COLORS.teal} />
          <Text style={styles.eyeText}>{show ? 'Hide' : 'Show'} passwords</Text>
        </Pressable>
        <Pressable style={styles.btn} onPress={() => void submit()} disabled={loading}>
          {loading ? (
            <ActivityIndicator color={COLORS.white} />
          ) : (
            <Text style={styles.btnText}>Update password</Text>
          )}
        </Pressable>
      </View>
    </View>
  )
}

function Pwd({
  label,
  value,
  onChange,
  show,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  show: boolean
}) {
  return (
    <>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={styles.input}
        secureTextEntry={!show}
        value={value}
        onChangeText={onChange}
        placeholderTextColor={COLORS.muted}
      />
    </>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, gap: 6 },
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
  eye: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 8 },
  eyeText: { fontFamily: FONTS.bodyMed, color: COLORS.teal, fontSize: 13 },
  btn: {
    marginTop: 16,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  err: { color: COLORS.danger },
  ok: { color: COLORS.success },
  warn: {
    backgroundColor: '#FEF3C7',
    color: '#92400E',
    padding: 10,
    borderRadius: 10,
    fontFamily: FONTS.body,
    marginBottom: 6,
  },
})
