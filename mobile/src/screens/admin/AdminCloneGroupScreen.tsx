import { useCallback, useEffect, useState } from 'react'
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
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>
type Route = RouteProp<RootStackParamList, 'AdminCloneGroup'>

type Seat = {
  groupMemberId: number
  memberId: number
  memberName: string
  memberNumber: number
  handLabel: string | null
  status: string
}

type GroupItem = {
  id: number
  groupName: string
  monthlyContribution: number
  totalMembers: number
}

export function AdminCloneGroupScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const sourceGroupId = route.params.groupId
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [source, setSource] = useState<GroupItem | null>(null)
  const [roster, setRoster] = useState<Seat[]>([])
  const [selected, setSelected] = useState<Record<number, boolean>>({})
  const [newName, setNewName] = useState('')
  const [startDate, setStartDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [extraNamesText, setExtraNamesText] = useState('')

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [groups, seats] = await Promise.all([
        apiFetch<GroupItem[]>('/api/groups', {}, user.accessToken),
        apiFetch<Seat[]>(`/api/groups/${sourceGroupId}/members`, {}, user.accessToken),
      ])
      const g = groups.find((x) => x.id === sourceGroupId) ?? null
      setSource(g)
      setNewName(g ? `${g.groupName} (copy)` : 'New group')
      const active = seats.filter((s) => s.status === 'active')
      setRoster(active)
      const next: Record<number, boolean> = {}
      for (const s of active) next[s.memberId] = true
      setSelected(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken, sourceGroupId])

  useEffect(() => {
    void load()
  }, [load])

  function toggle(memberId: number) {
    setSelected((prev) => ({ ...prev, [memberId]: !prev[memberId] }))
  }

  async function clone() {
    if (!user?.accessToken) return
    const selectedMemberIds = [
      ...new Set(roster.filter((s) => selected[s.memberId]).map((s) => s.memberId)),
    ]
    const newMemberNames = extraNamesText
      .split(/\n|,/)
      .map((s) => s.trim())
      .filter(Boolean)

    if (!newName.trim()) {
      setError('New group name is required.')
      return
    }
    if (selectedMemberIds.length + newMemberNames.length < 2) {
      setError('Select or add at least 2 members.')
      return
    }

    setSaving(true)
    setError(null)
    try {
      const created = await apiFetch<{ id: number }>(
        `/api/groups/${sourceGroupId}/clone`,
        {
          method: 'POST',
          body: JSON.stringify({
            newGroupName: newName.trim(),
            startDate,
            selectedMemberIds,
            newMemberNames,
          }),
        },
        user.accessToken,
      )
      navigation.replace('GroupLedger', { groupId: created.id })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Clone failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Clone group"
        subtitle={source?.groupName ?? `Group ${sourceGroupId}`}
        onBack={() => navigation.goBack()}
      />
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <ErrorBanner message={error} /> : null}
          {source ? (
            <Text style={styles.hint}>
              Copies contribution {formatInr(source.monthlyContribution)} · pick members from source
              roster or add new names.
            </Text>
          ) : null}

          <Text style={styles.label}>New group name</Text>
          <TextInput style={styles.input} value={newName} onChangeText={setNewName} />

          <Text style={styles.label}>Start date</Text>
          <TextInput
            style={styles.input}
            value={startDate}
            onChangeText={setStartDate}
            placeholder="YYYY-MM-DD"
            placeholderTextColor={COLORS.muted}
          />

          <Text style={[styles.label, { marginTop: 12 }]}>Members from source</Text>
          {roster.map((s) => {
            const on = Boolean(selected[s.memberId])
            return (
              <Pressable
                key={s.groupMemberId}
                style={[styles.seat, on && styles.seatOn]}
                onPress={() => toggle(s.memberId)}
              >
                <Text style={[styles.seatName, on && styles.seatNameOn]}>
                  {on ? '✓ ' : ''}#{s.memberNumber} {s.memberName}
                  {s.handLabel ? ` · ${s.handLabel}` : ''}
                </Text>
              </Pressable>
            )
          })}

          <Text style={styles.label}>Extra new names (optional)</Text>
          <Text style={styles.hint}>One per line — creates new member accounts.</Text>
          <TextInput
            style={[styles.input, styles.area]}
            multiline
            value={extraNamesText}
            onChangeText={setExtraNamesText}
            placeholder={'New member A\nNew member B'}
            placeholderTextColor={COLORS.muted}
          />

          <Pressable style={styles.btn} disabled={saving} onPress={() => void clone()}>
            {saving ? (
              <ActivityIndicator color={COLORS.white} />
            ) : (
              <Text style={styles.btnText}>Clone group</Text>
            )}
          </Pressable>
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 6 },
  hint: { fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted, marginBottom: 6, lineHeight: 18 },
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
  area: { minHeight: 90, textAlignVertical: 'top' },
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
})
