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
type Route = RouteProp<RootStackParamList, 'AdminEditGroup'>

type GroupItem = {
  id: number
  groupName: string
  monthlyContribution: number
  startDate: string
  status: string
}

export function AdminEditGroupScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const route = useRoute<Route>()
  const groupId = route.params.groupId
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [groupName, setGroupName] = useState('')
  const [monthly, setMonthly] = useState('')
  const [startDate, setStartDate] = useState('')
  const [status, setStatus] = useState('active')

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const list = await apiFetch<GroupItem[]>('/api/groups', {}, user.accessToken)
      const g = list.find((x) => x.id === groupId)
      if (!g) throw new Error('Group not found')
      setGroupName(g.groupName)
      setMonthly(String(g.monthlyContribution))
      setStartDate(g.startDate.slice(0, 10))
      setStatus(g.status)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken, groupId])

  useEffect(() => {
    void load()
  }, [load])

  async function save() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      await apiFetch(
        `/api/groups/${groupId}`,
        {
          method: 'PUT',
          body: JSON.stringify({
            groupName: groupName.trim(),
            startDate,
            status,
            monthlyContribution: Number(monthly),
          }),
        },
        user.accessToken,
      )
      setMessage('Group updated.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Edit group" subtitle={`#${groupId}`} onBack={() => navigation.goBack()} />
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <ErrorBanner message={error} /> : null}
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          <Text style={styles.label}>Name</Text>
          <TextInput style={styles.input} value={groupName} onChangeText={setGroupName} />
          <Text style={styles.label}>Monthly contribution</Text>
          <TextInput
            style={styles.input}
            keyboardType="number-pad"
            value={monthly}
            onChangeText={setMonthly}
          />
          <Text style={styles.hint}>Current: {formatInr(Number(monthly) || 0)}</Text>
          <Text style={styles.label}>Start date</Text>
          <TextInput style={styles.input} value={startDate} onChangeText={setStartDate} />
          <Text style={styles.label}>Status</Text>
          <View style={styles.row}>
            {['active', 'completed', 'inactive'].map((s) => (
              <Pressable
                key={s}
                style={[styles.chip, status === s && styles.chipOn]}
                onPress={() => setStatus(s)}
              >
                <Text style={[styles.chipText, status === s && styles.chipTextOn]}>{s}</Text>
              </Pressable>
            ))}
          </View>
          <Pressable style={styles.btn} disabled={saving} onPress={() => void save()}>
            {saving ? (
              <ActivityIndicator color={COLORS.white} />
            ) : (
              <Text style={styles.btnText}>Save changes</Text>
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
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  hint: { fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 6 },
  chip: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: COLORS.white,
  },
  chipOn: { backgroundColor: COLORS.tealSoft, borderColor: COLORS.teal },
  chipText: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  chipTextOn: { color: COLORS.teal },
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
