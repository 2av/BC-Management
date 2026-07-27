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
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { BRAND, COLORS, FONTS, RADIUS, SPACE } from '../../theme'
import { ErrorBanner, SoftCard } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type DashboardStats = {
  totalGroups: number
  activeGroups: number
  totalMembers: number
  totalCollected: number
  cashInHand: number
  thisMonthCollected: number
}

type Nav = NativeStackNavigationProp<RootStackParamList>

export function AdminHomeScreen() {
  const { user, logout } = useAuth()
  const navigation = useNavigation<Nav>()
  const insets = useSafeAreaInsets()
  const [data, setData] = useState<DashboardStats | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<DashboardStats>('/api/dashboard/admin', {}, user.accessToken)
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
    <View style={styles.root}>
      <StatusBar style="light" />
      <View style={[styles.hero, { paddingTop: insets.top + 10 }]}>
        <View style={styles.heroTop}>
          <View style={{ flex: 1 }}>
            <Text style={styles.brand}>{BRAND} · Admin</Text>
            <Text style={styles.hello}>{user?.fullName?.split(' ')[0] ?? 'Admin'}</Text>
          </View>
          <Pressable onPress={() => void logout()} style={styles.logoutChip}>
            <Text style={styles.logoutText}>Out</Text>
          </Pressable>
        </View>
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
          <View style={styles.statsRow}>
            <SoftCard style={styles.stat}>
              <Text style={styles.statLabel}>Groups</Text>
              <Text style={styles.statValue}>{data?.activeGroups ?? '—'}</Text>
            </SoftCard>
            <SoftCard style={styles.stat}>
              <Text style={styles.statLabel}>Members</Text>
              <Text style={styles.statValue}>{data?.totalMembers ?? '—'}</Text>
            </SoftCard>
          </View>
          <SoftCard>
            <Text style={styles.statLabel}>Collected this month</Text>
            <Text style={styles.statValue}>{formatInr(data?.thisMonthCollected ?? 0)}</Text>
            <Text style={[styles.statLabel, { marginTop: 10 }]}>Cash in hand</Text>
            <Text style={styles.statValue}>{formatInr(data?.cashInHand ?? 0)}</Text>
          </SoftCard>
          <Pressable
            style={styles.linkBtn}
            onPress={() => navigation.navigate('AdminChangePassword')}
          >
            <Text style={styles.linkBtnText}>Change password</Text>
          </Pressable>
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  hero: {
    paddingHorizontal: SPACE.md,
    paddingBottom: 14,
    backgroundColor: COLORS.navy,
    borderBottomLeftRadius: RADIUS.lg,
    borderBottomRightRadius: RADIUS.lg,
  },
  heroTop: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  brand: { fontFamily: FONTS.displaySoft, fontSize: 12, color: COLORS.tealSoft },
  hello: { fontFamily: FONTS.display, fontSize: 22, fontWeight: '700', color: COLORS.white },
  logoutChip: {
    borderWidth: 1,
    borderColor: 'rgba(204,251,241,0.35)',
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  logoutText: { color: COLORS.tealSoft, fontFamily: FONTS.bodyMed, fontSize: 12 },
  content: { padding: SPACE.md, gap: 12, paddingBottom: 40 },
  statsRow: { flexDirection: 'row', gap: 10 },
  stat: { flex: 1, marginBottom: 0 },
  statLabel: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  statValue: { fontFamily: FONTS.bodyBold, fontSize: 18, color: COLORS.text, marginTop: 4 },
  linkBtn: {
    marginTop: 8,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingVertical: 14,
    alignItems: 'center',
  },
  linkBtnText: { fontFamily: FONTS.bodyBold, color: COLORS.teal },
})
