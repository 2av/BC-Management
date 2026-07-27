import { useState } from 'react'
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { StatusBar } from 'expo-status-bar'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../auth/AuthContext'
import { ApiError } from '../api'
import { BRAND, COLORS, FONTS, RADIUS, SPACE } from '../theme'

type Portal = 'Member' | 'ClientAdmin' | 'SuperAdmin'

export function LoginScreen() {
  const { login } = useAuth()
  const insets = useSafeAreaInsets()
  const [portal, setPortal] = useState<Portal>('Member')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  async function onSubmit() {
    setError(null)
    if (!username.trim() || !password) {
      setError('Enter username/mobile and password.')
      return
    }
    setLoading(true)
    try {
      await login(username, password, portal)
    } catch (e) {
      setError(e instanceof ApiError || e instanceof Error ? e.message : 'Login failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <View style={styles.root}>
      <StatusBar style="light" />
      <View style={[styles.orb, styles.orbOne]} />
      <View style={[styles.orb, styles.orbTwo]} />

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          contentContainerStyle={[
            styles.content,
            { paddingTop: insets.top + 36, paddingBottom: insets.bottom + 28 },
          ]}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.hero}>
            <Text style={styles.brand}>{BRAND}</Text>
            <Text style={styles.heroTitle}>Your BC group,{'\n'}in your pocket</Text>
            <Text style={styles.heroBlurb}>
              Members pay dues. Admins run groups. Super admins manage the platform.
            </Text>
          </View>

          <View style={styles.card}>
            {error ? <Text style={styles.error}>{error}</Text> : null}

            <Text style={styles.label}>Sign in as</Text>
            <View style={styles.portalRow}>
              <Pressable
                style={[styles.portalChip, portal === 'Member' && styles.portalChipOn]}
                onPress={() => setPortal('Member')}
              >
                <Text style={[styles.portalText, portal === 'Member' && styles.portalTextOn]}>Member</Text>
              </Pressable>
              <Pressable
                style={[styles.portalChip, portal === 'ClientAdmin' && styles.portalChipOn]}
                onPress={() => setPortal('ClientAdmin')}
              >
                <Text style={[styles.portalText, portal === 'ClientAdmin' && styles.portalTextOn]}>Admin</Text>
              </Pressable>
              <Pressable
                style={[styles.portalChip, portal === 'SuperAdmin' && styles.portalChipOn]}
                onPress={() => setPortal('SuperAdmin')}
              >
                <Text style={[styles.portalText, portal === 'SuperAdmin' && styles.portalTextOn]}>Super</Text>
              </Pressable>
            </View>

            <Text style={styles.label}>Username or mobile</Text>
            <TextInput
              style={styles.input}
              autoCapitalize="none"
              autoCorrect={false}
              value={username}
              onChangeText={setUsername}
              placeholder="username or mobile number"
              placeholderTextColor={COLORS.muted}
            />

            <Text style={styles.label}>Password</Text>
            <View style={styles.passwordWrap}>
              <TextInput
                style={[styles.input, styles.passwordInput]}
                secureTextEntry={!showPassword}
                value={password}
                onChangeText={setPassword}
                placeholder="password"
                placeholderTextColor={COLORS.muted}
                onSubmitEditing={() => void onSubmit()}
              />
              <Pressable
                style={styles.eyeBtn}
                onPress={() => setShowPassword((v) => !v)}
                hitSlop={8}
              >
                <Ionicons
                  name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                  size={20}
                  color={COLORS.muted}
                />
              </Pressable>
            </View>

            <Pressable
              style={[styles.button, loading && styles.buttonDisabled]}
              onPress={() => void onSubmit()}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color={COLORS.white} />
              ) : (
                <Text style={styles.buttonText}>Sign in</Text>
              )}
            </Pressable>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.navy },
  orb: { position: 'absolute', borderRadius: 999, opacity: 0.35 },
  orbOne: { width: 220, height: 220, backgroundColor: COLORS.teal, top: -40, right: -60 },
  orbTwo: { width: 160, height: 160, backgroundColor: COLORS.tealMid, bottom: 80, left: -50 },
  content: { paddingHorizontal: SPACE.lg, flexGrow: 1, justifyContent: 'center' },
  hero: { marginBottom: SPACE.lg },
  brand: { fontFamily: FONTS.displaySoft, fontSize: 13, color: COLORS.tealSoft, marginBottom: 8 },
  heroTitle: {
    fontFamily: FONTS.display,
    fontSize: 30,
    fontWeight: '700',
    color: COLORS.white,
    lineHeight: 36,
  },
  heroBlurb: {
    marginTop: 10,
    fontFamily: FONTS.body,
    fontSize: 14,
    color: 'rgba(255,255,255,0.72)',
    lineHeight: 20,
  },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: RADIUS.lg,
    padding: SPACE.lg,
    gap: 8,
  },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 13, color: COLORS.text },
  portalRow: { flexDirection: 'row', gap: 8, marginBottom: 4 },
  portalChip: {
    flex: 1,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingVertical: 10,
    alignItems: 'center',
    backgroundColor: COLORS.sand,
  },
  portalChipOn: { backgroundColor: COLORS.tealSoft, borderColor: COLORS.teal },
  portalText: { fontFamily: FONTS.bodyMed, color: COLORS.muted, fontSize: 12 },
  portalTextOn: { color: COLORS.teal, fontFamily: FONTS.bodyBold, fontSize: 12 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    color: COLORS.text,
    backgroundColor: COLORS.sand,
    fontFamily: FONTS.body,
  },
  passwordWrap: { position: 'relative' },
  passwordInput: { paddingRight: 44 },
  eyeBtn: { position: 'absolute', right: 12, top: 12 },
  button: {
    marginTop: 14,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 15 },
  error: { color: COLORS.danger, fontFamily: FONTS.body, marginBottom: 4 },
})
