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
import { useFocusEffect, useNavigation } from '@react-navigation/native'
import type { NativeStackNavigationProp } from '@react-navigation/native-stack'
import { Ionicons } from '@expo/vector-icons'
import { useAuth } from '../../auth/AuthContext'
import { apiFetch } from '../../api'
import { COLORS, FONTS, SPACE } from '../../theme'
import { ErrorBanner, ScreenHeader, SoftCard } from '../../components/ui'
import { formatInr, type RootStackParamList } from '../../types'

type GroupItem = {
  id: number
  groupName: string
  totalMembers: number
  monthlyContribution: number
  status: string
  completedMonths: number
}

type Nav = NativeStackNavigationProp<RootStackParamList>

function ActionBtn({
  label,
  icon,
  onPress,
  outline,
}: {
  label: string
  icon: keyof typeof Ionicons.glyphMap
  onPress: () => void
  outline?: boolean
}) {
  return (
    <Pressable
      style={[styles.btn, outline && styles.btnOutline]}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
    >
      <Ionicons name={icon} size={14} color={outline ? COLORS.text : COLORS.white} />
      <Text style={[styles.btnText, outline && styles.btnOutlineText]}>{label}</Text>
    </Pressable>
  )
}

export function AdminGroupsScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [groups, setGroups] = useState<GroupItem[]>([])
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<GroupItem[]>('/api/groups', {}, user.accessToken)
      setGroups(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load groups')
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

  return (
    <View style={styles.root}>
      <ScreenHeader
        title="Groups"
        subtitle="Ledger · bidding · roster · clone"
        right={
          <Pressable style={styles.addBtn} onPress={() => navigation.navigate('AdminCreateGroup')}>
            <Ionicons name="add" size={16} color={COLORS.white} />
            <Text style={styles.addBtnText}>New</Text>
          </Pressable>
        }
      />
      {loading && groups.length === 0 ? (
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
          {groups.map((g) => (
            <SoftCard key={g.id} style={styles.card}>
              <Pressable onPress={() => navigation.navigate('GroupLedger', { groupId: g.id })}>
                <View style={styles.titleRow}>
                  <Ionicons name="people-outline" size={18} color={COLORS.teal} />
                  <Text style={styles.name}>{g.groupName}</Text>
                </View>
                <Text style={styles.muted}>
                  {g.totalMembers} seats · {formatInr(g.monthlyContribution)} / mo · {g.status}
                </Text>
                <Text style={styles.meta}>
                  {g.completedMonths}/{g.totalMembers} months done · Open ledger
                </Text>
              </Pressable>
              <View style={styles.row}>
                <ActionBtn
                  label="Bidding"
                  icon="hammer-outline"
                  onPress={() => navigation.navigate('AdminBidding', { groupId: g.id })}
                />
                <ActionBtn
                  label="Chart"
                  icon="grid-outline"
                  onPress={() => navigation.navigate('AdminBcChart', { groupId: g.id })}
                />
                <ActionBtn
                  label="Random"
                  icon="dice-outline"
                  onPress={() => navigation.navigate('RandomPicks', { groupId: g.id })}
                />
              </View>
              <View style={styles.row}>
                <ActionBtn
                  outline
                  label="Roster"
                  icon="list-outline"
                  onPress={() => navigation.navigate('AdminGroupRoster', { groupId: g.id })}
                />
                <ActionBtn
                  outline
                  label="Clone"
                  icon="copy-outline"
                  onPress={() => navigation.navigate('AdminCloneGroup', { groupId: g.id })}
                />
                <ActionBtn
                  outline
                  label="Edit"
                  icon="create-outline"
                  onPress={() => navigation.navigate('AdminEditGroup', { groupId: g.id })}
                />
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
  card: { marginBottom: 0 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  name: { flex: 1, fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  meta: { marginTop: 6, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.teal },
  row: { flexDirection: 'row', gap: 8, marginTop: 12 },
  btn: {
    flex: 1,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  btnOutline: { backgroundColor: COLORS.white, borderWidth: 1, borderColor: COLORS.border },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 11 },
  btnOutlineText: { color: COLORS.text },
  addBtn: {
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  addBtnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
})
