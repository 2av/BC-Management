import { useCallback, useState } from 'react'
import {
  ActivityIndicator,
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
import type { RootStackParamList } from '../../types'

type Nav = NativeStackNavigationProp<RootStackParamList>

type AuditItem = {
  id: number
  clientId: number | null
  clientName: string | null
  userType: string
  userId: number
  action: string
  tableName: string | null
  recordId: number | null
  ipAddress: string | null
  createdAt: string
}

export function SuperAdminAuditScreen() {
  const { user } = useAuth()
  const navigation = useNavigation<Nav>()
  const [items, setItems] = useState<AuditItem[]>([])
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const load = useCallback(async () => {
    if (!user?.accessToken) return
    setError(null)
    try {
      const next = await apiFetch<AuditItem[]>(
        '/api/super-admin/audit-logs?page=1&pageSize=50',
        {},
        user.accessToken,
      )
      setItems(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load audit logs')
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
        title="Audit log"
        subtitle="Recent platform actions"
        onBack={() => navigation.goBack()}
      />
      {loading ? (
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
          {items.length === 0 ? <Text style={styles.muted}>No audit entries.</Text> : null}
          {items.map((a) => (
            <SoftCard key={a.id}>
              <Text style={styles.name}>{a.action}</Text>
              <Text style={styles.muted}>
                {a.userType} #{a.userId}
                {a.clientName ? ` · ${a.clientName}` : ''}
                {a.tableName ? ` · ${a.tableName}` : ''}
                {a.recordId != null ? ` #${a.recordId}` : ''}
              </Text>
              <Text style={styles.meta}>{a.createdAt}</Text>
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
  name: { fontFamily: FONTS.bodyBold, fontSize: 14, color: COLORS.text },
  muted: { marginTop: 4, fontFamily: FONTS.body, fontSize: 12, color: COLORS.muted },
  meta: { marginTop: 6, fontFamily: FONTS.bodyMed, fontSize: 11, color: COLORS.teal },
})
