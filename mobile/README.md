# Mitra Niidhi — mobile (Expo)

Android **APK** for Member / Client Admin / Super Admin.

## Production API

Default: `https://bc.api.agprimetech.com`  
(set in `app.json` → `extra.apiUrl` and `.env.production`)

## Run (development)

```bash
cd mobile
npm install
$env:EXPO_PUBLIC_API_URL="http://10.0.2.2:5027"   # emulator → local API
npm start
```

## Build installable APK (EAS)

```bash
cd mobile
npm install
npx eas-cli login
npx eas-cli build -p android --profile preview
```

Download the `.apk` from the Expo page, copy to `../publish/mobile/`, and install on the phone  
(**Settings → Install unknown apps**).

Preview profile builds an **APK** (not AAB) for sideload sharing.

## iPhone / iOS (IPA)

Apple does **not** allow a simple sideload “setup file” like Android APK.

You need:
1. An **Apple Developer Program** account (~$99/year)
2. An Expo account (`npx eas-cli login`)
3. Apple credentials linked in EAS (Apple ID + team)

Then build:

```bash
cd mobile
npx eas-cli build -p ios --profile preview
```

Distribution options after the build:
- **TestFlight** (best for testers) — `npx eas-cli submit -p ios`
- **Ad Hoc IPA** — install only on registered device UDIDs
- **App Store** — public release

You cannot email a random `.ipa` for anyone to install the way you share an `.apk`.
