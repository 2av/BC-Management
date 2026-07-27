import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native'
import { useFocusEffect } from '@react-navigation/native'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import { formatInr } from '../../types'

type Plan = {
  id: number
  planName: string
  durationMonths: number
  price: number
  description: string | null
  features: string[]
  isActive: boolean
  isPromotional: boolean
  promotionalDiscount: number
  maxGroups: number | null
  maxMembersPerGroup: number | null
}

const emptyForm = {
  planName: '',
  durationMonths: '1',
  price: '',
  description: '',
  features: '',
  isActive: true,
  isPromotional: false,
  promotionalDiscount: '0',
  maxGroups: '',
  maxMembersPerGroup: '',
}

export function SuperAdminPlansScreen() {
  const { user } = useAuth()
  const [plans, setPlans] = useState<Plan[]>([])
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [showForm, setShowForm] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<Plan[]>('/api/super-admin/plans', {}, user.accessToken)
      setPlans(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load plans')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [user?.accessToken])

  useFocusEffect(
    useCallback(() => {
      setLoading(true)
      void load()
    }, [load]),
  )

  function startCreate() {
    setEditingId(null)
    setForm(emptyForm)
    setShowForm(true)
  }

  function startEdit(p: Plan) {
    setEditingId(p.id)
    setForm({
      planName: p.planName,
      durationMonths: String(p.durationMonths),
      price: String(p.price),
      description: p.description ?? '',
      features: (p.features ?? []).join('\n'),
      isActive: p.isActive,
      isPromotional: p.isPromotional,
      promotionalDiscount: String(p.promotionalDiscount ?? 0),
      maxGroups: p.maxGroups != null ? String(p.maxGroups) : '',
      maxMembersPerGroup: p.maxMembersPerGroup != null ? String(p.maxMembersPerGroup) : '',
    })
    setShowForm(true)
  }

  async function save() {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    setMessage(null)
    try {
      const body = {
        planName: form.planName.trim(),
        durationMonths: Number(form.durationMonths),
        price: Number(form.price),
        description: form.description.trim() || null,
        features: form.features.split('\n').map((s) => s.trim()).filter(Boolean),
        isActive: form.isActive,
        isPromotional: form.isPromotional,
        promotionalDiscount: Number(form.promotionalDiscount) || 0,
        maxGroups: form.maxGroups ? Number(form.maxGroups) : null,
        maxMembersPerGroup: form.maxMembersPerGroup ? Number(form.maxMembersPerGroup) : null,
      }
      if (editingId) {
        await apiFetch(`/api/super-admin/plans/${editingId}`, {
          method: 'PUT',
          body: JSON.stringify(body),
        }, user.accessToken)
        setMessage('Plan updated.')
      } else {
        await apiFetch('/api/super-admin/plans', {
          method: 'POST',
          body: JSON.stringify(body),
        }, user.accessToken)
        setMessage('Plan created.')
      }
      setShowForm(false)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Save failed')
    } finally {
      setSaving(false)
    }
  }

  async function deactivate(id: number) {
    if (!user?.accessToken) return
    setSaving(true)
    setError(null)
    try {
      await apiFetch(`/api/super-admin/plans/${id}`, { method: 'DELETE' }, user.accessToken)
      setMessage('Plan deactivated.')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Deactivate failed')
    } finally {
      setSaving(false)
    }
  }

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Plans"
        subtitle="Subscription packages"
        right={
          <Pressable style={styles.addBtn} onPress={startCreate}>
            <Text style={styles.addBtnText}>+ New</Text>
          </Pressable>
        }
      />
      {loading && plans.length === 0 ? (
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
          {message ? <Text style={styles.ok}>{message}</Text> : null}
          {error ? <ErrorBanner message={error} /> : null}

          {showForm ? (
            <SoftCard>
              <Text style={styles.formTitle}>{editingId ? 'Edit plan' : 'New plan'}</Text>
              <Text style={styles.label}>Name</Text>
              <TextInput style={styles.input} value={form.planName} onChangeText={(v) => setForm((f) => ({ ...f, planName: v }))} />
              <Text style={styles.label}>Duration (months)</Text>
              <TextInput style={styles.input} keyboardType="number-pad" value={form.durationMonths} onChangeText={(v) => setForm((f) => ({ ...f, durationMonths: v }))} />
              <Text style={styles.label}>Price</Text>
              <TextInput style={styles.input} keyboardType="number-pad" value={form.price} onChangeText={(v) => setForm((f) => ({ ...f, price: v }))} />
              <Text style={styles.label}>Description</Text>
              <TextInput style={styles.input} value={form.description} onChangeText={(v) => setForm((f) => ({ ...f, description: v }))} />
              <Text style={styles.label}>Features (one per line)</Text>
              <TextInput style={[styles.input, styles.area]} multiline value={form.features} onChangeText={(v) => setForm((f) => ({ ...f, features: v }))} />
              <View style={styles.switchRow}>
                <Text style={styles.switchLabel}>Active</Text>
                <Switch value={form.isActive} onValueChange={(v) => setForm((f) => ({ ...f, isActive: v }))} trackColor={{ true: COLORS.teal, false: COLORS.border }} />
              </View>
              <View style={styles.switchRow}>
                <Text style={styles.switchLabel}>Promotional</Text>
                <Switch value={form.isPromotional} onValueChange={(v) => setForm((f) => ({ ...f, isPromotional: v }))} trackColor={{ true: COLORS.teal, false: COLORS.border }} />
              </View>
              <Pressable style={styles.btn} disabled={saving} onPress={() => void save()}>
                <Text style={styles.btnText}>{saving ? 'Saving…' : 'Save plan'}</Text>
              </Pressable>
              <Pressable style={styles.cancel} onPress={() => setShowForm(false)}>
                <Text style={styles.cancelText}>Cancel</Text>
              </Pressable>
            </SoftCard>
          ) : null}

          {plans.map((p) => (
            <SoftCard key={p.id}>
              <Text style={styles.name}>
                {p.planName} {!p.isActive ? '(inactive)' : ''}
              </Text>
              <Text style={styles.muted}>
                {p.durationMonths} mo · {formatInr(p.price)}
                {p.maxGroups != null ? ` · max ${p.maxGroups} groups` : ''}
              </Text>
              <View style={styles.row}>
                <Pressable style={styles.secondaryBtn} onPress={() => startEdit(p)}>
                  <Text style={styles.secondaryBtnText}>Edit</Text>
                </Pressable>
                {p.isActive ? (
                  <Pressable style={styles.dangerBtn} disabled={saving} onPress={() => void deactivate(p.id)}>
                    <Text style={styles.dangerText}>Deactivate</Text>
                  </Pressable>
                ) : null}
              </View>
            </SoftCard>
          ))}
        </ScrollView>
      )}
    </View>
  )
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: COLORS.bg },
  content: { padding: SPACE.md, paddingBottom: 40, gap: 10 },
  addBtn: { backgroundColor: COLORS.teal, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 8 },
  addBtnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
  formTitle: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  label: { marginTop: 8, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.muted },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: COLORS.bg,
    color: COLORS.text,
  },
  area: { minHeight: 80, textAlignVertical: 'top' },
  switchRow: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  switchLabel: { fontFamily: FONTS.bodyMed, color: COLORS.text },
  btn: { marginTop: 14, backgroundColor: COLORS.teal, borderRadius: 12, paddingVertical: 12, alignItems: 'center' },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold },
  cancel: { marginTop: 8, alignItems: 'center', paddingVertical: 8 },
  cancelText: { fontFamily: FONTS.bodyMed, color: COLORS.muted },
  name: { fontFamily: FONTS.bodyBold, fontSize: 15, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  row: { flexDirection: 'row', gap: 8, marginTop: 10 },
  secondaryBtn: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: COLORS.white,
  },
  secondaryBtnText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.text },
  dangerBtn: {
    borderWidth: 1,
    borderColor: '#FECACA',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  dangerText: { fontFamily: FONTS.bodyBold, fontSize: 12, color: COLORS.danger },
  ok: { color: COLORS.success, fontFamily: FONTS.bodyMed },
})
