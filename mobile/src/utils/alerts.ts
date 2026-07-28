import { Alert, Platform } from 'react-native'

function webAlert(title: string, message: string, onOk?: () => void) {
  // React Native Alert.alert is unreliable / silent on web browsers.
  if (typeof window !== 'undefined') {
    window.alert(title ? `${title}\n\n${message}` : message)
    onOk?.()
    return
  }
  Alert.alert(title, message, [{ text: 'OK', onPress: onOk }])
}

export function showError(message: string, title = 'Error') {
  if (Platform.OS === 'web') {
    webAlert(title, message)
    return
  }
  Alert.alert(title, message, [{ text: 'OK' }])
}

export function showSuccess(message: string, title = 'Success', onOk?: () => void) {
  if (Platform.OS === 'web') {
    webAlert(title, message, onOk)
    return
  }
  Alert.alert(title, message, [{ text: 'OK', onPress: onOk }])
}

export function showInfo(message: string, title = 'Notice') {
  if (Platform.OS === 'web') {
    webAlert(title, message)
    return
  }
  Alert.alert(title, message, [{ text: 'OK' }])
}

export function showConfirm(
  title: string,
  message: string,
  onConfirm: () => void,
  confirmLabel = 'Confirm',
) {
  if (Platform.OS === 'web') {
    const ok =
      typeof window !== 'undefined'
        ? window.confirm(title ? `${title}\n\n${message}` : message)
        : false
    if (ok) onConfirm()
    return
  }
  Alert.alert(title, message, [
    { text: 'Cancel', style: 'cancel' },
    { text: confirmLabel, style: 'destructive', onPress: onConfirm },
  ])
}
