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
