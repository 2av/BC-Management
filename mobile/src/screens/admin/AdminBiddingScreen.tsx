import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useNavigation, useRoute } from '@react-navigation/native'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type Route = RouteProp<RootStackParamList, 'AdminBidding'>

type MonthRow = {
  monthNumber: number
  biddingStatus: string
  paymentDone?: boolean
}

type BiddingOverview = {
  groupName: string
  totalMonthlyCollection: number
  months: MonthRow[]
}

type RosterSeat = {
  groupMemberId: number
  memberId: number
  memberName: string
  memberNumber: number
  handLabel: string | null
  status: string
}

export function AdminBiddingScreen() {
  const { user } = useAuth()
  const navigation = useNavigation()
  const route = useRoute<Route>()
  const groupId = route.params.groupId
  const [data, setData] = useState<BiddingOverview | null>(null)
  const [roster, setRoster] = useState<RosterSeat[]>([])
  const [month, setMonth] = useState('')
  const [selectedSeatId, setSelectedSeatId] = useState<number | null>(null)
  const [boli, setBoli] = useState('')
  const [endDate, setEndDate] = useState(() => {
    const d = new Date()
    d.setDate(d.getDate() + 2)
    return d.toISOString().slice(0, 10)
  })
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  const selected = useMemo(
    () => roster.find((s) => s.groupMemberId === selectedSeatId) ?? null,
    [roster, selectedSeatId],
  )

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [next, seats] = await Promise.all([
        apiFetch<BiddingOverview>(`/api/groups/${groupId}/bidding`, {}, user.accessToken),
        apiFetch<RosterSeat[]>(`/api/groups/${groupId}/members`, {}, user.accessToken),
      ])
      setData(next)
      setRoster(seats.filter((s) => s.status === 'active'))
      setMonth((prev) => {
        if (prev) return prev
        const open = next.months.find((m) => m.biddingStatus === 'open')
        if (open) return String(open.monthNumber)
        const pending = next.months.find((m) => m.biddingStatus !== 'completed' && !m.paymentDone)
        return pending ? String(pending.monthNumber) : '2'
      })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load bidding')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken, groupId])

  useEffect(() => {
    void load()
  }, [load])

  const monthMeta = data?.months.find((m) => m.monthNumber === Number(month))

  async function openBidding() {
    if (!user?.accessToken || !month) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        `/api/groups/${groupId}/bidding/open`,
        {
          method: 'POST',
          body: JSON.stringify({ monthNumber: Number(month), endDate }),
        },
        user.accessToken,
      )
      setMessage(`Bidding opened for month ${month}.`)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Open bidding failed')
    } finally {
      setSaving(false)
    }
  }

  async function closeBidding() {
    if (!user?.accessToken || !month) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        `/api/groups/${groupId}/bidding/months/${Number(month)}/close`,
        { method: 'POST' },
        user.accessToken,
      )
      setMessage(`Bidding closed for month ${month}.`)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Close bidding failed')
    } finally {
      setSaving(false)
    }
  }

  async function approve() {
    if (!user?.accessToken || !selected) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const hasBoli = Boolean(boli.trim())
      await apiFetch(
        `/api/groups/${groupId}/bidding/approve-winner`,
        {
          method: 'POST',
          body: JSON.stringify({
            monthNumber: Number(month),
            winnerMemberId: selected.memberId,
            winnerGroupMemberId: selected.groupMemberId,
            winningBidAmount: 0,
            boliAmount: hasBoli ? Number(boli) : undefined,
            useRandomAmount: !hasBoli,
          }),
        },
        user.accessToken,
      )
      setMessage(`${selected.memberName} approved · payments created.`)
      setSelectedSeatId(null)
      setBoli('')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Approve failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Bidding"
        subtitle={data?.groupName ?? `Group ${groupId}`}
        onBack={() => navigation.goBack()}
      />
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content}>
          {error ? <ErrorBanner message={error} /> : null}
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          <Text style={styles.hint}>
            Collection {formatInr(data?.totalMonthlyCollection ?? 0)}. Pick winner from roster after
            the call.
          </Text>

          <Text style={styles.label}>Month</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chips}>
            {(data?.months ?? [])
              .filter((m) => m.monthNumber >= 2)
              .map((m) => (
                <Pressable
                  key={m.monthNumber}
                  style={[styles.chip, Number(month) === m.monthNumber && styles.chipOn]}
                  onPress={() => setMonth(String(m.monthNumber))}
                >
                  <Text
                    style={[styles.chipText, Number(month) === m.monthNumber && styles.chipTextOn]}
                  >
                    M{m.monthNumber} · {m.biddingStatus}
                  </Text>
                </Pressable>
              ))}
          </ScrollView>

          <Text style={styles.label}>Bidding end date (open)</Text>
          <TextInput
            style={styles.input}
            value={endDate}
            onChangeText={setEndDate}
            placeholder="YYYY-MM-DD"
            placeholderTextColor={COLORS.muted}
          />
          <View style={styles.row}>
            <Pressable
              style={[styles.secondaryBtn, { flex: 1 }]}
              disabled={saving || !month}
              onPress={() => void openBidding()}
            >
              <Text style={styles.secondaryBtnText}>Open bidding</Text>
            </Pressable>
            <Pressable
              style={[styles.secondaryBtn, { flex: 1 }]}
              disabled={saving || !month || monthMeta?.biddingStatus !== 'open'}
              onPress={() => void closeBidding()}
            >
              <Text style={styles.secondaryBtnText}>Close</Text>
            </Pressable>
          </View>

          <Text style={[styles.label, { marginTop: 14 }]}>Winner (tap seat)</Text>
          {roster.map((s) => {
            const on = selectedSeatId === s.groupMemberId
            return (
              <Pressable
                key={s.groupMemberId}
                style={[styles.seat, on && styles.seatOn]}
                onPress={() => setSelectedSeatId(s.groupMemberId)}
              >
                <Text style={[styles.seatName, on && styles.seatNameOn]}>
                  #{s.memberNumber} {s.memberName}
                  {s.handLabel ? ` · ${s.handLabel}` : ''}
                </Text>
              </Pressable>
            )
          })}

          <Text style={styles.label}>Boli / receive amount (optional)</Text>
          <TextInput
            style={styles.input}
            keyboardType="number-pad"
            value={boli}
            onChangeText={setBoli}
            placeholder="Leave empty to use chart random"
            placeholderTextColor={COLORS.muted}
          />

          <Pressable
            style={styles.btn}
            disabled={saving || !month || !selected}
            onPress={() => void approve()}
          >
            {saving ? (
              <ActivityIndicator color={COLORS.white} />
            ) : (
              <Text style={styles.btnText}>
                Approve {selected ? selected.memberName : 'winner'}
              </Text>
            )}
          </Pressable>
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, gap: 6, paddingBottom: 40 },
  hint: { fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted, marginBottom: 8 },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  chips: { marginVertical: 4 },
  chip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginRight: 8,
    backgroundColor: COLORS.white,
  },
  chipOn: { backgroundColor: COLORS.tealSoft, borderColor: COLORS.teal },
  chipText: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  chipTextOn: { color: COLORS.teal },
  row: { flexDirection: 'row', gap: 8, marginTop: 8 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, color: COLORS.text, fontSize: 13 },
  seat: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginTop: 6,
  },
  seatOn: { borderColor: COLORS.teal, backgroundColor: COLORS.tealSoft },
  seatName: { fontFamily: FONTS.bodyMed, color: COLORS.text },
  seatNameOn: { color: COLORS.teal, fontFamily: FONTS.bodyBold },
  btn: {
    marginTop: 16,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
