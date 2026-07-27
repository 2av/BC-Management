import { useCallback, useEffect, useState } from 'react'
import {
  ActivityIndicator,
  Alert,
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

type Route = RouteProp<RootStackParamList, 'AdminBcChart'>

type ChartMonth = {
  monthNumber: number
  randomAmount: number
  boliStartAmount: number | null
}

type Chart = {
  groupId: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  totalMonthlyCollection: number
  boliStepAmount: number
  months: ChartMonth[]
}

type RowEdit = {
  monthNumber: number
  randomAmount: string
  boliStartAmount: string
}

export function AdminBcChartScreen() {
  const { user } = useAuth()
  const navigation = useNavigation()
  const route = useRoute<Route>()
  const groupId = route.params.groupId
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [meta, setMeta] = useState<Omit<Chart, 'months' | 'boliStepAmount'> | null>(null)
  const [step, setStep] = useState('1000')
  const [rows, setRows] = useState<RowEdit[]>([])

  const applyChart = useCallback((next: Chart) => {
    setMeta({
      groupId: next.groupId,
      groupName: next.groupName,
      totalMembers: next.totalMembers,
      monthlyContribution: next.monthlyContribution,
      totalMonthlyCollection: next.totalMonthlyCollection,
    })
    setStep(String(next.boliStepAmount))
    setRows(
      next.months.map((m) => ({
        monthNumber: m.monthNumber,
        randomAmount: String(m.randomAmount),
        boliStartAmount: m.boliStartAmount == null ? '' : String(m.boliStartAmount),
      })),
    )
  }, [])

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<Chart>(`/api/groups/${groupId}/bc-chart`, {}, user.accessToken)
      applyChart(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load BC chart')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken, groupId, applyChart])

  useEffect(() => {
    void load()
  }, [load])

  async function save() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const next = await apiFetch<Chart>(
        `/api/groups/${groupId}/bc-chart`,
        {
          method: 'PUT',
          body: JSON.stringify({
            boliStepAmount: Number(step),
            months: rows.map((r) => ({
              monthNumber: r.monthNumber,
              randomAmount: Number(r.randomAmount),
              boliStartAmount: r.boliStartAmount.trim() === '' ? null : Number(r.boliStartAmount),
            })),
          }),
        },
        user.accessToken,
      )
      applyChart(next)
      setMessage('BC chart saved.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  function confirmGenerate() {
    Alert.alert('Fill defaults?', 'Reset all months with the default chart pattern for this group.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Fill', onPress: () => void generate() },
    ])
  }

  async function generate() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const next = await apiFetch<Chart>(
        `/api/groups/${groupId}/bc-chart/generate-defaults`,
        { method: 'POST' },
        user.accessToken,
      )
      applyChart(next)
      setMessage('Defaults filled — edit and save if needed.')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Generate failed')
    } finally {
      setSaving(false)
    }
  }

  function updateRow(monthNumber: number, patch: Partial<RowEdit>) {
    setRows((prev) => prev.map((r) => (r.monthNumber === monthNumber ? { ...r, ...patch } : r)))
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="BC Chart"
        subtitle={meta?.groupName ?? `Group ${groupId}`}
        onBack={() => navigation.goBack()}
      />
      {loading ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <ErrorBanner message={error} /> : null}
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {meta ? (
            <Text style={styles.hint}>
              {meta.totalMembers} seats · {formatInr(meta.monthlyContribution)} / mo · collection{' '}
              {formatInr(meta.totalMonthlyCollection)}
            </Text>
          ) : null}

          <Text style={styles.label}>Boli deduction step (₹)</Text>
          <TextInput
            style={styles.input}
            keyboardType="number-pad"
            value={step}
            onChangeText={setStep}
          />

          <View style={styles.row}>
            <Pressable
              style={[styles.secondaryBtn, { flex: 1 }]}
              disabled={saving}
              onPress={confirmGenerate}
            >
              <Text style={styles.secondaryBtnText}>Fill defaults</Text>
            </Pressable>
            <Pressable
              style={[styles.btn, { flex: 1, marginTop: 0 }]}
              disabled={saving}
              onPress={() => void save()}
            >
              {saving ? (
                <ActivityIndicator color={COLORS.white} />
              ) : (
                <Text style={styles.btnText}>Save chart</Text>
              )}
            </Pressable>
          </View>

          <Text style={[styles.label, { marginTop: 14 }]}>Month-wise amounts</Text>
          {rows.map((r) => (
            <View key={r.monthNumber} style={styles.monthCard}>
              <Text style={styles.monthTitle}>Month {r.monthNumber}</Text>
              <Text style={styles.fieldLabel}>Random receive</Text>
              <TextInput
                style={styles.input}
                keyboardType="number-pad"
                value={r.randomAmount}
                onChangeText={(v) => updateRow(r.monthNumber, { randomAmount: v })}
              />
              <Text style={styles.fieldLabel}>Boli start (optional)</Text>
              <TextInput
                style={styles.input}
                keyboardType="number-pad"
                value={r.boliStartAmount}
                onChangeText={(v) => updateRow(r.monthNumber, { boliStartAmount: v })}
                placeholder="Blank = no fixed start"
                placeholderTextColor={COLORS.muted}
              />
            </View>
          ))}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 6 },
  hint: { fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted, marginBottom: 8 },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  fieldLabel: { marginTop: 8, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  row: { flexDirection: 'row', gap: 8, marginTop: 10 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, color: COLORS.text, fontSize: 13 },
  btn: {
    marginTop: 16,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  monthCard: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 14,
    padding: 12,
    backgroundColor: COLORS.white,
  },
  monthTitle: { fontFamily: FONTS.bodyBold, color: COLORS.text, marginBottom: 4 },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
