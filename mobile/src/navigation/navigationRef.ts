import { createNavigationContainerRef } from '@react-navigation/native'
import type { RootStackParamList } from '../types'

export const navigationRef = createNavigationContainerRef<RootStackParamList>()

export function navigateToAlerts() {
  if (!navigationRef.isReady()) return
  navigationRef.navigate('MainTabs', { screen: 'Alerts' })
}
