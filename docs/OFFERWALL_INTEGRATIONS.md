# Offerwall Integrations — Setup Guide

How to connect real offerwall networks to EarnPlus so users earn coins
automatically when they complete tasks. Written in plain steps — no
coding needed on your side. Everything is pasted in dashboards, not in code.

> **Status key:** ✅ verified against the network's official docs (2026-10-06)
> · ⚠️ partially verified — a few details must be confirmed in your
> publisher dashboard · All providers ship **disabled**. Nothing goes
> live until you paste real credentials and switch the provider on in
> the EarnPlus admin panel (Offerwalls section).

---

## How EarnPlus receives earnings (all networks)

1. A user taps a task in your app. EarnPlus records the click and sends
   the user to the network's wall.
2. The user completes an offer on the network's side.
3. The network calls your server: `https://YOUR-DOMAIN/postback/{slug}`
   (replace `YOUR-DOMAIN` with your real domain, and `{slug}` with the
   network's slug below). This is called a **postback**.
4. EarnPlus checks the postback (signature if the network provides one,
   your click record, duplicates), then credits the user's wallet.

You never invent API keys. Each network gives you its own keys **after**
it approves your publisher account.

---

## 1. AdGate Media ✅

**What it is:** a well-known offerwall network (now part of Prodege).
Publisher panel: `https://panel.adgatemedia.com`

**In the AdGate panel (after they approve you):**
1. Go to **Monetization Tools → AdGate Rewards → Create AdGate Reward Wall**.
2. Fill in your currency names (e.g. Points plural "Coins", singular "Coin").
3. Set the **conversion rate** — how many of your coins $1 USD of earnings is worth.
4. In the **Postback** field, paste:
   `https://YOUR-DOMAIN/postback/adgate?tx_id={transaction_id}&user_id={user_id}&points={points}&offer_id={offer_id}&status={status}`
   (Click **"More Information on Postbacks"** under the field to see the
   exact macro names — make sure the payout macro you use matches the
   `points` parameter. If AdGate shows a different macro name for the
   payout, tell your developer to update the field mapping.)
5. Note your **Wall ID** (shown after creating the wall).

**In the EarnPlus admin panel (Offerwalls → AdGate Media):**
1. Paste the wall into **Offer URL template**:
   `https://wall.adgaterewards.com/YOUR_WALL_ID/{user_id}`
   (replace `YOUR_WALL_ID` with the Wall ID from step 5 above;
   `{user_id}` is filled automatically per user).
2. Switch the provider **on**.
3. That's it — no secret key needed. AdGate's public documentation
   defines no signature for these postbacks, so EarnPlus protects you
   with the IP check (add AdGate's IPs if they publish them), click
   validation and duplicate blocking instead.

**Good to know:**
- AdGate also sends **chargebacks** (`status=0`) when an offer is
  reversed. EarnPlus never credits those — it flags the original
  earning for your review in the conversion log.
- Test mode: AdGate's wall editor has a "Testing Mode" that shows a
  test offer at the top of the wall. Complete it and check that coins
  land in your test user's wallet.

**Sources:** official AdGate docs at
`github.com/adgatemedia/adgaterewards` (wall format + postback example),
AdGate publisher help center.

---

## 2. AdGem ✅

**What it is:** a mobile-focused offerwall network with good surveys.
Publisher dashboard: `dashboard.adgem.com`, docs: `docs.adgem.com`

**In the AdGem dashboard (after they approve you):**
1. Go to **Properties & Apps → New Property**, choose **Desktop/Web**,
   and create it. Copy your **App ID**.
2. Open the property → **Offerwall** tab. Set your virtual currency
   name and icon to match EarnPlus.
3. Go to **Postback Options → Server Postback** and paste this URL
   (parameters must stay in this alphabetical order — AdGem requires
   it for signature checking):
   `https://YOUR-DOMAIN/postback/adgem?amount={amount}&campaign_id={campaign_id}&goal_id={goal_id}&goal_name={goal_name}&offer_name={offer_name}&payout={payout}&player_id={player_id}&transaction_id={transaction_id}`
4. In **Postback Settings**, enable **v2 Server Postback hashing** and
   copy the one-time **Postback Key** it shows you.

**In the EarnPlus admin panel (Offerwalls → AdGem):**
1. Paste the **Postback Key** from step 4 as the provider's
   **postback secret** and save.
2. Paste the wall into **Offer URL template**:
   `https://api.adgem.com/v1/wall?appid=YOUR_APP_ID&playerid={user_id}`
   (replace `YOUR_APP_ID`; `{user_id}` is filled automatically —
   AdGem needs it lowercase, which EarnPlus handles).
3. Switch the provider **on**.

**Before going live:** the v2 signature check is implemented to
AdGem's documented scheme, but confirm the exact signature computation
in your AdGem dashboard's Postback Settings once, then run one test
offer end-to-end.

**Sources:** official AdGem docs (`docs.adgem.com` — web offerwall
integration, quickstart postback setup).

---

## 3. TimeWall ✅

**What it is:** a survey + micro-task offerwall for publishers
("Monetize Your Users"). Site: `timewall.io`

**Status:** VERIFIED — the full spec below was captured from the
owner's TimeWall site-owner dashboard on 2026-10-07 (Placement → Add
Placement → Postback Setup).

> ⚠️ **Approval caveat:** TimeWall's own signup page says it is
> "designed for website owners with at least **5000 unique visitors
> per month**" and smaller sites will not be approved. Apply anyway,
> but keep expectations realistic until the app has traffic.

**In the TimeWall dashboard (Placements → Add Placement):**
1. **Placement Type:** `Offerwall (iFrame)`
2. **Placement Category:** `Reward Site`
3. **Redeem Type:** `Manual Redeem`
4. **Currency Name:** `Coins` (max 8 chars)
5. **Currency Conversion Rate:** `5000` (coins per $1 USD — keep the
   EarnPlus provider config's `timewall_currency_rate` at the same
   value; owner's 2026-10-07 decision for a ~55/45 user/owner split at
   100 Coins = ₹1)
6. **Do you allow decimals:** `No`
7. **Postback URL** — paste this (replace `YOUR-DOMAIN` with your
   real domain):
   `https://YOUR-DOMAIN/postback/timewall?userid={userID}&txid={transactionID}&revenue={revenue}&currency={currencyAmount}&hash={hash}&ip={ip}&type={type}&withdrawid={withdrawid}&reason={reason}&offername={offername}&offerdetail={offerdetail}`
8. Click **REVEAL** next to "Your Secret Key" and copy it.
9. Click **CREATE PLACEMENT** — the dashboard gives you the iframe
   wall URL; paste that into the EarnPlus provider's **Offer URL
   template**.

**How their postback works (already implemented in EarnPlus):**
- GET request with params: `userid`, `txid`, `revenue`, `currency`,
  `hash`, `ip`, `type`, `withdrawid`, `reason`, `offername`,
  `offerdetail`.
- `hash` = SHA256 hex of `userID + revenue + SecretKey` (no
  separators, revenue used *exactly* as sent — `"0.002"`, never
  reformatted).
- Postbacks come from `18.156.132.55` (two older IPs,
  `51.81.120.73` and `142.111.248.18`, are being retired — all three
  are whitelisted).
- EarnPlus credits the `currency` (currencyAmount) as coins when
  sane, otherwise `revenue × 5000`; withdrawal-type events are logged
  for review and never credited.
- For a test postback, contact TimeWall support via the blue icon in
  the dashboard's bottom-right corner.

**In the EarnPlus admin panel (Offerwalls → TimeWall):**
1. Paste the **Secret Key** as the provider's **postback secret**.
2. Paste the iframe wall URL as the **Offer URL template**.
3. Confirm `timewall_currency_rate` matches the placement's
   conversion rate (5000).
4. Switch the provider **on** once TimeWall approves the placement
   (placement `a3604f711b1e95d4`, created 2026-10-07 — currently
   **PENDING APPROVAL**; the iframe wall URL is issued after approval).

**Sources:** owner's TimeWall site-owner dashboard screenshots,
2026-10-07 (postback setup panel: server IPs, `{hash}` scheme,
example postback URL with all macros).

> Do not confuse TimeWall with **TimeBucks** — TimeBucks is a rewards
> website (a competitor of EarnPlus), not a provider you can plug in.

---

## Testing without real providers

EarnPlus ships with a **Demo Tasks** provider (always on) that serves
5 sample tasks and runs them through the real signed postback endpoint,
so you can test the full earn flow with zero accounts.

## If a postback isn't crediting

1. EarnPlus admin → Offerwalls → **Conversion log**: every postback is
   logged with its status (`credited`, `duplicate`, `rejected`) and a
   reason (e.g. `invalid_signature`, `no_matching_click`).
2. `duplicate` means the network re-sent an already-paid conversion —
   that's normal and safe (no double pay).
3. `rejected / no_matching_click` usually means the user opened the
   wall without going through your app's task link first.
4. You can manually **credit** or **reject** any pending conversion
   from the log.
