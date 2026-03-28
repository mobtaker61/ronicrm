# TikTok Developer Portal — copy for RoniCRM / ronicrm.com (English)

## Critical: you swapped two URLs (fix this first)

| Field in TikTok portal | Wrong (what you had) | Correct value |
|------------------------|----------------------|---------------|
| **Login Kit → Redirect URI (Web)** | `https://ronicrm.com/tiktok-webhook` | **`https://ronicrm.com/settings/tiktok/callback`** |
| **Webhooks → Callback URL** | `https://ronicrm.com/settings/tiktok/callback` | **`https://ronicrm.com/tiktok-webhook`** |

**Why:** OAuth must return the user’s browser to your **Laravel callback route** (`/settings/tiktok/callback`). TikTok’s **servers** call **`/tiktok-webhook`** for POST events (and may hit GET for checks). If Redirect URI points to the webhook, login will break.

In `.env` use: `TIKTOK_REDIRECT_URI=https://ronicrm.com/settings/tiktok/callback` (or rely on the default `route('settings.tiktok.callback')` when this env is empty).

---

Use these texts in **developers.tiktok.com** → your app → **App details**, **App review**, and **Products**. Adjust URLs and the demo video if your domain differs.

**End goal:** two-way **Direct Messaging (DM)** for business users inside RoniCRM (unified inbox: receive webhooks, reply, and send messages within TikTok’s allowed policies).

---

## Basic information

### App name
`RoniCRM` (as registered)

### App description (use this full text — reduces “too short / vague” warnings)

RoniCRM is a web-based customer relationship management (CRM) platform built for small and medium-sized businesses (SMEs). It helps owners and support teams manage customer conversations, leads, and follow-ups in one centralized workspace. Organizations can connect their official TikTok account using TikTok Login (OAuth 2.0). After the user authorizes the app on tiktok.com, RoniCRM receives short-lived and refreshable access tokens to identify the connected TikTok account (openid, display name, and avatar as allowed by approved scopes). Tokens are stored encrypted on our servers and are used only to power features the business enables, including webhook subscriptions for lifecycle events (for example, when a user removes authorization) and, where TikTok approves our use case, Business Messaging so inbound direct messages can appear in RoniCRM’s Inbox and staff can reply in compliance with TikTok’s messaging policies. We do not sell TikTok user data to third parties. Our Privacy Policy and Terms describe data categories, retention, and user rights. All integrations use HTTPS.

### Category
Choose the closest match (e.g. **Business** or **Productivity**).

### Privacy Policy URL
`https://ronicrm.com/privacy-policy`

### Terms of Service URL
`https://ronicrm.com/terms-and-conditions`

### Website URL (Platforms → Web)
`https://ronicrm.com`

### Redirect URI (OAuth) — Login Kit only
`https://ronicrm.com/settings/tiktok/callback`

---

## App review — “Required information for app submission” (paste entire block)

**Product: Login Kit**  
We use Login Kit so a logged-in organization administrator can connect their TikTok account to RoniCRM without sharing their TikTok password. The user is redirected to TikTok, approves the requested scopes, and is sent back to `https://ronicrm.com/settings/tiktok/callback` with an authorization code. Our backend exchanges the code for access and refresh tokens. We use `user.info.basic` to display which TikTok account is connected (e.g. display name and avatar in Settings). If `user.info.profile` is enabled, we may display non-sensitive profile fields returned by TikTok (such as bio or profile links) only where relevant inside the CRM UI. We do not use Login Kit to post content on behalf of users unless a separate TikTok product explicitly allows it and is approved for our app.

**Product: Webhooks**  
We configure the webhook callback at `https://ronicrm.com/tiktok-webhook`. Our server verifies the `TikTok-Signature` header using our client secret, returns HTTP 200 for valid notifications, and processes events idempotently. We handle `authorization.removed` by deleting stored tokens and disconnecting the TikTok integration for that user so we remain compliant when access is revoked. When TikTok enables Business Messaging / DM events for our app, we will use webhooks to ingest inbound messages into our Inbox and to keep conversation state consistent.

**Scopes**  
- `user.info.basic`: Required to identify the connected account after OAuth.  
- `user.info.profile`: Optional; used only to enrich the connected-account display in Settings if TikTok returns those fields and our UI needs them.

**Security & data**  
Tokens are encrypted at rest. Traffic is HTTPS only. Data practices are described at `https://ronicrm.com/privacy-policy`. We are a multi-tenant SaaS: each organization’s data is isolated in our application database.

**Demo video alignment**  
The uploaded video shows the real flow: sign in to RoniCRM → Settings → Connect TikTok → TikTok consent → return to RoniCRM with “connected” state → open Inbox → TikTok channel. Any messaging send/receive shown matches the permissions TikTok has actually granted; we do not demonstrate features that are not live in the build.

---

## Demo video script (for “Upload video”)

1. Open `https://ronicrm.com` and log in as a user who can manage organization settings.  
2. Go to **Settings** → **TikTok**.  
3. Click **Connect TikTok**; complete TikTok’s consent screen; you must land on **`/settings/tiktok/callback`** (not the webhook URL).  
4. Show **connected** state: display name / avatar / open id if shown in UI.  
5. Open **Inbox** → **TikTok** tab; show the conversation list UI.  
6. If DMs are not yet approved by TikTok, add on-screen text or voiceover: *“Messaging delivery will activate after TikTok approves Business Messaging for this app.”* Do not fake sending a DM if the API is not enabled.

---

## Products

### Login Kit
Enable **Login Kit**. Request the minimum scopes you need today (e.g. `user.info.basic`). Add any **messaging-related scopes** only when TikTok’s portal lists them for your app tier and your use case is approved.

### Webhooks
Set **Callback URL** to:

`https://ronicrm.com/tiktok-webhook`

Click **Test URL** after fixing the Login Kit redirect (TikTok may require a healthy OAuth configuration).

Ensure the endpoint returns **HTTP 200** for valid deliveries and verifies **`TikTok-Signature`** (HMAC-SHA256 with your **client secret**) per TikTok’s webhook documentation.

---

## Notes for reviewers

- **Multi-tenant:** Each organization’s TikTok connection is isolated by our `organization_id` in the database.  
- **Revocation:** We handle `authorization.removed` (and similar) webhook events by revoking stored tokens and disconnecting the account in RoniCRM.  
- **DM:** Outbound messages are only sent through **official TikTok Business Messaging APIs** after approval; we do not automate the consumer app or scrape DMs.

---

## Scopes: `user.info.profile`

Enable **only if** you will actually show bio / profile link / verification badge in RoniCRM Settings. If the UI does not use these fields yet, remove `user.info.profile` and keep **`user.info.basic` only** — fewer scopes can speed up review.

## Direct Messaging (two-way)

Your screenshot shows **no messaging / IM product** yet. For full DM, TikTok must grant **Business Messaging** (or the relevant messaging product) and any required scopes in the portal. Until then, describe **Login Kit + Webhooks + future Inbox** honestly in review; do not claim live DM in the written description unless the demo proves it.

## `.env` (server)

```
TIKTOK_CLIENT_KEY=paste_from_portal
TIKTOK_CLIENT_SECRET=paste_from_portal
TIKTOK_REDIRECT_URI=https://ronicrm.com/settings/tiktok/callback
# After TikTok gives you the official send endpoint:
# TIKTOK_MESSAGING_SEND_URL=
```
