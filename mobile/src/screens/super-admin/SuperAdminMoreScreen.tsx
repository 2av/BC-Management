import { Pressable, StyleSheet, Text, View } from 'react-native'
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ScreenHeader, SoftCard } from '../../components/ui'
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
          <Text style={styles.name}>@{user?.username}</Text>
          <Text style={styles.muted}>Super admin · platform</Text>
        </SoftCard>
        <Pressable style={styles.row} onPress={() => navigation.navigate('SaPayments')}>
          <Text style={styles.rowText}>Subscription payments</Text>
        </Pressable>
        <Pressable style={styles.row} onPress={() => navigation.navigate('SaAudit')}>
          <Text style={styles.rowText}>Audit log</Text>
        </Pressable>
        <Pressable style={styles.row} onPress={() => navigation.navigate('AdminChangePassword')}>
          <Text style={styles.rowText}>Change password</Text>
        </Pressable>
        <Pressable style={[styles.row, styles.danger]} onPress={() => void logout()}>
          <Text style={[styles.rowText, { color: COLORS.danger }]}>Sign out</Text>
        </Pressable>
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
})
