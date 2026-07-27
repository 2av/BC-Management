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
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader } from '../../components/ui'
import type { AppNotification, NotificationCounts, RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>
type Filter = 'all' | 'unread'

export function AdminNotificationsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [filter, setFilter] = useState<Filter>('all')
  const [items, setItems] = useState<AppNotification[]>([])
  const [counts, setCounts] = useState<NotificationCounts | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const q = filter === 'all' ? '' : `?filter=${filter}`
      const [list, c] = await Promise.all([
        apiFetch<AppNotification[]>(`/api/notifications${q}`, {}, user.accessToken),
        apiFetch<NotificationCounts>('/api/notifications/counts', {}, user.accessToken),
      ])
      setItems(list)
      setCounts(c)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load notifications')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, filter])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  async function markRead(id: number) {
    if (!user?.accessToken) return
    try {
      await apiFetch(`/api/notifications/${id}/read`, { method: 'PATCH' }, user.accessToken)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed')
    }
  }

  async function markAll() {
    if (!user?.accessToken) return
    try {
      await apiFetch('/api/notifications/mark-all-read', { method: 'POST' }, user.accessToken)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed')
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Alerts"
        subtitle={counts ? `${counts.unread} unread` : 'Admin notifications'}
        onBack={() => navigation.goBack()}
      />
      <View style={styles.filters}>
        {(['all', 'unread'] as Filter[]).map((f) => (
          <Pressable
            key={f}
            style={[styles.chip, filter === f && styles.chipOn]}
            onPress={() => setFilter(f)}
          >
            <Text style={[styles.chipText, filter === f && styles.chipTextOn]}>{f}</Text>
          </Pressable>
        ))}
        <Pressable style={styles.markAll} onPress={() => void markAll()}>
          <Text style={styles.markAllText}>Mark all read</Text>
        </Pressable>
      </View>
      {loading && items.length === 0 ? (
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
          {items.length === 0 ? <Text style={styles.muted}>No notifications.</Text> : null}
          {items.map((n) => (
            <Pressable
              key={n.id}
              style={[styles.card, !n.isRead && styles.cardUnread]}
              onPress={() => void markRead(n.id)}
            >
              <Text style={styles.title}>{n.title}</Text>
              <Text style={styles.msg}>{n.message}</Text>
              <Text style={styles.meta}>{n.type} · tap to mark read</Text>
            </Pressable>
          ))}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  filters: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: SPACE.md,
    paddingTop: 8,
  },
  chip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: COLORS.white,
  },
  chipOn: { backgroundColor: COLORS.tealSoft, borderColor: COLORS.teal },
  chipText: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted, textTransform: 'capitalize' },
  chipTextOn: { color: COLORS.teal },
  markAll: { marginLeft: 'auto' },
  markAllText: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.teal },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 8 },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 12,
  },
  cardUnread: { borderColor: COLORS.teal, backgroundColor: '#F0FDFA' },
  title: { fontFamily: FONTS.bodyBold, fontSize: 14, color: COLORS.text },
  msg: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  meta: { marginTop: 6, fontFamily: FONTS.body, fontSize: 11, color: COLORS.tabInactive },
  muted: { fontFamily: FONTS.body, color: COLORS.muted },
})
