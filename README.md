# Water Station Dashboard — Vercel Edition

Admin-only management dashboard for a water refilling station:
**POS / order tracker, customer records, credit (utang) tracker, inventory,
and expenses.** No public ordering — only signed-in staff can create orders.

**Stack:** one static `index.html` (vanilla JS + supabase-js) + Supabase.
No PHP, no server, no build step — deploys straight to **Vercel** (or Netlify,
GitHub Pages, any static host).

---

## Setup (10 minutes)

**1. Supabase project** → [supabase.com](https://supabase.com) → New project.

**2. Create the tables** → SQL Editor → paste and run `schema.sql`
(from the original package — skip if you already ran it).

**3. Grant access to the browser app** → SQL Editor → paste and run
`vercel-setup.sql` (the RLS policies in this folder).

**4. Create your admin login** → Authentication → **Users** → *Add user* →
email + password → tick **Auto Confirm User**.

**5. Lock the door** → Authentication → **Sign In / Providers** → Email →
turn **OFF** "Allow new users to sign up".
*This step is what keeps strangers out — don't skip it.*

**6. Configure the app** → open `index.html`, edit the `CONFIG` block at the
top of the script:
- `SUPABASE_URL` — Project Settings → API → Project URL
- `SUPABASE_ANON_KEY` — Project Settings → API → `anon` `public` key
- brand name, tagline, color, currency

**7. Deploy** → push `index.html` to a GitHub repo → import to Vercel → done.
(No `vercel.json` needed; a lone `index.html` at the repo root just works.)

---

## Security model (read this once)

- The **anon key in the HTML is fine to publish** — that's what it's for.
  Protection comes from Row Level Security (only `authenticated` users can
  touch data) plus **disabled sign-ups** (only accounts you create exist).
- **Never** put the `service_role` key in this file. It bypasses RLS.
- The old `staff_users` table (from the PHP version) stays locked and unused;
  logins are now Supabase Auth accounts.

## White-label checklist (per client)

One block to edit: `CONFIG` at the top of `index.html`.

| Setting | What it is |
|---|---|
| `BRAND_NAME` / `BRAND_TAGLINE` | Station name + tagline |
| `BRAND_COLOR` / `BRAND_INK` | Accent + dark tone — whole UI follows |
| `CURRENCY` | Default `₱` |
| `SUPABASE_URL` / `SUPABASE_ANON_KEY` | One Supabase project **per client** |

Per client = own Supabase project (free tier) + own copy of `index.html`
deployed to its own Vercel project. Data fully isolated, handover is trivial.

## How credit (utang) works

1. An order paid short has a `due` amount.
2. The due joins the **customer balance** only when the order is *completed*
   (walk-ins immediately; deliveries when you tap "Delivered ✓").
3. Every payment is logged and deducted from the balance — the full trail is
   in the customer's History.
4. Paying more than the balance becomes **advance credit** (negative balance).

## Known simplifications

- Balance updates are read-then-write, not atomic. With a single admin doing
  entry this is a non-issue; if multiple staff ever use it at once, move the
  balance math into a Postgres function (`rpc`).
- Products are managed in the Supabase table editor (no in-app UI yet).
