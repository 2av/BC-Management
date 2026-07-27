import { createBottomTabNavigator } from '@react-navigation/bottom-tabs'
import { Ionicons } from '@expo/vector-icons'
import { SuperAdminHomeScreen } from '../screens/super-admin/SuperAdminHomeScreen'
import { SuperAdminClientsScreen } from '../screens/super-admin/SuperAdminClientsScreen'
import { SuperAdminPlansScreen } from '../screens/super-admin/SuperAdminPlansScreen'
import { SuperAdminSubscriptionsScreen } from '../screens/super-admin/SuperAdminSubscriptionsScreen'
import { SuperAdminMoreScreen } from '../screens/super-admin/SuperAdminMoreScreen'
import { COLORS, FONTS } from '../theme'

export type SuperAdminTabParamList = {
  SaHome: undefined
  SaClients: undefined
  SaPlans: undefined
  SaSubs: undefined
  SaMore: undefined
}

const Tab = createBottomTabNavigator<SuperAdminTabParamList>()

export function SuperAdminTabs() {
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
        name="SaHome"
        component={SuperAdminHomeScreen}
        options={{
          title: 'Home',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="SaClients"
        component={SuperAdminClientsScreen}
        options={{
          title: 'Clients',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'business' : 'business-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="SaPlans"
        component={SuperAdminPlansScreen}
        options={{
          title: 'Plans',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'pricetag' : 'pricetag-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="SaSubs"
        component={SuperAdminSubscriptionsScreen}
        options={{
          title: 'Subs',
          tabBarIcon: ({ color, size, focused }) => (
            <Ionicons name={focused ? 'card' : 'card-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tab.Screen
        name="SaMore"
        component={SuperAdminMoreScreen}
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
