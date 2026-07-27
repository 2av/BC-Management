import { Platform } from 'react-native'
import * as Device from 'expo-device'
import * as Notifications from 'expo-notifications'
import Constants from 'expo-constants'
import { apiFetch } from '../api'

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
})

function getProjectId(): string | undefined {
  return (
    Constants.easConfig?.projectId ??
    (Constants.expoConfig?.extra?.eas as { projectId?: string } | undefined)?.projectId
  )
}

export async function ensureAndroidChannel(): Promise<void> {
  if (Platform.OS !== 'android') return
  await Notifications.setNotificationChannelAsync('default', {
    name: 'Mitra Niidhi',
    importance: Notifications.AndroidImportance.MAX,
    vibrationPattern: [0, 250, 250, 250],
    lightColor: '#0D9488',
    sound: 'default',
  })
}

export async function registerForPushNotificationsAsync(
  accessToken: string,
): Promise<string | null> {
  if (!Device.isDevice) {
    console.warn('Push notifications require a physical device')
    return null
  }

  await ensureAndroidChannel()

  const { status: existing } = await Notifications.getPermissionsAsync()
  let finalStatus = existing
  if (existing !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync()
    finalStatus = status
  }
  if (finalStatus !== 'granted') {
    console.warn('Push notification permission not granted')
    return null
  }

  const projectId = getProjectId()
  const tokenResponse = await Notifications.getExpoPushTokenAsync(
    projectId ? { projectId } : undefined,
  )
  const token = tokenResponse.data
  if (!token) return null

  await apiFetch(
    '/api/members/me/push-token',
    {
      method: 'POST',
      body: JSON.stringify({
        token,
        platform: Platform.OS,
      }),
    },
    accessToken,
  )

  return token
}

export async function unregisterPushToken(
  accessToken: string,
  token: string,
): Promise<void> {
  if (!token) return
  try {
    await apiFetch(
      `/api/members/me/push-token?token=${encodeURIComponent(token)}`,
      { method: 'DELETE' },
      accessToken,
    )
  } catch {
    // ignore offline logout
  }
}

export function addNotificationOpenListener(onOpen: () => void): () => void {
  const sub = Notifications.addNotificationResponseReceivedListener(() => {
    onOpen()
  })
  return () => sub.remove()
}
