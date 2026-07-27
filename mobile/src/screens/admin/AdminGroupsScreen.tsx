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
            <Text style={styles.addBtnText}>+ New</Text>
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
                <Text style={styles.name}>{g.groupName}</Text>
                <Text style={styles.muted}>
                  {g.totalMembers} seats · {formatInr(g.monthlyContribution)} / mo · {g.status}
                </Text>
                <Text style={styles.meta}>
                  {g.completedMonths}/{g.totalMembers} months done
                </Text>
              </Pressable>
              <View style={styles.row}>
                <Pressable
                  style={styles.btn}
                  onPress={() => navigation.navigate('AdminBidding', { groupId: g.id })}
                >
                  <Text style={styles.btnText}>Bidding</Text>
                </Pressable>
                <Pressable
                  style={styles.btn}
                  onPress={() => navigation.navigate('AdminBcChart', { groupId: g.id })}
                >
                  <Text style={styles.btnText}>Chart</Text>
                </Pressable>
                <Pressable
                  style={styles.btn}
                  onPress={() => navigation.navigate('RandomPicks', { groupId: g.id })}
                >
                  <Text style={styles.btnText}>Random</Text>
                </Pressable>
              </View>
              <View style={styles.row}>
                <Pressable
                  style={[styles.btn, styles.btnOutline]}
                  onPress={() => navigation.navigate('AdminGroupRoster', { groupId: g.id })}
                >
                  <Text style={[styles.btnText, styles.btnOutlineText]}>Roster</Text>
                </Pressable>
                <Pressable
                  style={[styles.btn, styles.btnOutline]}
                  onPress={() => navigation.navigate('AdminCloneGroup', { groupId: g.id })}
                >
                  <Text style={[styles.btnText, styles.btnOutlineText]}>Clone</Text>
                </Pressable>
                <Pressable
                  style={[styles.btn, styles.btnOutline]}
                  onPress={() => navigation.navigate('AdminEditGroup', { groupId: g.id })}
                >
                  <Text style={[styles.btnText, styles.btnOutlineText]}>Edit</Text>
                </Pressable>
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
  name: { fontFamily: FONTS.bodyBold, fontSize: 16, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 13, color: COLORS.muted },
  meta: { marginTop: 6, fontFamily: FONTS.bodyMed, fontSize: 12, color: COLORS.teal },
  row: { flexDirection: 'row', gap: 8, marginTop: 12 },
  btn: {
    flex: 1,
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
  },
  btnOutline: { backgroundColor: COLORS.white, borderWidth: 1, borderColor: COLORS.border },
  btnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 12 },
  btnOutlineText: { color: COLORS.text },
  addBtn: {
    backgroundColor: COLORS.teal,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  addBtnText: { color: COLORS.white, fontFamily: FONTS.bodyBold, fontSize: 13 },
})
