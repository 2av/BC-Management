import { Pressable, StyleSheet, Text, View } from 'react-native'
import { Ionicons } from '@expo/vector-icons'
import { COLORS, FONTS } from '../theme'

type Props = {
  label: string
  icon: keyof typeof Ionicons.glyphMap
  onPress: () => void
  danger?: boolean
  subtitle?: string
}

export function MenuRow({ label, icon, onPress, danger, subtitle }: Props) {
  const tint = danger ? COLORS.danger : COLORS.teal
  return (
    <Pressable
      style={[styles.row, danger && styles.danger]}
      onPress={onPress}
      accessibilityRole="button"
    >
      <View style={[styles.iconWrap, danger && styles.iconWrapDanger]}>
        <Ionicons name={icon} size={20} color={tint} />
      </View>
      <View style={styles.textWrap}>
        <Text style={[styles.label, danger && { color: COLORS.danger }]}>{label}</Text>
        {subtitle ? <Text style={styles.sub}>{subtitle}</Text> : null}
      </View>
      <Ionicons name="chevron-forward" size={18} color={COLORS.muted} />
    </Pressable>
  )
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  danger: { borderColor: '#FECACA' },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: COLORS.tealSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconWrapDanger: { backgroundColor: '#FEE2E2' },
  textWrap: { flex: 1 },
  label: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  sub: { marginTop: 2, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
})
