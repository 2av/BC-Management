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
import type { BottomTabNavigationProp } from '@react-navigation/bottom-tabs'
import type { CompositeNavigationProp } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useSafeAreaInsets } from 'react-native-safe-area-context'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { BRAND, COLORS, FONTS, RADIUS, SPACE } from '../theme'
import { ErrorBanner, SoftCard } from '../components/ui'
import {
  formatInr,
  type MainTabParamList,
  type MemberDashboard,
  type NotificationCounts,
  type RootStackParamList,
} from '../types'

type Nav = CompositeNavigationProp<
  BottomTabNavigationProp<MainTabParamList, 'Home'>,
  NativeStackNavigationProp<RootStackParamList>
>

export function DashboardScreen() {
  const { user, logout } = useAuth()
  const navigation = useNavigation<Nav>()
  const insets = useSafeAreaInsets()
  const [data, setData] = useState<MemberDashboard | null>(null)
  const [unread, setUnread] = useState(0)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [next, counts] = await Promise.all([
        apiFetch<MemberDashboard>('/api/members/me/dashboard', {}, user.accessToken),
        apiFetch<NotificationCounts>('/api/notifications/counts', {}, user.accessToken).catch(
          () => null,
        ),
      ])
      setData(next)
      setUnread(counts?.unread ?? 0)
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

  const activeGroups = (data?.groups ?? []).filter((g) => g.status === 'active')
  const pending = data?.pendingDues ?? 0
  const groupsWithPending = activeGroups.filter((g) => (g.pendingAmount ?? 0) > 0)
  const pendingGroupHint =
    groupsWithPending.length === 0
      ? `${activeGroups.length} group${activeGroups.length === 1 ? '' : 's'}`
      : groupsWithPending.length === 1
        ? `Due in ${groupsWithPending[0].groupName}`
        : `Due in ${groupsWithPending.length} groups`

  return (
    <View style={styles.root}>
      <StatusBar style="light" />
      <View style={[styles.hero, { paddingTop: insets.top + 10 }]}>
        <View style={styles.heroTop}>
          <View style={{ flex: 1 }}>
            <Text style={styles.brand}>{BRAND}</Text>
            <Text style={styles.hello}>
              Namaste, {data?.fullName?.split(' ')[0] ?? user?.fullName?.split(' ')[0] ?? 'Member'}
            </Text>
          </View>
          <Pressable onPress={() => void logout()} style={styles.logoutChip}>
            <Ionicons name="log-out-outline" size={16} color={COLORS.tealSoft} />
          </Pressable>
        </View>

        <View style={styles.heroPending}>
          <View style={{ flex: 1 }}>
            <Text style={styles.heroPendingLabel}>Pending dues</Text>
            <Text style={styles.heroPendingHint}>
              {pendingGroupHint}
              {unread > 0 ? ` · ${unread} alert${unread === 1 ? '' : 's'}` : ''}
            </Text>
          </View>
          <Text style={styles.heroPendingValue}>{formatInr(pending)}</Text>
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
            <Stat label="Paid" value={formatInr(data?.totalPaid ?? 0)} />
            <Stat label="Received" value={formatInr(data?.totalReceived ?? 0)} />
          </View>

          <View style={styles.quickRow}>
            <QuickAction
              icon="wallet-outline"
              label="Pay now"
              onPress={() => navigation.navigate('Pay')}
            />
            <QuickAction
              icon="notifications-outline"
              label="Alerts"
              badge={unread}
              onPress={() => navigation.navigate('Alerts')}
            />
          </View>

          <Text style={styles.section}>Your groups</Text>
          {activeGroups.length === 0 ? (
            <Text style={styles.muted}>No active groups yet.</Text>
          ) : (
            activeGroups.map((g) => {
              const progress =
                g.totalMembers > 0 ? Math.min(1, g.completedMonths / g.totalMembers) : 0
              const hasPending = (g.pendingAmount ?? 0) > 0
              return (
                <SoftCard
                  key={g.groupMemberId}
                  style={[styles.groupCard, hasPending ? styles.groupCardPending : null]}
                >
                  <Pressable onPress={() => navigation.navigate('GroupLedger', { groupId: g.groupId })}>
                    <View style={styles.groupTop}>
                      <Text style={styles.groupName}>{g.groupName}</Text>
                      <Text style={styles.groupAmt}>{formatInr(g.monthlyContribution)}</Text>
                    </View>
                    <Text style={styles.muted}>
                      Seat #{g.memberNumber}
                      {g.handLabel ? ` · ${g.handLabel}` : ''} · Paid {formatInr(g.totalPaid)}
                    </Text>
                    {hasPending ? (
                      <View style={styles.pendingBanner}>
                        <Text style={styles.pendingBannerTitle}>Payment pending</Text>
                        <Text style={styles.pendingBannerAmt}>{formatInr(g.pendingAmount)}</Text>
                        <Text style={styles.pendingBannerSub}>
                          {g.nextPendingMonth ? `Month ${g.nextPendingMonth}` : 'Due'}
                          {g.pendingPaymentCount > 1 ? ` · ${g.pendingPaymentCount} months` : ''}
                        </Text>
                      </View>
                    ) : (
                      <Text style={styles.clearedHint}>No payment due</Text>
                    )}
                    <View style={styles.progressTrack}>
                      <View style={[styles.progressFill, { width: `${progress * 100}%` }]} />
                    </View>
                    <Text style={styles.progressLabel}>
                      {g.completedMonths}/{g.totalMembers} months · Open ledger
                    </Text>
                  </Pressable>
                  {hasPending && g.nextPendingMonth ? (
                    <Pressable
                      style={styles.payCta}
                      onPress={() =>
                        navigation.navigate('PayDetail', {
                          groupId: g.groupId,
                          month: g.nextPendingMonth!,
                        })
                      }
                    >
                      <Text style={styles.payCtaText}>Pay now</Text>
                    </Pressable>
                  ) : (
                    <View style={[styles.payCta, styles.payCtaDisabled]}>
                      <Text style={styles.payCtaTextDisabled}>Pay</Text>
                    </View>
                  )}
                </SoftCard>
              )
            })
          )}
        </ScrollView>
      )}
    </View>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <SoftCard style={styles.stat}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={styles.statValue}>{value}</Text>
    </SoftCard>
  )
}

