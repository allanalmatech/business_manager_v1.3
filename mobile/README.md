# Business Manager — phone app

A till that keeps working when the network does not.

Everything a cashier does is written to the phone first and sent to the shop's
server afterwards. Sales, stock changes and new customers queue up while the
phone is offline and go through when it can reach the server again, without
anyone having to remember to press anything.

## Building it

Needs Flutter 3.22 or newer.

```bash
flutter pub get

# Point the app at your shop's server. Without this it will not find one.
flutter run       --dart-define=BM_BASE_URL=https://yourshop.example.com
flutter build apk --dart-define=BM_BASE_URL=https://yourshop.example.com --release
```

Signing a release build: put `key.properties` in `android/` with `storeFile`,
`storePassword`, `keyAlias` and `keyPassword`. Without it the build falls back
to debug signing, which is fine for testing and not for handing out.

The server must be running Business Manager 1.4.0 or newer; the app talks to
`/api/v1` (see `docs/API.md` in the main repository).

## How it is put together

```
lib/
  core/        settings, money, results, ids — no Flutter, no screens
  domain/      what a product, customer and sale are, and the basket's rules
  data/
    local/     the phone's own database, and the product photo cache
    remote/    the one place that talks HTTP
    repositories/  sign-in, sync, sales, catalogue
  features/    one folder per screen
  platform/    Bluetooth printing and the ESC/POS bytes
  widgets/     the few pieces used on more than one screen
```

A screen never calls the network and never writes SQL. It reads from a
repository and shows what it gets. That is what makes the offline behaviour
possible to reason about: there is exactly one path in and one path out.

## Working offline

**The phone's database is what the screens read.** Sync fills it; nothing else
does. So a screen is never waiting on a connection, and the app opens straight
into the till even with the radio off.

**Every change becomes a queued action with a unique id.** The server applies
each id once, so the phone can resend the whole queue as often as it likes —
after a crash, a flat battery, a tunnel — without a sale being recorded twice.

**Sales are never refused for being offline.** The sale already happened: the
customer has the goods. The server records it and flags anything unusual
(sold below cost, more than was in stock) for a supervisor to look at under
Administration → Devices, rather than rejecting it after the fact.

**Stock is sent as a change, not a total.** "Three fewer" rather than "now
seven". Two phones selling the same product at once both count, instead of the
slower one overwriting the faster one.

**The phone subtracts its own unsent sales from the stock it shows.** A cashier
offline sees the shelf going down as they sell, not a number frozen at the last
sync.

**Edits carry what the phone last saw.** Changing a price sends the old value
too. If someone at the office changed it in the meantime, the change is refused
and shown as a conflict instead of quietly winning.

## Photos

Taken with the camera, shrunk on the phone to at most 1280 px and about 200 KB
before being uploaded, because a 4 MB photo over a mobile connection is slow
and expensive and the server only ever shows it small.

Downloaded photos are kept on the phone and reused. Each use is timestamped;
when the folder passes its budget (60 MB) the ones nobody has looked at for
longest are deleted first, so storage does not creep upwards.

## Printing

Receipts go straight to a Bluetooth printer as ESC/POS bytes, with no network
involved. Pair the printer in the phone's own Bluetooth settings, then choose
it under More → Receipt printer and print a test.

The Android side is a small Kotlin class
(`android/.../BluetoothPrinterPlugin.kt`) that opens a plain serial connection,
writes the bytes in small pieces, and closes it. A connection per receipt is
slower than holding one open, but it survives the printer being switched off
between customers, which is how they are actually used.

## Checking the code without building

`tools/check_mobile.py` in the main repository walks these sources for unbalanced
delimiters, imports that point nowhere, duplicate class names and calls to
methods that do not exist. It is not a compiler, but it catches the mistakes
that come from editing many files at once.
