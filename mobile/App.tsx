import { useEffect, useState } from 'react'
import { ActivityIndicator, Platform, View } from 'react-native'
import { NavigationContainer } from '@react-navigation/native'
import { createNativeStackNavigator } from '@react-navigation/native-stack'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { SafeAreaProvider } from 'react-native-safe-area-context'
import { useFonts } from 'expo-font'
import { AuthProvider, useAuth } from './src/auth/AuthContext'
import { LoginScreen } from './src/screens/LoginScreen'
import { MainTabs } from './src/navigation/MainTabs'
import { AdminTabs } from './src/navigation/AdminTabs'
import { navigationRef, navigateToAlerts } from './src/navigation/navigationRef'
import { addNotificationOpenListener } from './src/notifications/push'
import { PayDetailScreen } from './src/screens/PayDetailScreen'
import { GroupLedgerScreen } from './src/screens/GroupLedgerScreen'
import { RandomPicksScreen } from './src/screens/RandomPicksScreen'
import { InvoiceScreen } from './src/screens/InvoiceScreen'
import { AdminBiddingScreen } from './src/screens/admin/AdminBiddingScreen'
import { AdminChangePasswordScreen } from './src/screens/admin/AdminChangePasswordScreen'
import { AdminCreateGroupScreen } from './src/screens/admin/AdminCreateGroupScreen'
import { AdminEditGroupScreen } from './src/screens/admin/AdminEditGroupScreen'
import { AdminNotificationsScreen } from './src/screens/admin/AdminNotificationsScreen'
import { AdminBcChartScreen } from './src/screens/admin/AdminBcChartScreen'
import { AdminReportsScreen } from './src/screens/admin/AdminReportsScreen'
import { AdminPaymentConfigScreen } from './src/screens/admin/AdminPaymentConfigScreen'
import { AdminSettingsScreen } from './src/screens/admin/AdminSettingsScreen'
import { AdminGroupRosterScreen } from './src/screens/admin/AdminGroupRosterScreen'
import { AdminCloneGroupScreen } from './src/screens/admin/AdminCloneGroupScreen'
import { SuperAdminTabs } from './src/navigation/SuperAdminTabs'
import { SuperAdminClientDetailScreen } from './src/screens/super-admin/SuperAdminClientDetailScreen'
import { SuperAdminPaymentsScreen } from './src/screens/super-admin/SuperAdminPaymentsScreen'
import { SuperAdminAuditScreen } from './src/screens/super-admin/SuperAdminAuditScreen'
import { COLORS } from './src/theme'
import type { RootStackParamList } from './src/types'

const Stack = createNativeStackNavigator<RootStackParamList>()
const queryClient = new QueryClient()

function RootNavigator() {
  const { user, ready } = useAuth()

  useEffect(() => {
    if (!user || user.role !== 'Member') return
    return addNotificationOpenListener(() => navigateToAlerts())
  }, [user])

  if (!ready) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.bg }}>
        <ActivityIndicator color={COLORS.teal} size="large" />
      </View>
    )
  }

  const isAdmin = user?.role === 'ClientAdmin'
  const isSuperAdmin = user?.role === 'SuperAdmin'
  const mustChange = Boolean(user?.mustChangePassword)

  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      {!user ? (
        <Stack.Screen name="Login" component={LoginScreen} />
      ) : mustChange && user.role === 'Member' ? (
        <Stack.Screen name="MemberForceChangePassword" component={AdminChangePasswordScreen} />
      ) : isSuperAdmin ? (
        <>
          <Stack.Screen name="SuperAdminTabs" component={SuperAdminTabs} />
          <Stack.Screen name="SaClientDetail" component={SuperAdminClientDetailScreen} />
          <Stack.Screen name="SaPayments" component={SuperAdminPaymentsScreen} />
          <Stack.Screen name="SaAudit" component={SuperAdminAuditScreen} />
          <Stack.Screen name="AdminChangePassword" component={AdminChangePasswordScreen} />
        </>
      ) : isAdmin ? (
        <>
          <Stack.Screen name="AdminTabs" component={AdminTabs} />
          <Stack.Screen name="GroupLedger" component={GroupLedgerScreen} />
          <Stack.Screen name="RandomPicks" component={RandomPicksScreen} />
          <Stack.Screen name="AdminBidding" component={AdminBiddingScreen} />
          <Stack.Screen name="AdminChangePassword" component={AdminChangePasswordScreen} />
          <Stack.Screen name="AdminCreateGroup" component={AdminCreateGroupScreen} />
          <Stack.Screen name="AdminEditGroup" component={AdminEditGroupScreen} />
          <Stack.Screen name="AdminNotifications" component={AdminNotificationsScreen} />
          <Stack.Screen name="AdminBcChart" component={AdminBcChartScreen} />
          <Stack.Screen name="AdminReports" component={AdminReportsScreen} />
          <Stack.Screen name="AdminPaymentConfig" component={AdminPaymentConfigScreen} />
          <Stack.Screen name="AdminSettings" component={AdminSettingsScreen} />
          <Stack.Screen name="AdminGroupRoster" component={AdminGroupRosterScreen} />
          <Stack.Screen name="AdminCloneGroup" component={AdminCloneGroupScreen} />
          <Stack.Screen name="Invoice" component={InvoiceScreen} />
        </>
      ) : (
        <>
          <Stack.Screen name="MainTabs" component={MainTabs} />
          <Stack.Screen name="PayDetail" component={PayDetailScreen} />
          <Stack.Screen name="GroupLedger" component={GroupLedgerScreen} />
          <Stack.Screen name="RandomPicks" component={RandomPicksScreen} />
          <Stack.Screen name="Invoice" component={InvoiceScreen} />
          <Stack.Screen name="AdminChangePassword" component={AdminChangePasswordScreen} />
        </>
      )}
    </Stack.Navigator>
  )
}

function injectWebFonts() {
  if (Platform.OS !== 'web' || typeof document === 'undefined') return
  const id = 'mitra-google-fonts'
  if (document.getElementById(id)) return
  const link = document.createElement('link')
  link.id = id
  link.rel = 'stylesheet'
  link.href =
    'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap'
  document.head.appendChild(link)
}

export default function App() {
  const [fontsLoaded, fontError] = useFonts({
    Fraunces_600SemiBold: require('./assets/fonts/Fraunces_600SemiBold.ttf'),
    Fraunces_700Bold: require('./assets/fonts/Fraunces_700Bold.ttf'),
    DMSans_400Regular: require('./assets/fonts/DMSans_400Regular.ttf'),
    DMSans_500Medium: require('./assets/fonts/DMSans_500Medium.ttf'),
    DMSans_700Bold: require('./assets/fonts/DMSans_700Bold.ttf'),
  })
  const [timedOut, setTimedOut] = useState(false)

  useEffect(() => {
    injectWebFonts()
    const t = setTimeout(() => setTimedOut(true), 4000)
    return () => clearTimeout(t)
  }, [])

  if (!fontsLoaded && !fontError && !timedOut) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.navy }}>
        <ActivityIndicator color={COLORS.tealSoft} size="large" />
      </View>
    )
  }

  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <NavigationContainer ref={navigationRef}>
            <RootNavigator />
          </NavigationContainer>
        </AuthProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  )
}
