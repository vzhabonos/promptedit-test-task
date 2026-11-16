# Pixel Studio

Users sign in, write a prompt, pick a size and quality, and generate an image through
[Pixelforge](https://pixelforge.promptedit.dev/docs). They see a price range before submitting,
are charged the exact cost plus 25% once a generation finishes, and pay nothing when it fails.

Laravel 13 · Inertia 3 · Vue 3.5 · TypeScript · Tailwind 4 · PostgreSQL · Pest 5

## Running it locally

Requires Docker. Everything runs in [Laravel Sail](https://laravel.com/docs/sail) (PHP 8.5 + Postgres 18).

```bash
cp .env.example .env              # then set PIXELFORGE_API_KEY and PIXELFORGE_WEBHOOK_SECRET
# Install PHP dependencies without PHP on the host (Sail's own image):
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/var/www/html -w /var/www/html \
    laravelsail/php84-composer:latest composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail composer dev    # web server, queue worker, scheduler, logs and Vite
```

Open http://localhost, register, buy some (fake) credits and generate.

**Webhooks locally:** Pixelforge only calls public URLs, so expose the app with a tunnel
(e.g. `cloudflared tunnel --url http://localhost`) and set
`https://<tunnel>/webhooks/pixelforge` as the webhook URL of your *local* API key.
Without a tunnel the app still works: generations are polled once their webhooks are overdue.

**Operators:** `./vendor/bin/sail artisan app:make-admin you@example.com` unlocks the spend report.

**Checks:** `./vendor/bin/sail composer ci:check` runs formatting, lint, `vue-tsc`, Pint, PHPStan (level 7)
and the Pest suite (against Postgres, like production).

## How it works

```
Generate page ──POST /generations──▶ CreateGeneration ──▶ generations row (pending) ──▶ SubmitGeneration job
                                                                                          │ POST /v1/generations
                                                                                          ▼  (Idempotency-Key = our id)
Pixelforge ──webhook──▶ VerifyPixelforgeSignature ──▶ PixelforgeWebhookController ──┐
pixelforge:reconcile (every minute, overdue only) ──GET /v1/generations/{id}────────┼──▶ SyncGeneration ──▶ GenerationBilling
SubmitGeneration (the 202 response) ────────────────────────────────────────────────┘
```

| Where | What |
|---|---|
| `app/Billing` | `Money` (nanodollars), `CostCalculator` (the only place tokens become money), `GenerationBilling`, `CreditBalance` |
| `app/Pixelforge` | `PixelforgeClient` (the only HTTP entry point), `PixelforgeException`, `WebhookSignature`, `PricingCache`, typed API data |
| `app/Actions` | `CreateGeneration`, `SyncGeneration`, `FailGeneration` |
| `app/Jobs/SubmitGeneration.php` | Submission with retries |
| `app/Console/Commands/ReconcileGenerations.php` | Polling fallback for missed webhooks |
| `app/Reports/SpendReport.php` | Admin report, reconciled with `GET /v1/usage` |

## Decisions

### Money
- **Integer nanodollars everywhere** (`bigint`). Pixelforge reports exact 9-decimal amounts, so every
  provider cost is a whole number of nanodollars. Decimal strings are parsed with `brick/math`; there
  are no floats. `Money::fromDecimal` refuses to round, so an unexpected sub-nanodollar amount fails
  loudly instead of being silently miscounted.
- **Actual cost uses the rates stored on the generation** (Pixelforge fixes them at creation), never
  the current price list. `GET /v1/pricing` is only used for estimates, and is cached (fresh 5 min,
  served stale up to a day while it refreshes).
- **Markup:** `charge = ceil(provider_cost × 1.25)` to the nanodollar, so we never undercharge.
  At today's rates it is always exact anyway.
- **Estimates are a guaranteed range.** Output and reference-image tokens are exact. Text tokens are
  `ceil(chars/4)` ±15% (Unicode characters). Reasoning tokens have a min/max per quality. The page
  shows the range (`$min–$max`), the per-token breakdown and "you'll never pay more than $max". We
  store both bounds; the maximum is what the balance holds while the generation runs.
- **Display is exact too.** Amounts drop trailing zeros but are never rounded (`$0.3818875`).
  Users see what they paid. Pixelforge's own cost is operator-only.

### Exactly-once billing
- **One code path.** Every Pixelforge generation object (the create response, an idempotent
  replay, a webhook, a poll) goes through `SyncGeneration`. It runs in a transaction and locks the
  row (`SELECT … FOR UPDATE`).
- **Statuses only move forward** (`pending → queued → processing → succeeded | failed`). A late
  `processing` after `succeeded`, a duplicate `succeeded`, or the 202 arriving after the first
  webhook all become no-ops. The cost is recorded when a generation first becomes `succeeded`,
  which can only happen once.
- **Webhook dedup.** The event id is inserted (`ON CONFLICT DO NOTHING`) in the same transaction
  as its effect. A duplicate is skipped. If processing fails, the transaction rolls back and the
  event id goes with it, so Pixelforge's retry is processed normally.
- **Webhooks can beat the create response.** We send our id as `metadata.generation_id` and match
  on it, so the row is always found. It is committed before the job is dispatched.
- **Failures we only assumed.** If every submit attempt timed out but one actually reached
  Pixelforge, we marked the generation failed without a Pixelforge id. If Pixelforge later reports
  on it, Pixelforge wins, and the generation is billed as it really happened.

### Robustness
- **Submission is a queued job.** The Idempotency-Key is our generation's ULID, so retries can never
  create or bill a second generation.
  - **Retried:** rate limit, concurrency limit (3 per account), `503 overloaded`, 5xx and network
    errors. The delay is `max(Retry-After, 2^attempt capped at 60s)`, for up to 10 attempts (about
    5 minutes).
  - **Fail immediately, free, with a readable message:** content policy, validation, daily quota,
    reused key and auth errors.
  - **Crash:** a job crash (`failed()`) also fails the generation, so nothing is stuck as pending.
- **Missed webhooks:** `pixelforge:reconcile` polls queued/processing generations that have been
  quiet for 2+ minutes, at most 10 per run (the API allows 60 requests a minute).
- **Signatures:** HMAC-SHA256 over `t.body`, compared in constant time. Timestamps older than 5
  minutes are rejected (replays). The route sits outside the web middleware (no session/CSRF).

### Balances (stretch)
- `balance = purchases − Σ charged_cost − Σ estimated_cost_max of running generations`, computed
  from the records that already exist, with no separate ledger to drift out of sync. The "hold" is
  implicit and disappears when the generation finishes.
- **Concurrent submits:** submitting locks the user row, so two submits at once can't spend the
  same credits.
- **Blocking:** a generation is blocked unless the balance covers its *maximum* cost.
- **Purchases** are simulated ($5/$10/$25).

### Admin report (stretch)
- Charged amount, provider cost and profit per **UTC day of completion** and per user. This is the
  day Pixelforge bills on, not the creation day.
- **Reconciliation:** each day is compared with `GET /v1/usage` (cost and count, to the
  nanodollar), and differences are flagged.
- **Scope:** usage is per API key, so each environment reconciles against its own key.

### Smaller choices
- **Sign-in:** registration and password reset only. No email verification, so reviewers can sign
  up instantly.
- **Accounts:** deleting an account keeps its generations (`user_id` is set to null), so billing
  history and the report stay correct.
- **Images:** they aren't stored. Pixelforge URLs expire after 24 hours and the UI says so.
  Downloading them into storage would be the next step.
- **Live status:** Inertia `usePoll` (3s), only while something is running. WebSockets would be
  the upgrade path.

## Testing with Pixelforge's keywords

Put these anywhere in a prompt:

| Keyword | What happens |
|---|---|
| `[fail]` | Accepted, then fails. Shown as failed and not charged |
| `[slow]` | Takes 60–90s |
| `[duplicate]` | Every webhook arrives twice. Billed once |
| `[overloaded]` | Rejected at submission with `503 overloaded` |
| `[blocked]` | Content policy rejection. Fails immediately and free |
| `[succeed]` | Never fails at random |

`[overloaded]` is retried with backoff until the attempts run out, then the generation fails and is free.

## Deploying (Laravel Cloud)

1. **Create the app** from this repository and attach a Postgres database.
2. **Build commands:** `composer install --no-dev --optimize-autoloader`, then `npm ci && npm run build`.
   **Deploy command:** `php artisan migrate --force`.
3. **Background work:** enable the **scheduler**, and add a **queue worker** (`php artisan queue:work`).
4. **Environment:**
   - `APP_NAME`
   - `PIXELFORGE_API_KEY` / `PIXELFORGE_WEBHOOK_SECRET`: use a **separate** Pixelforge key for production.
   - `QUEUE_CONNECTION=database`, `CACHE_STORE=database`
5. **Webhook:** in the Pixelforge dashboard, set that key's webhook URL to `https://<your-app>/webhooks/pixelforge`.
6. **Operator:** `php artisan app:make-admin you@example.com`.
