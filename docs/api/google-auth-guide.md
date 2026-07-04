# Google Sign-In — Mobile Auth Guide

How the mobile app authenticates users with Google and gets a Moodle token.

---

## Flow overview

```
Mobile app
  │
  ├─ 1. Google Sign-In SDK  ──►  Google
  │                          ◄──  id_token (JWT)
  │
  ├─ 2. POST /local/googleauth/google_login.php  { idtoken }
  │                          ──►  Moodle verifies token with Google
  │                               finds or creates the Moodle account
  │                          ◄──  { token, userid, username, … }
  │
  └─ 3. Use token as wstoken in all subsequent API calls
```

---

## Server setup (once, as admin)

### 1. Install the plugin

Place `local/googleauth/` in your Moodle `local/` directory and visit  
**Site admin → Notifications** to run the install.

### 2. Configure the Google Client ID

**Site admin → Plugins → Local plugins → Google Auth**

Paste the **OAuth 2.0 Client ID** from Google Cloud Console.  
This must match the client ID used in the mobile app's Google Sign-In SDK setup.

- Android app → use the Android client ID **or** the web client ID (Google recommends the web client ID for server-side verification)
- iOS app → use the iOS client ID **or** the web client ID

> If you leave the Client ID blank, audience verification is skipped (fine for dev, not for production).

### 3. Google Cloud Console setup

1. Go to [console.cloud.google.com](https://console.cloud.google.com) → **APIs & Services → Credentials**
2. Create an **OAuth 2.0 Client ID** for each platform (Android, iOS) your app targets
3. Also create a **Web** client ID — this is what you configure in the plugin settings

---

## Endpoint

```
POST /local/googleauth/google_login.php
Content-Type: application/json
```

### Request body

```json
{ "idtoken": "<Google id_token string>" }
```

Also accepts `application/x-www-form-urlencoded` with `idtoken=...`.

### Success response `200`

```json
{
  "token":     "abc123moodletoken...",
  "userid":    42,
  "username":  "ahmed.ali",
  "firstname": "Ahmed",
  "lastname":  "Ali",
  "email":     "ahmed.ali@gmail.com"
}
```

Save `token` → use as `wstoken` in every subsequent request.  
Save `userid` → use wherever the API needs a `userid` parameter.

### Error responses

| HTTP | `error` code         | Meaning |
|------|----------------------|---------|
| 400  | `missing_idtoken`    | `idtoken` field not sent |
| 400  | `unverified_email`   | Google email not verified |
| 401  | `invalid_token`      | Google rejected the token (expired, tampered) |
| 401  | `token_expired`      | Token `exp` claim is in the past |
| 401  | `invalid_audience`   | Token `aud` doesn't match configured client ID |
| 403  | `user_suspended`     | Moodle account is suspended |
| 405  | `method_not_allowed` | Not a POST request |

---

## Account creation behaviour

| Scenario | Result |
|----------|--------|
| First Google login | Moodle account created automatically (`auth=oauth2`) with name and email from Google |
| Subsequent Google logins | Same Moodle account found by email; same token reused |
| Email already exists (manual account) | Linked to that existing account — no duplicate created |
| Username collision | `_2`, `_3` suffix appended automatically |

---

## Mobile integration (by platform)

### React Native — `@react-native-google-signin/google-signin`

```js
import { GoogleSignin } from '@react-native-google-signin/google-signin';

GoogleSignin.configure({
  webClientId: 'YOUR_WEB_CLIENT_ID.apps.googleusercontent.com',
});

async function loginWithGoogle() {
  await GoogleSignin.hasPlayServices();
  const userInfo = await GoogleSignin.signIn();
  const { idToken } = await GoogleSignin.getTokens();

  const res = await fetch('https://YOUR_MOODLE_URL/local/googleauth/google_login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ idtoken: idToken }),
  });

  const data = await res.json();
  // store data.token and data.userid
}
```

### Flutter — `google_sign_in`

```dart
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;

final _googleSignIn = GoogleSignIn(
  clientId: 'YOUR_WEB_CLIENT_ID.apps.googleusercontent.com', // iOS only; Android uses google-services.json
);

Future<void> loginWithGoogle() async {
  final account = await _googleSignIn.signIn();
  final auth    = await account!.authentication;
  final idToken = auth.idToken!;

  final res = await http.post(
    Uri.parse('https://YOUR_MOODLE_URL/local/googleauth/google_login.php'),
    headers: {'Content-Type': 'application/json'},
    body: jsonEncode({'idtoken': idToken}),
  );

  final data = jsonDecode(res.body);
  // store data['token'] and data['userid']
}
```

---

## Security notes

- The `id_token` is verified server-side by calling Google's `tokeninfo` endpoint — no JWT library needed.
- The token returned is a standard Moodle permanent token (same as username+password login). Store it securely (Keychain / Keystore).
- Reusing existing tokens means the user stays logged in across app reinstalls as long as the Moodle token is not revoked.
- To force re-verification on every login, delete the `$existing` reuse logic in `google_login.php` — each login will then generate a fresh token.
