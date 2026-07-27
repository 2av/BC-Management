import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { StatusBar } from 'expo-status-bar'
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../auth/AuthContext'
import { apiFetch } from '../api'
import { COLORS } from '../theme'
import type {
  AvailableRandomMember,
  AvailableRandomMembers,
  RandomPick,
  RootStackParamList,
} from '../types'

type Nav = NativeStackNavigationProp<RootStackParamList, 'RandomPicks'>
type Route = RouteProp<RootStackParamList, 'RandomPicks'>

export function RandomPicksScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const { groupId } = route.params

  const [picks, setPicks] = useState<RandomPick[]>([])
  const [available, setAvailable] = useState<AvailableRandomMembers | null>(null)
  const [month, setMonth] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [list, avail] = await Promise.all([
        apiFetch<RandomPick[]>(`/api/groups/${groupId}/random-picks`, {}, user.accessToken),
        apiFetch<AvailableRandomMembers>(
          `/api/groups/${groupId}/random-picks/available-members`,
          {},
          user.accessToken,
        ),
      ])
      setPicks(list)
      setAvailable(avail)
      setMonth((prev) => {
        if (prev) return prev
        if (avail.activeMonth == null) return prev
        return String(avail.activeMonth === 1 ? 2 : avail.activeMonth)
      })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load random picks')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken, groupId])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  async function runSpin() {
    if (!user?.accessToken) return
    setSubmitting(true)
    setError(null)
    setMessage(null)
    try {
      const res = await apiFetch<RandomPick>(
        `/api/groups/${groupId}/random-picks`,
        {
          method: 'POST',
          body: JSON.stringify({ monthNumber: Number(month) }),
        },
        user.accessToken,
      )
      setMessage(`${res.effectiveMemberName} selected for month ${res.monthNumber}.`)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Spin failed')
    } finally {
      setSubmitting(false)
    }
  }

  const members: AvailableRandomMember[] = available?.members ?? []

  return (
    <View style={styles.root}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <Pressable onPress={() => navigation.goBack()} hitSlop={8}>
          <Text style={styles.back}>← Back</Text>
        </Pressable>
        <Text style={styles.title}>Random pick</Text>
        <Text style={styles.sub}>Spin for the current month winner</Text>
      </View>

      {loading && !available ? (
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
          {error ? <Text style={styles.error}>{error}</Text> : null}
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {available?.blockReason ? <Text style={styles.warn}>{available.blockReason}</Text> : null}

          {!available?.canCustomPick ? (
            <Text style={styles.warn}>You are not allowed to run the random spin.</Text>
          ) : (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>Spin wheel</Text>
              <Text style={styles.muted}>
                {members.length} eligible · active month {available.activeMonth ?? '—'}
              </Text>
              <Text style={styles.label}>Month</Text>
              <TextInput
                style={styles.input}
                keyboardType="number-pad"
                value={month}
                onChangeText={setMonth}
              />
              <Pressable
                style={[
                  styles.spinBtn,
                  (!members.length || available.canPlacePick === false || submitting) &&
                    styles.spinBtnDisabled,
                ]}
                disabled={!members.length || available.canPlacePick === false || submitting}
                onPress={() => void runSpin()}
              >
                {submitting ? (
                  <ActivityIndicator color={COLORS.white} />
                ) : (
                  <Text style={styles.spinBtnText}>Run random spin</Text>
                )}
              </Pressable>
            </View>
          )}

          <View style={styles.card}>
            <Text style={styles.cardTitle}>Previous picks</Text>
            {picks.length === 0 ? (
              <Text style={styles.muted}>No picks yet.</Text>
            ) : (
              picks.map((p) => (
                <View key={p.id} style={styles.pickRow}>
                  <Text style={styles.pickMonth}>Month {p.monthNumber}</Text>
                  <Text style={styles.pickName}>{p.effectiveMemberName}</Text>
                </View>
              ))
            )}
          </View>
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  header: {
    backgroundColor: COLORS.navy,
    paddingHorizontal: 18,
    paddingTop: 54,
    paddingBottom: 18,
  },
  back: { color: COLORS.tealSoft, fontSize: 14, marginBottom: 8 },
  title: { color: COLORS.white, fontSize: 22, fontWeight: '700' },
  sub: { color: COLORS.tealSoft, marginTop: 4, fontSize: 13 },
  content: { padding: 16, gap: 12, paddingBottom: 40 },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
    gap: 8,
  },
  cardTitle: { fontSize: 16, fontWeight: '700', color: COLORS.text },
  muted: { color: COLORS.muted, fontSize: 13 },
  label: { marginTop: 8, color: COLORS.text, fontWeight: '600', fontSize: 13 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: COLORS.text,
    backgroundColor: COLORS.sand,
  },
  spinBtn: {
    marginTop: 8,
    backgroundColor: '#D97706',
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  spinBtnDisabled: { opacity: 0.5 },
  spinBtnText: { color: COLORS.white, fontWeight: '700', fontSize: 15 },
  pickRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: COLORS.border,
  },
  pickMonth: { color: COLORS.muted, fontSize: 13 },
  pickName: { color: COLORS.text, fontWeight: '600', fontSize: 13 },
  error: { color: COLORS.danger, marginBottom: 4 },
  ok: { color: COLORS.success, marginBottom: 4 },
  warn: { color: COLORS.warning, marginBottom: 4 },
})
