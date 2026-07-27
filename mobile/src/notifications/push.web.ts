/** Web: no OS tray push — keep API surface so AuthContext imports stay stable. */
export async function ensureAndroidChannel(): Promise<void> {}

export async function registerForPushNotificationsAsync(
  _accessToken: string,
): Promise<string | null> {
  return null
}

export async function unregisterPushToken(
  _accessToken: string,
  _token: string,
): Promise<void> {}

export function addNotificationOpenListener(_onOpen: () => void): () => void {
  return () => {}
}
