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
import { useFocusEffect } from '@react-navigation/native'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { COLORS, FONTS } from '../theme'
import { ScreenHeader, ErrorBanner } from '../components/ui'
import type {
  AppNotification,
  NotificationCounts,
} from '../types'

type Filter = 'all' | 'unread' | 'read' | 'warning' | 'danger'

export function NotificationsScreen() {
  const { user } = useAuth()
  const [filter, setFilter] = useState<Filter>('all')
  const [items, setItems] = useState<AppNotification[]>([])
  const [counts, setCounts] = useState<NotificationCounts | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

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
    setBusyId(id)
    try {
      await apiFetch(`/api/notifications/${id}/read`, { method: 'PATCH' }, user.accessToken)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to mark read')
    } finally {
      setBusyId(null)
    }
  }

  async function markAllRead() {
    if (!user?.accessToken) return
    setBusyId(-1)
    try {
      await apiFetch('/api/notifications/mark-all-read', { method: 'POST' }, user.accessToken)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to mark all read')
    } finally {
      setBusyId(null)
    }
  }

  async function remove(id: number) {
    if (!user?.accessToken) return
    setBusyId(id)
    try {
      await apiFetch(`/api/notifications/${id}`, { method: 'DELETE' }, user.accessToken)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to delete')
    } finally {
      setBusyId(null)
    }
  }

  const filters: { key: Filter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'unread', label: `Unread${counts ? ` (${counts.unread})` : ''}` },
    { key: 'read', label: 'Read' },
    { key: 'warning', label: 'Warning' },
    { key: 'danger', label: 'Danger' },
  ]

  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <ScreenHeader
        title="Notifications"
        subtitle={counts ? `${counts.unread} unread of ${counts.total}` : 'Alerts and updates'}
        right={
          <Pressable style={styles.markAll} onPress={() => void markAllRead()} disabled={busyId === -1}>
            <Text style={styles.markAllText}>Mark all</Text>
          </Pressable>
        }
      />

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

          <View style={styles.chips}>
            {filters.map((f) => (
              <Pressable
                key={f.key}
                onPress={() => setFilter(f.key)}
                style={[styles.chip, filter === f.key && styles.chipActive]}
              >
                <Text style={[styles.chipText, filter === f.key && styles.chipTextActive]}>
                  {f.label}
                </Text>
              </Pressable>
            ))}
          </View>

          {items.length === 0 ? (
            <Text style={styles.muted}>No notifications.</Text>
          ) : (
            items.map((n) => (
              <View key={n.id} style={[styles.card, !n.isRead && styles.cardUnread]}>
                <View style={styles.cardTop}>
                  <Text style={styles.cardTitle}>{n.title}</Text>
                  <View
                    style={[
                      styles.typePill,
                      n.type === 'danger'
                        ? styles.typeDanger
                        : n.type === 'warning'
                          ? styles.typeWarn
                          : styles.typeInfo,
                    ]}
                  >
                    <Text style={styles.typeText}>{n.type}</Text>
                  </View>
                </View>
                <Text style={styles.message}>{n.message}</Text>
                <Text style={styles.date}>{new Date(n.createdAt).toLocaleString('en-IN')}</Text>
                <View style={styles.actions}>
                  {!n.isRead ? (
                    <Pressable
                      style={styles.actionBtn}
                      disabled={busyId === n.id}
                      onPress={() => void markRead(n.id)}
                    >
                      <Text style={styles.actionText}>Mark read</Text>
                    </Pressable>
                  ) : null}
                  <Pressable
                    style={[styles.actionBtn, styles.deleteBtn]}
                    disabled={busyId === n.id}
                    onPress={() => void remove(n.id)}
                  >
                    <Text style={[styles.actionText, styles.deleteText]}>Delete</Text>
                  </Pressable>
                </View>
              </View>
            ))
          )}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  header: {
    paddingTop: 56,
    paddingHorizontal: 20,
    paddingBottom: 14,
    backgroundColor: COLORS.white,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  back: { color: COLORS.teal, fontWeight: '600', marginBottom: 8 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  title: { fontSize: 22, fontWeight: '700', color: COLORS.text },
  sub: { fontSize: 13, color: COLORS.muted, marginTop: 2 },
  markAll: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  markAllText: { fontSize: 12, fontWeight: '700', color: COLORS.teal },
  content: { padding: 16, paddingBottom: 40 },
  error: {
    backgroundColor: '#FEF2F2',
    color: COLORS.danger,
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
  },
  chipActive: { backgroundColor: COLORS.teal, borderColor: COLORS.teal },
  chipText: { fontSize: 12, fontWeight: '600', color: COLORS.muted },
  chipTextActive: { color: COLORS.white },
  muted: { fontSize: 13, color: COLORS.muted },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardUnread: { borderColor: COLORS.tealSoft, backgroundColor: '#F0FDFA' },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 8, marginBottom: 6 },
  cardTitle: { flex: 1, fontSize: 15, fontWeight: '700', color: COLORS.text },
  typePill: { borderRadius: 999, paddingHorizontal: 8, paddingVertical: 3 },
  typeInfo: { backgroundColor: '#E2E8F0' },
  typeWarn: { backgroundColor: '#FEF3C7' },
  typeDanger: { backgroundColor: '#FEE2E2' },
  typeText: { fontSize: 11, fontWeight: '700', color: COLORS.text, textTransform: 'capitalize' },
  message: { fontSize: 13, color: COLORS.muted, lineHeight: 18 },
  date: { fontSize: 11, color: COLORS.muted, marginTop: 8 },
  actions: { flexDirection: 'row', gap: 8, marginTop: 10 },
  actionBtn: {
    borderRadius: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  actionText: { fontSize: 12, fontWeight: '700', color: COLORS.teal },
  deleteBtn: { borderColor: '#FECACA' },
  deleteText: { color: COLORS.danger },
})
