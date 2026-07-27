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
import { useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

type Setting = {
  key: string
  value: string
  type: string
  category: string
  description: string | null
}

type Response = {
  settingsByCategory: Record<string, Setting[]>
  stats: {
    totalGroups: number
    totalMembers: number
    totalCollected: number
    totalPayments: number
    totalAdmins: number
  }
}

type SchemaIssue = { kind: string; objectName: string; detail: string; canAutoFix: boolean }
type SchemaCheck = { isUpToDate: boolean; issues: SchemaIssue[]; notes: string[] }
type SchemaMigrate = {
  succeeded: boolean
  appliedCount: number
  applied: string[]
  skipped: string[]
  errors: string[]
}

export function AdminSettingsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [schemaBusy, setSchemaBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [data, setData] = useState<Response | null>(null)
  const [values, setValues] = useState<Record<string, string>>({})
  const [schema, setSchema] = useState<SchemaCheck | null>(null)
  const [migrateResult, setMigrateResult] = useState<SchemaMigrate | null>(null)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<Response>('/api/settings', {}, user.accessToken)
      setData(next)
      const merged: Record<string, string> = {}
      for (const cat of Object.values(next.settingsByCategory)) {
        for (const s of cat) merged[s.key] = s.value
      }
      setValues(merged)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load settings')
    } finally {
      setLoading(false)
    }
  }, [user?.accessToken])

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
        '/api/settings',
        { method: 'PUT', body: JSON.stringify({ settings: values }) },
        user.accessToken,
      )
      setMessage('Settings saved.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  async function checkSchema() {
    if (!user?.accessToken) return
    setSchemaBusy(true)
    setError(null)
    setMessage(null)
    try {
      const next = await apiFetch<SchemaCheck>('/api/settings/schema', {}, user.accessToken)
      setSchema(next)
      setMessage(next.isUpToDate ? 'Schema is up to date.' : `${next.issues.length} schema issue(s).`)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Schema check failed')
    } finally {
      setSchemaBusy(false)
    }
  }

  async function migrateSchema() {
    if (!user?.accessToken) return
    setSchemaBusy(true)
    setError(null)
    setMessage(null)
    setMigrateResult(null)
    try {
      const next = await apiFetch<SchemaMigrate>(
        '/api/settings/schema/migrate',
        { method: 'POST' },
        user.accessToken,
      )
      setMigrateResult(next)
      setMessage(
        next.succeeded
          ? `Migrated ${next.appliedCount} change(s).`
          : 'Migrate finished with errors.',
      )
      await checkSchema()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Migrate failed')
    } finally {
      setSchemaBusy(false)
    }
  }

  const categories = data ? Object.entries(data.settingsByCategory) : []

  return (
    <View style={styles.root}>
      <ScreenHeader title="Settings" subtitle="Organisation defaults" onBack={() => navigation.goBack()} />
      {loading && !data ? (
        <ActivityIndicator style={{ marginTop: 40 }} color={COLORS.teal} />
      ) : (
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {error ? <ErrorBanner message={error} /> : null}
          {message ? <Text style={styles.ok}>{message}</Text> : null}

          {data ? (
            <View style={styles.stats}>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{data.stats.totalGroups}</Text>
                <Text style={styles.statLabel}>Groups</Text>
              </SoftCard>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{data.stats.totalMembers}</Text>
                <Text style={styles.statLabel}>Members</Text>
              </SoftCard>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{formatInr(data.stats.totalCollected)}</Text>
                <Text style={styles.statLabel}>Collected</Text>
              </SoftCard>
              <SoftCard style={styles.stat}>
                <Text style={styles.statVal}>{data.stats.totalPayments}</Text>
                <Text style={styles.statLabel}>Payments</Text>
              </SoftCard>
            </View>
          ) : null}

          <SoftCard>
            <Text style={styles.sectionTitle}>Database schema</Text>
            <Text style={styles.hint}>
              Check missing tables/columns, then apply safe auto-fixes.
            </Text>
            <View style={styles.schemaRow}>
              <Pressable
                style={[styles.secondaryBtn, { flex: 1 }]}
                disabled={schemaBusy}
                onPress={() => void checkSchema()}
              >
                <Text style={styles.secondaryBtnText}>
                  {schemaBusy ? 'Working…' : 'Check schema'}
                </Text>
              </Pressable>
              <Pressable
                style={[styles.btn, { flex: 1, marginTop: 0 }]}
                disabled={schemaBusy}
                onPress={() => void migrateSchema()}
              >
                <Text style={styles.btnText}>Migrate</Text>
              </Pressable>
            </View>
            {schema ? (
              <Text style={styles.meta}>
                {schema.isUpToDate
                  ? 'Up to date'
                  : `${schema.issues.length} issue(s)${
                      schema.issues.some((i) => i.canAutoFix) ? ' · some auto-fixable' : ''
                    }`}
              </Text>
            ) : null}
            {schema?.issues.slice(0, 8).map((issue, i) => (
              <Text key={`${issue.objectName}-${i}`} style={styles.issue}>
                {issue.kind}: {issue.objectName} — {issue.detail}
              </Text>
            ))}
            {migrateResult ? (
              <Text style={styles.meta}>
                Applied {migrateResult.appliedCount}
                {migrateResult.errors.length ? ` · ${migrateResult.errors.length} error(s)` : ''}
              </Text>
            ) : null}
          </SoftCard>

          {categories.map(([cat, items]) => (
            <View key={cat} style={styles.section}>
              <Text style={styles.sectionTitle}>{cat}</Text>
              {items.map((s) => (
                <View key={s.key} style={styles.field}>
                  <Text style={styles.label}>{s.key}</Text>
                  {s.description ? <Text style={styles.hint}>{s.description}</Text> : null}
                  <TextInput
                    style={styles.input}
                    value={values[s.key] ?? s.value}
                    onChangeText={(v) => setValues((prev) => ({ ...prev, [s.key]: v }))}
                    autoCapitalize="none"
                  />
                </View>
              ))}
            </View>
          ))}

          <Pressable style={styles.btn} disabled={saving} onPress={() => void save()}>
            {saving ? (
              <ActivityIndicator color={COLORS.white} />
            ) : (
              <Text style={styles.btnText}>Save settings</Text>
            )}
          </Pressable>
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 8 },
  stats: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 8 },
  stat: { width: '48%', marginBottom: 0, flexGrow: 1 },
  statVal: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  statLabel: { marginTop: 4, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  section: { marginTop: 8 },
  sectionTitle: {
    fontFamily: FONTS.bodyBold,
    fontSize: 14,
    color: COLORS.teal,
    textTransform: 'capitalize',
    marginBottom: 6,
  },
  field: { marginBottom: 10 },
  label: { fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  hint: { fontFamily: FONTS.body, fontSize: 11, color: COLORS.muted, marginBottom: 4 },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: COLORS.white,
    color: COLORS.text,
  },
  schemaRow: { flexDirection: 'row', gap: 8, marginTop: 8 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.white,
    borderRadius: 12,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.text },
  meta: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.teal },
  issue: { marginTop: 4, fontFamily: FONTS.body, fontSize: 11, color: COLORS.muted, lineHeight: 16 },
  btn: {
    marginTop: 12,
    backgroundColor: COLORS.teal,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
  },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
