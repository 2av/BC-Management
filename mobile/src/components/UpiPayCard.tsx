import { useMemo, useState } from 'react'

import { Image, Pressable, StyleSheet, Text, View } from 'react-native'

import * as Clipboard from 'expo-clipboard'

import { COLORS } from '../theme'

import { PAYMENT_BRAND, buildUpiQr } from '../payments/upi'

import type { PaymentMethods } from '../types'



export function UpiPayCard({

  methods,

  amount,

  paymentText,

}: {

  methods?: PaymentMethods | null

  amount?: number | null

  paymentText?: string

}) {

  const [copied, setCopied] = useState(false)

  const note = paymentText || methods?.paymentNote || PAYMENT_BRAND

  const payee = methods?.payeeName || PAYMENT_BRAND



  const embedded = useMemo(() => {

    if (!methods?.upiId?.trim()) return null

    return buildUpiQr({

      upiId: methods.upiId,

      payee,

      note,

      amount,

    })

  }, [methods?.upiId, payee, note, amount])



  const qrImageUrl = embedded?.qrImageUrl || methods?.qrImageUrl



  async function copyUpiId() {

    if (!methods?.upiId) return

    try {

      await Clipboard.setStringAsync(methods.upiId.trim())

      setCopied(true)

      setTimeout(() => setCopied(false), 2000)

    } catch {

      // ignore

    }

  }



  if (!methods?.upiId || !qrImageUrl) {

    return (

      <View style={styles.card}>

        <Text style={styles.title}>{PAYMENT_BRAND}</Text>

        <Text style={styles.muted}>UPI payment is not configured yet. Ask your admin.</Text>

      </View>

    )

  }



  return (

    <View style={styles.card}>

      <Text style={styles.title}>{PAYMENT_BRAND}</Text>

      <Text style={styles.muted}>Scan QR or copy UPI ID · Remark: {note}</Text>

      <Image source={{ uri: qrImageUrl }} style={styles.qr} accessibilityLabel="UPI QR code" />

      <Text style={styles.upiId}>{methods.upiId}</Text>

      <Pressable onPress={() => void copyUpiId()} style={styles.copyBtn}>

        <Text style={styles.copyText}>{copied ? 'Copied' : 'Copy UPI ID'}</Text>

      </Pressable>

    </View>

  )

}



const styles = StyleSheet.create({

  card: {

    backgroundColor: '#F0FDFA',

    borderRadius: 14,

    borderWidth: 1,

    borderColor: COLORS.tealSoft,

    padding: 16,

    marginBottom: 14,

    alignItems: 'center',

  },

  title: { fontSize: 16, fontWeight: '700', color: COLORS.teal, marginBottom: 4 },

  muted: {

    fontSize: 13,

    color: COLORS.muted,

    textAlign: 'center',

    marginBottom: 12,

    lineHeight: 18,

  },

  qr: {

    width: 200,

    height: 200,

    borderRadius: 8,

    backgroundColor: COLORS.white,

    marginBottom: 12,

  },

  upiId: { fontSize: 14, fontWeight: '700', color: COLORS.text, marginBottom: 10 },

  copyBtn: {

    backgroundColor: COLORS.teal,

    paddingHorizontal: 18,

    paddingVertical: 10,

    borderRadius: 10,

  },

  copyText: { color: COLORS.white, fontWeight: '700', fontSize: 14 },

})

