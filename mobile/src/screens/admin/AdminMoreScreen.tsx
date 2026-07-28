import { Pressable, StyleSheet, Text, View } from 'react-native'
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ScreenHeader, SoftCard } from '../../components/ui'
import { MenuRow } from '../../components/MenuRow'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

const LINKS: {
  label: string
  subtitle: string
  icon: keyof typeof Ionicons.glyphMap
  go: (nav: Nav) => void
}[] = [
  {
    label: 'Notifications',
    subtitle: 'Send alerts to members',
    icon: 'notifications-outline',
    go: (nav) => nav.navigate('AdminNotifications'),
  },
  {
    label: 'Reports',
    subtitle: 'Collections and summaries',
    icon: 'bar-chart-outline',
    go: (nav) => nav.navigate('AdminReports'),
  },
  {
    label: 'Payment config',
    subtitle: 'UPI / QR for member pay',
    icon: 'qr-code-outline',
    go: (nav) => nav.navigate('AdminPaymentConfig'),
  },
  {
    label: 'Settings',
    subtitle: 'Group and app options',
    icon: 'settings-outline',
    go: (nav) => nav.navigate('AdminSettings'),
  },
  {
    label: 'Create group',
    subtitle: 'Start a new BC group',
    icon: 'add-circle-outline',
    go: (nav) => nav.navigate('AdminCreateGroup'),
  },
  {
    label: 'Change password',
    subtitle: 'Update your login password',
    icon: 'lock-closed-outline',
    go: (nav) => nav.navigate('AdminChangePassword'),
  },
]

export function AdminMoreScreen() {
  const { logout, user } = useAuth()
  const navigation = useNavigation<Nav>()

  return (
    <View style={styles.root}>
      <ScreenHeader title="More" subtitle={`Signed in as ${user?.fullName ?? 'admin'}`} />
      <View style={styles.content}>
        <SoftCard>
          <View style={styles.profileRow}>
            <View style={styles.avatar}>
              <Ionicons name="shield-checkmark" size={22} color={COLORS.teal} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.name}>@{user?.username}</Text>
              <Text style={styles.muted}>Client admin · mobile ops</Text>
            </View>
          </View>
        </SoftCard>
        {LINKS.map((item) => (
          <MenuRow
            key={item.label}
            label={item.label}
            subtitle={item.subtitle}
            icon={item.icon}
            onPress={() => item.go(navigation)}
          />
        ))}
        <MenuRow
          label="Sign out"
          subtitle="Leave this admin session"
          icon="log-out-outline"
          danger
          onPress={() => void logout()}
        />
        <Text style={styles.hint}>Client admin tools are under the Admin login portal.</Text>
      </View>
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, gap: 10 },
  profileRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 14,
    backgroundColor: COLORS.tealSoft,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, color: COLORS.muted },
  hint: {
    marginTop: 8,
    fontFamily: FONTS.body,
    fontSize: 12,
    color: COLORS.muted,
    lineHeight: 18,
  },
})
