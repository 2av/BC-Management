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
import { PasswordField } from '../components/PasswordField'
import { showError } from '../utils/alerts'

type Portal = 'Member' | 'ClientAdmin' | 'SuperAdmin'

const PORTAL_COPY: Record<
  Portal,
  {
    badge: string
    title: string
    blurb: string
    tip: string
  }
> = {
  Member: {
    badge: 'Member sign in',
    title: 'Welcome back',
    blurb: 'Pay dues, track bids, and see your group activity in one place.',
    tip: 'Use the mobile number or username shared by your group admin.',
  },
  ClientAdmin: {
    badge: 'Admin sign in',
    title: 'Group admin',
    blurb: 'Manage members, bidding rounds, payments, and group ledgers.',
    tip: 'Sign in with your admin username. Members should use member login.',
  },
  SuperAdmin: {
    badge: 'Super admin',
    title: 'Platform access',
    blurb: 'Oversee clients, groups, and platform-wide settings.',
    tip: 'Reserved for Mitra Niidhi platform operators only.',
  },
}

export function LoginScreen() {
  const { login } = useAuth()
  const insets = useSafeAreaInsets()
  const [portal, setPortal] = useState<Portal>('Member')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)

  const copy = PORTAL_COPY[portal]
  const isMember = portal === 'Member'

  async function onSubmit() {
    if (!username.trim() || !password) {
      showError('Enter username/mobile and password.', 'Sign in')
      return
    }
    setLoading(true)
    try {
      await login(username, password, portal)
    } catch (e) {
      showError(
        e instanceof ApiError || e instanceof Error ? e.message : 'Login failed',
        'Sign in failed',
      )
    } finally {
      setLoading(false)
    }
  }

  function switchPortal(next: Portal) {
    setPortal(next)
    setUsername('')
    setPassword('')
  }

  return (
    <View style={styles.root}>
      <StatusBar style="light" />
      <View style={[styles.orb, styles.orbOne]} />
      <View style={[styles.orb, styles.orbTwo]} />
      <View style={[styles.orb, styles.orbThree]} />

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 8 : 0}
      >
        <ScrollView
          contentContainerStyle={[
            styles.content,
            { paddingTop: insets.top + 20, paddingBottom: insets.bottom + 24 },
          ]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
        >
          <View style={styles.brandRow}>
            <Text style={styles.brand}>{BRAND}</Text>
            {!isMember ? (
              <Pressable
                onPress={() => switchPortal('Member')}
                hitSlop={8}
                style={styles.backLink}
              >
                <Ionicons name="arrow-back" size={14} color={COLORS.tealSoft} />
                <Text style={styles.backLinkText}>Member login</Text>
              </Pressable>
            ) : null}
          </View>

          <View style={styles.card}>
            <View style={styles.badge}>
              <Ionicons
                name={
                  portal === 'Member'
                    ? 'person-outline'
                    : portal === 'ClientAdmin'
                      ? 'briefcase-outline'
                      : 'planet-outline'
                }
                size={14}
                color={COLORS.teal}
              />
              <Text style={styles.badgeText}>{copy.badge}</Text>
            </View>

            <Text style={styles.cardTitle}>{copy.title}</Text>
            <Text style={styles.cardBlurb}>{copy.blurb}</Text>

            <Text style={styles.label}>Username or mobile</Text>
            <View style={styles.userWrap}>
              <Ionicons
                name="person-outline"
                size={18}
                color={COLORS.muted}
                style={styles.userIcon}
              />
              <TextInput
                style={[styles.input, styles.userInput]}
                autoCapitalize="none"
                autoCorrect={false}
                value={username}
                onChangeText={setUsername}
                placeholder="username or mobile number"
                placeholderTextColor={COLORS.muted}
              />
            </View>

            <PasswordField
              label="Password"
              value={password}
              onChangeText={setPassword}
              placeholder="password"
              autoComplete="password"
              textContentType="password"
              returnKeyType="go"
              onSubmitEditing={() => void onSubmit()}
              variant="sand"
            />

            <Pressable
              style={[styles.button, loading && styles.buttonDisabled]}
              onPress={() => void onSubmit()}
              disabled={loading}
            >
              {loading ? (
                <ActivityIndicator color={COLORS.white} />
              ) : (
                <>
                  <Ionicons name="log-in-outline" size={18} color={COLORS.white} />
                  <Text style={styles.buttonText}>Sign in</Text>
                </>
              )}
            </Pressable>

            {isMember ? (
              <View style={styles.staffBox}>
                <Text style={styles.staffHint}>Staff access</Text>
                <View style={styles.staffLinks}>
                  <Pressable
                    onPress={() => switchPortal('ClientAdmin')}
                    hitSlop={6}
                    style={styles.staffLink}
                  >
                    <Text style={styles.staffLinkText}>Admin login</Text>
                    <Ionicons name="chevron-forward" size={14} color={COLORS.teal} />
                  </Pressable>
                  <Text style={styles.staffDot}>·</Text>
                  <Pressable
                    onPress={() => switchPortal('SuperAdmin')}
                    hitSlop={6}
                    style={styles.staffLink}
                  >
                    <Text style={styles.staffLinkText}>Super admin</Text>
                    <Ionicons name="chevron-forward" size={14} color={COLORS.teal} />
                  </Pressable>
                </View>
              </View>
            ) : null}
          </View>

          <View style={styles.infoBlock}>
            <View style={styles.infoRow}>
              <View style={styles.infoIcon}>
                <Ionicons name="information-circle-outline" size={18} color={COLORS.tealSoft} />
              </View>
              <Text style={styles.infoText}>{copy.tip}</Text>
            </View>
            {isMember ? (
              <View style={styles.infoRow}>
                <View style={styles.infoIcon}>
                  <Ionicons name="shield-checkmark-outline" size={18} color={COLORS.tealSoft} />
                </View>
                <Text style={styles.infoText}>
                  Your dues, bids, and invoices stay private to your group.
                </Text>
              </View>
            ) : null}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.navy },
  orb: { position: 'absolute', borderRadius: 999, opacity: 0.32 },
  orbOne: { width: 200, height: 200, backgroundColor: COLORS.teal, top: -50, right: -70 },
  orbTwo: { width: 140, height: 140, backgroundColor: COLORS.tealMid, bottom: 120, left: -55 },
  orbThree: {
    width: 90,
    height: 90,
    backgroundColor: COLORS.tealSoft,
    opacity: 0.12,
    top: '42%',
    right: 24,
  },
  content: {
    paddingHorizontal: SPACE.lg,
    flexGrow: 1,
    justifyContent: 'flex-start',
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: SPACE.md,
    minHeight: 28,
  },
  brand: {
    fontFamily: FONTS.displaySoft,
    fontSize: 14,
    color: COLORS.tealSoft,
    letterSpacing: 0.2,
  },
  backLink: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  backLinkText: {
    fontFamily: FONTS.bodyMed,
    fontSize: 12,
    color: COLORS.tealSoft,
  },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: RADIUS.lg,
    padding: SPACE.lg,
    gap: 6,
    shadowColor: '#000',
    shadowOpacity: 0.18,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 10 },
    elevation: 6,
  },
  badge: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: COLORS.tealSoft,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
    marginBottom: 4,
  },
  badgeText: {
    fontFamily: FONTS.bodyMed,
    fontSize: 12,
    color: COLORS.teal,
  },
  cardTitle: {
    fontFamily: FONTS.display,
    fontSize: 26,
    fontWeight: '700',
    color: COLORS.text,
    lineHeight: 32,
  },
  cardBlurb: {
    fontFamily: FONTS.body,
    fontSize: 14,
    color: COLORS.muted,
    lineHeight: 20,
    marginBottom: 6,
  },
  label: {
    marginTop: 8,
    marginBottom: 6,
    fontFamily: FONTS.bodyMed,
    fontSize: 12,
    color: COLORS.muted,
  },
  userWrap: { position: 'relative', justifyContent: 'center' },
  userIcon: { position: 'absolute', left: 12, zIndex: 1 },
  userInput: { paddingLeft: 40 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    color: COLORS.text,
    backgroundColor: COLORS.sand,
    fontFamily: FONTS.body,
    fontSize: 15,
  },
  button: {
    marginTop: 14,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 15 },
  staffBox: {
    marginTop: 16,
    paddingTop: 14,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: COLORS.border,
    alignItems: 'center',
    gap: 6,
  },
  staffHint: {
    fontFamily: FONTS.body,
    fontSize: 11,
    color: COLORS.muted,
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  staffLinks: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    flexWrap: 'wrap',
    gap: 4,
  },
  staffLink: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
    paddingVertical: 2,
    paddingHorizontal: 4,
  },
  staffLinkText: {
    fontFamily: FONTS.bodyMed,
    fontSize: 13,
    color: COLORS.teal,
  },
  staffDot: {
    color: COLORS.muted,
    fontSize: 14,
    marginHorizontal: 2,
  },
  infoBlock: {
    marginTop: SPACE.lg,
    gap: 12,
    paddingHorizontal: 4,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
  },
  infoIcon: {
    marginTop: 1,
  },
  infoText: {
    flex: 1,
    fontFamily: FONTS.body,
    fontSize: 13,
    color: 'rgba(255,255,255,0.72)',
    lineHeight: 19,
  },
})
