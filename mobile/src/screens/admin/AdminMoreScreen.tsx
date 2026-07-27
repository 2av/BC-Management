import { Pressable, StyleSheet, Text, View } from 'react-native'
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ScreenHeader, SoftCard } from '../../components/ui'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

const LINKS: { label: string; go: (nav: Nav) => void }[] = [
  { label: 'Notifications', go: (nav) => nav.navigate('AdminNotifications') },
  { label: 'Reports', go: (nav) => nav.navigate('AdminReports') },
  { label: 'Payment config (UPI / QR)', go: (nav) => nav.navigate('AdminPaymentConfig') },
  { label: 'Settings', go: (nav) => nav.navigate('AdminSettings') },
  { label: 'Create group', go: (nav) => nav.navigate('AdminCreateGroup') },
  { label: 'Change password', go: (nav) => nav.navigate('AdminChangePassword') },
]

export function AdminMoreScreen() {
  const { logout, user } = useAuth()
  const navigation = useNavigation<Nav>()

  return (
    <View style={styles.root}>
      <ScreenHeader title="More" subtitle={`Signed in as ${user?.fullName ?? 'admin'}`} />
      <View style={styles.content}>
        <SoftCard>
          <Text style={styles.name}>@{user?.username}</Text>
          <Text style={styles.muted}>Client admin · mobile ops</Text>
        </SoftCard>
        {LINKS.map((item) => (
          <Pressable key={item.label} style={styles.row} onPress={() => item.go(navigation)}>
            <Text style={styles.rowText}>{item.label}</Text>
          </Pressable>
        ))}
        <Pressable style={[styles.row, styles.danger]} onPress={() => void logout()}>
          <Text style={[styles.rowText, { color: COLORS.danger }]}>Sign out</Text>
        </Pressable>
        <Text style={styles.hint}>Client admin tools are under the Admin login portal.</Text>
      </View>
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, gap: 10 },
  name: { fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, color: COLORS.muted },
  row: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingVertical: 14,
    paddingHorizontal: 14,
  },
  danger: { borderColor: '#FECACA' },
  rowText: { fontFamily: FONTS.bodyBold, color: COLORS.text },
  hint: { marginTop: 8, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted, lineHeight: 18 },
})
