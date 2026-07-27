import { createBottomTabNavigator } from '@react-navigation/bottom-tabs'
import { Ionicons } from '@expo/vector-icons'
import { View, Text, StyleSheet } from 'react-native'
import { DashboardScreen } from '../screens/DashboardScreen'
import { PaymentsScreen } from '../screens/PaymentsScreen'
import { NotificationsScreen } from '../screens/NotificationsScreen'
import { ProfileScreen } from '../screens/ProfileScreen'
import { COLORS, FONTS } from '../theme'
import type { MainTabParamList } from '../types'
import { useAuth } from '../auth/AuthContext'
import { useEffect, useState } from 'react'
import { apiFetch } from '../api'
import type { NotificationCounts } from '../types'

const Tab = createBottomTabNavigator<MainTabParamList>()

function AlertsIcon({ color, size, focused }: { color: string; size: number; focused: boolean }) {
  const { user } = useAuth()
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let alive = true
    ;(async () => {
      if (!user?.accessToken) return
      try {
        const c = await apiFetch<NotificationCounts>(
          '/api/notifications/counts',
          {},
          user.accessToken,
        )
        if (alive) setUnread(c.unread)
      } catch {
        // ignore
      }
    })()
    return () => {
      alive = false
    }
  }, [user?.accessToken, focused])

  return (
    <View>
      <Ionicons name={focused ? 'notifications' : 'notifications-outline'} size={size} color={color} />
      {unread > 0 ? (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{unread > 9 ? '9+' : unread}</Text>
        </View>
      ) : null}
    </View>
  )
}

export function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: COLORS.teal,
        tabBarInactiveTintColor: COLORS.tabInactive,
        tabBarStyle: {
          backgroundColor: COLORS.white,
          borderTopColor: COLORS.border,
          height: 64,
          paddingBottom: 8,
          paddingTop: 6,
        },
        tabBarLabelStyle: {
          fontFamily: FONTS.bodyMed,
          fontSize: 11,
        },
      }}
    >
      <Tab.Screen
        name="Home"
        component={DashboardScreen}
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="Pay"
        component={PaymentsScreen}
        options={{
          title: 'Pay',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'wallet' : 'wallet-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="Alerts"
        component={NotificationsScreen}
        options={{
          title: 'Alerts',
          tabBarIcon: ({ color, size, focused }) => (
            <AlertsIcon color={color} size={size} focused={focused} />
          ),
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          title: 'Profile',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'person' : 'person-outline'} size={size} color={color} />
          ),
        }}
      />
    </Tab.Navigator>
  )
}

const styles = StyleSheet.create({
  badge: {
    position: 'absolute',
    top: -4,
    right: -8,
    minWidth: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: COLORS.danger,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 3,
  },
  badgeText: {
    color: COLORS.white,
    fontSize: 9,
    fontFamily: FONTS.bodyBold,
  },
})
