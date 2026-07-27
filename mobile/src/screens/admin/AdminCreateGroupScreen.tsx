import { useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader } from '../../components/ui'
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

export function AdminCreateGroupScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [groupName, setGroupName] = useState('')
  const [totalMembers, setTotalMembers] = useState('10')
  const [monthly, setMonthly] = useState('5000')
  const [startDate, setStartDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [namesText, setNamesText] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  async function create() {
    if (!user?.accessToken) return
    const seats = Number(totalMembers)
    const contrib = Number(monthly)
    if (!groupName.trim()) {
      setError('Group name is required.')
      return
    }
    if (!Number.isFinite(seats) || seats < 2) {
      setError('Total members must be at least 2.')
      return
    }
    if (!Number.isFinite(contrib) || contrib <= 0) {
      setError('Monthly contribution must be > 0.')
      return
    }
    const memberNames = namesText
      .split(/\n|,/)
      .map((s) => s.trim())
      .filter(Boolean)
    if (memberNames.length !== seats) {
      setError(`Enter exactly ${seats} member names (one per line).`)
      return
    }

    setSaving(true)
    setError(null)
    try {
      const created = await apiFetch<{ id: number }>(
        '/api/groups',
        {
          method: 'POST',
          body: JSON.stringify({
            groupName: groupName.trim(),
            totalMembers: seats,
            monthlyContribution: contrib,
            startDate,
            memberNames,
            organiserSlotIndex: 0,
          }),
        },
        user.accessToken,
      )
      navigation.replace('GroupLedger', { groupId: created.id })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Create failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title="Create group" subtitle="Basic setup" onBack={() => navigation.goBack()} />
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {error ? <ErrorBanner message={error} /> : null}
        <Text style={styles.label}>Group name</Text>
        <TextInput style={styles.input} value={groupName} onChangeText={setGroupName} />
        <Text style={styles.label}>Total seats</Text>
        <TextInput
          style={styles.input}
          keyboardType="number-pad"
          value={totalMembers}
          onChangeText={setTotalMembers}
        />
        <Text style={styles.label}>Monthly contribution</Text>
        <TextInput
          style={styles.input}
          keyboardType="number-pad"
          value={monthly}
          onChangeText={setMonthly}
        />
        <Text style={styles.label}>Start date</Text>
        <TextInput
          style={styles.input}
          value={startDate}
          onChangeText={setStartDate}
          placeholder="YYYY-MM-DD"
          placeholderTextColor={COLORS.muted}
        />
        <Text style={styles.label}>Member names</Text>
        <Text style={styles.hint}>Required — one name per line. First name = Month 1 organiser.</Text>
        <TextInput
          style={[styles.input, styles.area]}
          multiline
          value={namesText}
          onChangeText={setNamesText}
          placeholder={'Ramesh\nSuresh\n...'}
          placeholderTextColor={COLORS.muted}
        />
        <Pressable style={styles.btn} disabled={saving} onPress={() => void create()}>
          {saving ? (
            <ActivityIndicator color={COLORS.white} />
          ) : (
            <Text style={styles.btnText}>Create group</Text>
          )}
        </Pressable>
      </ScrollView>
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
  area: { minHeight: 120, textAlignVertical: 'top' },
  btn: {
    marginTop: 16,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
})
