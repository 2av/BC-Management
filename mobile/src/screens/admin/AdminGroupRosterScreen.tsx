import { useCallback, useMemo, useState } from 'react'
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useFocusEffect, useNavigation, useRoute } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import type { RouteProp } from '@react-navigation/native'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>
type Route = RouteProp<RootStackParamList, 'AdminGroupRoster'>

type Seat = {
  groupMemberId: number
  memberId: number
  memberName: string
  username: string | null
  phone: string | null
  memberNumber: number
  handLabel: string | null
  status: string
}

type DirectoryMember = {
  id: number
  memberName: string
  username: string | null
  phone: string | null
  status: string
}

type ImportResult = {
  imported: number
  skipped: number
  errors: string[]
}

export function AdminGroupRosterScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const groupId = route.params.groupId
  const [roster, setRoster] = useState<Seat[]>([])
  const [directory, setDirectory] = useState<DirectoryMember[]>([])
  const [groupName, setGroupName] = useState(`Group ${groupId}`)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [newName, setNewName] = useState('')
  const [search, setSearch] = useState('')
  const [csvText, setCsvText] = useState(
    'member_name,member_number,username,phone,email,address\n',
  )
  const [showImport, setShowImport] = useState(false)
  const [showPick, setShowPick] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const [seats, members, groups] = await Promise.all([
        apiFetch<Seat[]>(`/api/groups/${groupId}/members`, {}, user.accessToken),
        apiFetch<DirectoryMember[]>('/api/members', {}, user.accessToken),
        apiFetch<{ id: number; groupName: string }[]>('/api/groups', {}, user.accessToken),
      ])
      setRoster(seats)
      setDirectory(members.filter((m) => m.status === 'active'))
      const g = groups.find((x) => x.id === groupId)
      if (g) setGroupName(g.groupName)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load roster')
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

  const filteredDirectory = useMemo(() => {
    const q = search.trim().toLowerCase()
    return directory
      .filter((m) => {
        if (!q) return true
        return (
          m.memberName.toLowerCase().includes(q) ||
          (m.username ?? '').toLowerCase().includes(q) ||
          (m.phone ?? '').includes(q)
        )
      })
      .slice(0, 40)
  }, [directory, search])

  async function addNewMember() {
    if (!user?.accessToken || !newName.trim()) {
      setError('Enter a member name.')
      return
    }
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        `/api/groups/${groupId}/members`,
        { method: 'POST', body: JSON.stringify({ memberName: newName.trim(), addHand: false }) },
        user.accessToken,
      )
      setNewName('')
      setMessage('Member added.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Add failed')
    } finally {
      setSaving(false)
    }
  }

  async function assignExisting(memberId: number) {
    if (!user?.accessToken) return
    const already = roster.some((s) => s.memberId === memberId && s.status === 'active')
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        `/api/groups/${groupId}/members`,
        {
          method: 'POST',
          body: JSON.stringify({ memberId, addHand: already }),
        },
        user.accessToken,
      )
      setMessage(already ? 'Extra hand added.' : 'Member assigned.')
      setShowPick(false)
      setSearch('')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Assign failed')
    } finally {
      setSaving(false)
    }
  }

  function confirmRemove(seat: Seat) {
    Alert.alert(
      'Remove seat?',
      `#${seat.memberNumber} ${seat.memberName}${seat.handLabel ? ` (${seat.handLabel})` : ''}`,
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Remove', style: 'destructive', onPress: () => void removeSeat(seat) },
      ],
    )
  }

  async function removeSeat(seat: Seat) {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        `/api/groups/${groupId}/members/${seat.memberId}?groupMemberId=${seat.groupMemberId}`,
        { method: 'DELETE' },
        user.accessToken,
      )
      setMessage('Seat removed.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Remove failed')
    } finally {
      setSaving(false)
    }
  }

  async function runImport() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const res = await apiFetch<ImportResult>(
        `/api/groups/${groupId}/members/import`,
        {
          method: 'POST',
          body: JSON.stringify({ csvContent: csvText, skipDuplicates: true }),
        },
        user.accessToken,
      )
      setMessage(`Import: ${res.imported} added, ${res.skipped} skipped.`)
      if (res.errors.length) setError(res.errors.slice(0, 5).join('\n'))
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Import failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Roster" subtitle={groupName} onBack={() => navigation.goBack()} />
      {loading && roster.length === 0 ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
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
          {message ? <Text style={styles.ok}>{message}</Text> : null}

          <Text style={styles.section}>Seats ({roster.length})</Text>
          {roster.map((s) => (
            <SoftCard key={s.groupMemberId} style={styles.seatCard}>
              <View style={styles.seatRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.name}>
                    #{s.memberNumber} {s.memberName}
                    {s.handLabel ? ` · ${s.handLabel}` : ''}
                  </Text>
                  <Text style={styles.muted}>
                    {s.username ? `@${s.username}` : 'no username'}
                    {s.phone ? ` · ${s.phone}` : ''} · {s.status}
                  </Text>
                </View>
                <View style={styles.seatActions}>
                  <Pressable
                    style={styles.invoiceBtn}
                    onPress={() =>
                      navigation.navigate('Invoice', { groupId, memberId: s.memberId })
                    }
                  >
                    <Text style={styles.invoiceText}>Invoice</Text>
                  </Pressable>
                  {s.status === 'active' ? (
                    <Pressable
                      style={styles.removeBtn}
                      onPress={() => confirmRemove(s)}
                      disabled={saving}
                    >
                      <Text style={styles.removeText}>Remove</Text>
                    </Pressable>
                  ) : null}
                </View>
              </View>
            </SoftCard>
          ))}

          <Text style={styles.section}>Add new member</Text>
          <TextInput
            style={styles.input}
            value={newName}
            onChangeText={setNewName}
            placeholder="Full name"
            placeholderTextColor={COLORS.muted}
          />
          <Pressable style={styles.btn} disabled={saving} onPress={() => void addNewMember()}>
            <Text style={styles.btnText}>Create & assign</Text>
          </Pressable>

          <Pressable
            style={[styles.secondaryBtn, { marginTop: 10 }]}
            onPress={() => setShowPick((v) => !v)}
          >
            <Text style={styles.secondaryBtnText}>
              {showPick ? 'Hide directory' : 'Assign from directory'}
            </Text>
          </Pressable>
          {showPick ? (
            <View style={styles.block}>
              <TextInput
                style={styles.input}
                value={search}
                onChangeText={setSearch}
                placeholder="Search name / username / phone"
                placeholderTextColor={COLORS.muted}
              />
              {filteredDirectory.map((m) => (
                <Pressable
                  key={m.id}
                  style={styles.pickRow}
                  disabled={saving}
                  onPress={() => void assignExisting(m.id)}
                >
                  <Text style={styles.name}>{m.memberName}</Text>
                  <Text style={styles.muted}>
                    {m.username ? `@${m.username}` : ''}
                    {m.phone ? ` · ${m.phone}` : ''}
                  </Text>
                </Pressable>
              ))}
            </View>
          ) : null}

          <Pressable
            style={[styles.secondaryBtn, { marginTop: 14 }]}
            onPress={() => setShowImport((v) => !v)}
          >
            <Text style={styles.secondaryBtnText}>
              {showImport ? 'Hide CSV import' : 'CSV import'}
            </Text>
          </Pressable>
          {showImport ? (
            <View style={styles.block}>
              <Text style={styles.hint}>
                Header required: member_name,member_number,username,phone,email,address
              </Text>
              <TextInput
                style={[styles.input, styles.area]}
                multiline
                value={csvText}
                onChangeText={setCsvText}
                placeholderTextColor={COLORS.muted}
              />
              <Pressable style={styles.btn} disabled={saving} onPress={() => void runImport()}>
                {saving ? (
                  <ActivityIndicator color={COLORS.white} />
                ) : (
                  <Text style={styles.btnText}>Import CSV</Text>
                )}
              </Pressable>
            </View>
          ) : null}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 8 },
  section: {
    marginTop: 12,
    fontFamily: FONTS.bodyBold,
    fontSize: 14,
    color: COLORS.teal,
  },
  seatCard: { marginBottom: 0 },
  seatRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  seatActions: { gap: 6, alignItems: 'flex-end' },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 2, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  invoiceBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    backgroundColor: COLORS.white,
  },
  invoiceText: { color: COLORS.teal, fontFamily: FONTS.bodyBold, fontSize: 12 },
  removeBtn: {
    borderWidth: 1,
    borderColor: '#FECACA',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  removeText: { color: COLORS.danger, fontFamily: FONTS.bodyBold, fontSize: 12 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  area: { minHeight: 140, textAlignVertical: 'top', fontFamily: FONTS.body, fontSize: 12 },
  btn: {
    marginTop: 8,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, color: COLORS.text },
  block: { gap: 8, marginTop: 8 },
  pickRow: {
    backgroundColor: COLORS.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 12,
  },
  hint: { fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted, lineHeight: 18 },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