function QuickAction({
  icon,
  label,
  onPress,
  badge,
}: {
  icon: keyof typeof Ionicons.glyphMap
  label: string
  onPress: () => void
  badge?: number
}) {
  return (
    <Pressable style={styles.quick} onPress={onPress}>
      <View style={styles.quickIconWrap}>
        <Ionicons name={icon} size={20} color={COLORS.teal} />
        {badge && badge > 0 ? (
          <View style={styles.quickBadge}>
            <Text style={styles.quickBadgeText}>{badge > 9 ? '9+' : badge}</Text>
          </View>
        ) : null}
      </View>
      <Text style={styles.quickLabel}>{label}</Text>
    </Pressable>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  hero: {
    paddingHorizontal: SPACE.md,
    paddingBottom: 12,
    borderBottomLeftRadius: RADIUS.lg,
    borderBottomRightRadius: RADIUS.lg,
    backgroundColor: COLORS.navy,
  },
  heroTop: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  brand: {
    fontFamily: FONTS.displaySoft,
    fontSize: 12,
    color: COLORS.tealSoft,
    marginBottom: 2,
  },
  hello: {
    fontFamily: FONTS.display,
    fontSize: 20,
    fontWeight: '700',
    color: COLORS.white,
    letterSpacing: -0.3,
  },
  logoutChip: {
    width: 34,
    height: 34,
    borderRadius: 17,
    borderWidth: 1,
    borderColor: 'rgba(204,251,241,0.35)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroPending: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.15)',
  },
  heroPendingLabel: {
    fontFamily: FONTS.bodyMed,
    fontSize: 11,
    color: 'rgba(255,255,255,0.65)',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  heroPendingValue: {
    fontFamily: FONTS.display,
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.white,
    letterSpacing: -0.4,
  },
  heroPendingHint: {
    marginTop: 2,
    fontFamily: FONTS.body,
    fontSize: 12,
    color: 'rgba(255,255,255,0.65)',
  },
  content: { padding: SPACE.md, paddingBottom: 40 },
  statsRow: { flexDirection: 'row', gap: 10, marginTop: -6 },
  stat: { flex: 1, marginBottom: 0 },
  statLabel: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted, marginBottom: 4 },
  statValue: { fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  quickRow: { flexDirection: 'row', gap: 10, marginTop: SPACE.md, marginBottom: 4 },
  quick: {
    flex: 1,
    backgroundColor: COLORS.white,
    borderRadius: RADIUS.md,
    paddingVertical: 14,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  quickIconWrap: { position: 'relative', marginBottom: 6 },
  quickLabel: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.text },
  quickBadge: {
    position: 'absolute',
    top: -6,
    right: -10,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: COLORS.danger,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 3,
  },
  quickBadgeText: { color: COLORS.white, fontSize: 9, fontFamily: FONTS.bodyBold },
  section: {
    marginTop: SPACE.lg,
    marginBottom: SPACE.sm,
    fontFamily: FONTS.displaySoft,
    fontSize: 20,
    color: COLORS.text,
  },
  groupCard: { marginBottom: SPACE.sm },
  groupCardPending: {
    borderColor: '#FECACA',
    borderWidth: 1,
    backgroundColor: '#FFF8F8',
  },
  groupTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
    marginBottom: 4,
  },
  groupName: { flex: 1, fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  groupAmt: { fontFamily: FONTS.bodyBold, fontSize: 14, color: COLORS.teal },
  muted: { fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  pendingBanner: {
    marginTop: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#FECACA',
    backgroundColor: '#FEF2F2',
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  pendingBannerTitle: {
    fontFamily: FONTS.bodyMed,
    fontSize: 11,
    color: COLORS.danger,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  pendingBannerAmt: {
    marginTop: 2,
    fontFamily: FONTS.bodyBold,
    fontSize: 18,
    color: COLORS.danger,
  },
  pendingBannerSub: {
    marginTop: 2,
    fontFamily: FONTS.body,
    fontSize: 12,
    color: '#991B1B',
  },
  clearedHint: {
    marginTop: 10,
    fontFamily: FONTS.bodyMed,
    fontSize: 12,
    color: COLORS.success,
  },
  payCta: {
    marginTop: 12,
    backgroundColor: COLORS.danger,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  payCtaDisabled: {
    backgroundColor: COLORS.sand,
  },
  payCtaText: {
    fontFamily: FONTS.bodyBold,
    fontSize: 14,
    color: COLORS.white,
  },
  payCtaTextDisabled: {
    fontFamily: FONTS.bodyMed,
    fontSize: 14,
    color: COLORS.muted,
  },
  progressTrack: {
    height: 7,
    borderRadius: 999,
    backgroundColor: COLORS.sand,
    marginTop: 12,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: COLORS.tealMid,
    borderRadius: 999,
  },
  progressLabel: {
    marginTop: 8,
    fontFamily: FONTS.bodyMed,
    fontSize: 12,
    color: COLORS.teal,
  },
})
