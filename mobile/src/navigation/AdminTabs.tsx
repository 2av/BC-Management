import { createBottomTabNavigator } from '@react-navigation/bottom-tabs'
import { Ionicons } from '@expo/vector-icons'
import { AdminHomeScreen } from '../screens/admin/AdminHomeScreen'
import { AdminGroupsScreen } from '../screens/admin/AdminGroupsScreen'
import { AdminMembersScreen } from '../screens/admin/AdminMembersScreen'
import { AdminPaymentsScreen } from '../screens/admin/AdminPaymentsScreen'
import { AdminMoreScreen } from '../screens/admin/AdminMoreScreen'
import { COLORS, FONTS } from '../theme'

export type AdminTabParamList = {
  AdminHome: undefined
  AdminGroups: undefined
  AdminMembers: undefined
  AdminPayments: undefined
  AdminMore: undefined
}

const Tab = createBottomTabNavigator<AdminTabParamList>()

export function AdminTabs() {
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
        tabBarLabelStyle: { fontFamily: FONTS.bodyMed, fontSize: 11 },
      }}
    >
      <Tab.Screen
        name="AdminHome"
        component={AdminHomeScreen}
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="AdminGroups"
        component={AdminGroupsScreen}
        options={{
          title: 'Groups',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'people' : 'people-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="AdminMembers"
        component={AdminMembersScreen}
        options={{
          title: 'Members',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'person' : 'person-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="AdminPayments"
        component={AdminPaymentsScreen}
        options={{
          title: 'Pay',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'wallet' : 'wallet-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="AdminMore"
        component={AdminMoreScreen}
        options={{
          title: 'More',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'menu' : 'menu-outline'} size={size} color={color} />
          ),
        }}
      />
    </Tab.Navigator>
  )
}
