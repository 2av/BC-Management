import type { ReactNode } from 'react'
import {
  Pressable,
  StyleSheet,
  Text,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { COLORS, FONTS, RADIUS, SPACE } from '../theme'

export function ScreenHeader({
  title,
  subtitle,
  onBack,
  right,
  dark,
}: {
  title: string
  subtitle?: string
  onBack?: () => void
  right?: ReactNode
  dark?: boolean
}) {
  const insets = useSafeAreaInsets()
  return (
    <View
      style={[
        styles.header,
        { paddingTop: Math.max(insets.top, 16) + 8 },
        dark && styles.headerDark,
      ]}
    >
      {onBack ? (
        <Pressable onPress={onBack} hitSlop={10} style={styles.backBtn}>
          <Text style={[styles.back, dark && styles.textOnDark]}>← Back</Text>
        </Pressable>
      ) : null}
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={[styles.title, dark && styles.textOnDark]}>{title}</Text>
          {subtitle ? (
            <Text style={[styles.sub, dark && styles.subOnDark]}>{subtitle}</Text>
          ) : null}
        </View>
        {right}
      </View>
    </View>
  )
}

export function SoftCard({
  children,
  style,
  accent,
}: {
  children: ReactNode
  style?: StyleProp<ViewStyle>
  accent?: boolean
}) {
  return (
    <View style={[styles.card, accent && styles.cardAccent, style]}>{children}</View>
  )
}

export function PrimaryButton({
  label,
  onPress,
  disabled,
  tone = 'teal',
}: {
  label: string
  onPress: () => void
  disabled?: boolean
  tone?: 'teal' | 'navy' | 'amber'
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={[
        styles.btn,
        tone === 'navy' && styles.btnNavy,
        tone === 'amber' && styles.btnAmber,
        disabled && styles.btnDisabled,
      ]}
    >
      <Text style={styles.btnText}>{label}</Text>
    </Pressable>
  )
}

export function ErrorBanner({ message }: { message: string }) {
  return <Text style={styles.error}>{message}</Text>
}

export function SuccessBanner({ message }: { message: string }) {
  return <Text style={styles.success}>{message}</Text>
}

const styles = StyleSheet.create({
  header: {
    paddingHorizontal: SPACE.lg,
    paddingBottom: SPACE.md,
    backgroundColor: COLORS.white,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  headerDark: {
    backgroundColor: COLORS.navy,
    borderBottomColor: 'transparent',
  },
  backBtn: { marginBottom: 8 },
  back: {
    color: COLORS.teal,
    fontFamily: FONTS.bodyMed,
    fontSize: 14,
  },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  title: {
    fontFamily: FONTS.display,
    fontSize: 26,
    color: COLORS.text,
    letterSpacing: -0.3,
  },
  sub: {
    marginTop: 4,
    fontFamily: FONTS.body,
    fontSize: 13,
    color: COLORS.muted,
    lineHeight: 18,
  },
  textOnDark: { color: COLORS.white },
  subOnDark: { color: 'rgba(255,255,255,0.72)' },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: RADIUS.md,
    padding: SPACE.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACE.sm,
  },
  cardAccent: {
    backgroundColor: COLORS.mint,
    borderColor: COLORS.tealSoft,
  },
  btn: {
    backgroundColor: COLORS.teal,
    borderRadius: RADIUS.sm,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnNavy: { backgroundColor: COLORS.navy },
  btnAmber: { backgroundColor: '#D97706' },
  btnDisabled: { opacity: 0.5 },
  btnText: {
    color: COLORS.white,
    fontFamily: FONTS.bodyBold,
    fontSize: 15,
  },
  error: {
    backgroundColor: '#FEF2F2',
    color: COLORS.danger,
    borderRadius: RADIUS.sm,
    padding: 12,
    marginBottom: 12,
    fontFamily: FONTS.bodyMed,
    fontSize: 13,
    overflow: 'hidden',
  },
  success: {
    backgroundColor: '#ECFDF5',
    color: COLORS.success,
    borderRadius: RADIUS.sm,
    padding: 12,
    marginBottom: 12,
    fontFamily: FONTS.bodyMed,
    fontSize: 13,
    overflow: 'hidden',
  },
})
