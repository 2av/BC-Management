import { StyleSheet, Text, View } from 'react-native'
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ScreenHeader, SoftCard } from '../../components/ui'
import { MenuRow } from '../../components/MenuRow'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

export function SuperAdminMoreScreen() {
  const { logout, user } = useAuth()
  const navigation = useNavigation<Nav>()

  return (
    <View style={styles.root}>
      <ScreenHeader title="More" subtitle={`Signed in as ${user?.fullName ?? 'super admin'}`} />
      <View style={styles.content}>
        <SoftCard>
          <View style={styles.profileRow}>
            <View style={styles.avatar}>
              <Ionicons name="planet-outline" size={22} color={COLORS.teal} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.name}>@{user?.username}</Text>
              <Text style={styles.muted}>Super admin · platform</Text>
            </View>
          </View>
        </SoftCard>
        <MenuRow
          label="Subscription payments"
          subtitle="Client billing history"
          icon="cash-outline"
          onPress={() => navigation.navigate('SaPayments')}
        />
        <MenuRow
          label="Audit log"
          subtitle="Platform activity trail"
          icon="document-text-outline"
          onPress={() => navigation.navigate('SaAudit')}
        />
        <MenuRow
          label="Change password"
          subtitle="Update your login password"
          icon="lock-closed-outline"
          onPress={() => navigation.navigate('AdminChangePassword')}
        />
        <MenuRow
          label="Sign out"
          subtitle="Leave this session"
          icon="log-out-outline"
          danger
          onPress={() => void logout()}
        />
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
})
