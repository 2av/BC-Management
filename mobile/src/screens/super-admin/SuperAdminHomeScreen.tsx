import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native'
import { StatusBar } from 'expo-status-bar'
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { BRAND, COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, SoftCard } from '../../components/ui'
import { MenuRow } from '../../components/MenuRow'
import { formatInr, type RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

type Dashboard = {
  clientCount: number
  activeClients: number
  groupCount: number
  memberCount: number
  monthlyRevenue: number
  expiringSoon: number
}

export function SuperAdminHomeScreen() {
  const { user, logout } = useAuth()
  const navigation = useNavigation<Nav>()
  const insets = useSafeAreaInsets()
  const [data, setData] = useState<Dashboard | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<Dashboard>('/api/super-admin/dashboard', {}, user.accessToken)
      setData(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load dashboard')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  return (
    <View style={[styles.root, { paddingTop: insets.top }]}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.brand}>{BRAND}</Text>
          <Text style={styles.title}>Platform console</Text>
          <Text style={styles.sub}>Hi {user?.fullName ?? 'Super admin'}</Text>
        </View>
        <Pressable onPress={() => void logout()} style={styles.signOutBtn} hitSlop={8}>
          <Ionicons name="log-out-outline" size={18} color={COLORS.danger} />
        </Pressable>
      </View>

      {loading && !data ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true)
                void load()
              }}
              tintColor={COLORS.teal}
            />
          }
        >
          {error ? <ErrorBanner message={error} /> : null}
          {data ? (
            <View style={styles.stats}>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{data.activeClients}/{data.clientCount}</Text>
                <Text style={styles.statLabel}>Active clients</Text>
              </SoftCard>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{data.groupCount}</Text>
                <Text style={styles.statLabel}>Groups</Text>
              </SoftCard>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{data.memberCount}</Text>
                <Text style={styles.statLabel}>Members</Text>
              </SoftCard>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{formatInr(data.monthlyRevenue)}</Text>
                <Text style={styles.statLabel}>Month revenue</Text>
              </SoftCard>
              <SoftCard style={[styles.stat, { width: '100%' }]}>
                <Text style={[styles.statVal, data.expiringSoon > 0 && { color: COLORS.warning }]}>
                  {data.expiringSoon}
                </Text>
                <Text style={styles.statLabel}>Subscriptions expiring in 30 days</Text>
              </SoftCard>
            </View>
          ) : null}

          <MenuRow
            label="Change password"
            subtitle="Update your login password"
            icon="lock-closed-outline"
            onPress={() => navigation.navigate('AdminChangePassword')}
          />
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  header: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: SPACE.md,
    paddingBottom: 12,
    gap: 12,
  },
  brand: { fontFamily: FONTS.displaySoft, fontSize: 12, color: COLORS.teal },
  title: { fontFamily: FONTS.bodyBold, fontSize: 22, color: COLORS.text, marginTop: 2 },
  sub: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  signOutBtn: {
    marginTop: 8,
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#FECACA',
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  stats: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  stat: { width: '48%', flexGrow: 1, marginBottom: 0 },
  statVal: { fontFamily: FONTS.bodyBold, fontSize: 18, color: COLORS.text },
  statLabel: { marginTop: 4, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
})
