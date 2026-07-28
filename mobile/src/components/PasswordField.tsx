import { useState } from 'react'
import {
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
} from 'react-native'
import { Ionicons } from '@expo/vector-icons'
import { COLORS, FONTS } from '../theme'

type Props = {
  label: string
  value: string
  onChangeText: (value: string) => void
  placeholder?: string
  autoComplete?: TextInputProps['autoComplete']
  textContentType?: TextInputProps['textContentType']
  returnKeyType?: TextInputProps['returnKeyType']
  onSubmitEditing?: TextInputProps['onSubmitEditing']
  variant?: 'default' | 'sand'
}

export function PasswordField({
  label,
  value,
  onChangeText,
  placeholder,
  autoComplete,
  textContentType,
  returnKeyType,
  onSubmitEditing,
  variant = 'default',
}: Props) {
  const [visible, setVisible] = useState(false)

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.inputWrap}>
        <TextInput
          style={[styles.input, variant === 'sand' && styles.inputSand]}
          value={value}
          onChangeText={onChangeText}
          secureTextEntry={!visible}
          placeholder={placeholder}
          placeholderTextColor={COLORS.muted}
          autoCapitalize="none"
          autoCorrect={false}
          autoComplete={autoComplete}
          textContentType={textContentType}
          returnKeyType={returnKeyType}
          onSubmitEditing={onSubmitEditing}
        />
        <Pressable
          style={styles.eyeBtn}
          onPress={() => setVisible((v) => !v)}
          hitSlop={10}
          accessibilityRole="button"
          accessibilityLabel={visible ? 'Hide password' : 'Show password'}
        >
          <Ionicons
            name={visible ? 'eye-off-outline' : 'eye-outline'}
            size={22}
            color={COLORS.muted}
          />
        </Pressable>
      </View>
    </View>
  )
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 4 },
  label: {
    marginTop: 8,
    marginBottom: 6,
    fontFamily: FONTS.bodyMed,
    fontSize: 12,
    color: COLORS.muted,
  },
  inputWrap: {
    position: 'relative',
    justifyContent: 'center',
  },
  input: {
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 12,
    paddingRight: 46,
    backgroundColor: COLORS.white,
    color: COLORS.text,
    fontFamily: FONTS.body,
    fontSize: 15,
  },
  inputSand: { backgroundColor: COLORS.sand },
  eyeBtn: {
    position: 'absolute',
    right: 10,
    height: 44,
    width: 36,
    alignItems: 'center',
    justifyContent: 'center',
  },
})
