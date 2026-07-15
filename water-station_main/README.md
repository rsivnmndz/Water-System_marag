# White-Label Water Station System (PHP + Supabase)

Complete management system para sa water refilling station: **POS / order tracker,
customer records, utang tracker, inventory, gastos, at public online ordering** —
lahat naka-white-label sa isang config file.

**Stack:** Traditional PHP (7.4+) + Supabase (Postgres via PostgREST API).
Walang framework, walang build step, walang Postgres driver na kailangan sa hosting —
plain cURL lang. Tumatakbo sa kahit anong cheap shared hosting (Hostinger, z.com, atbp.).

---

## Setup (15 minutes)

**1. Create a Supabase project** → [supabase.com](https://supabase.com) → New project (free tier ok).

**2. Run the schema** → Supabase Dashboard → **SQL Editor** → New query →
paste the contents of `schema.sql` → **Run**. This creates all tables, locks them
with RLS, and seeds the price list, starter inventory, and default login.

**3. Get your keys** → **Project Settings → API**:
- Project URL (e.g. `https://abcd1234.supabase.co`)
- `service_role` secret key

**4. Edit `config.php`** — brand name, tagline, color, phone, address, plus the
two Supabase values above.

**5. Upload everything** to your PHP host (public_html) — or run locally:
```bash
php -S localhost:8000
```

**6. Login** at `/login.php` → `admin` / `admin123` → **palitan agad ang password**
(SQL Editor):
```sql
update staff_users set password_hash = crypt('BagongPassword123', gen_salt('bf'))
where username = 'admin';
```

---

## White-label checklist (per client deploy)

Isang file lang ang ginagalaw: **`config.php`**

| Setting | Ano ito |
|---|---|
| `BRAND_NAME` / `BRAND_TAGLINE` | Pangalan at tagline ng istasyon |
| `BRAND_COLOR` | Primary accent — buong UI sumusunod |
| `BRAND_INK` | Dark tone (sidebar/headings) |
| `BRAND_PHONE` / `BRAND_ADDRESS` | Lalabas sa public order page |
| `SUPABASE_URL` / `SUPABASE_SERVICE_KEY` | Isang Supabase project **per client** |

Bawat client = sariling Supabase project (libreng tier) + sariling copy ng files.
Hiwalay ang data, hiwalay ang keys — walang multi-tenant risk.

---

## Pages

| Page | Para saan |
|---|---|
| `index.php` | Dashboard — benta ngayon, nakolekta, pending deliveries, utang board, low stock |
| `order-new.php` | POS — walk-in o delivery, live total at sukli, utang handling |
| `orders.php` | Order tracker — pending → for delivery → delivered, bayad, cancel |
| `customers.php` | Customer records — search, add, balances |
| `customer-view.php` | Profile + ledger (orders at payments) + tanggap bayad |
| `utang.php` | Utang tracker — chalkboard view, quick payment per suki |
| `inventory.php` | Stock levels, low-stock alerts, adjustments na may movement log |
| `expenses.php` | Buwanang gastos per category |
| `order-online.php` | **Public** — customers can order delivery, COD, walang login |

## Paano gumagana ang utang

1. Order na kulang ang bayad → may `due`.
2. Ang `due` ay pumapasok sa **customer balance** kapag *completed* ang order
   (walk-in = agad; delivery = pag-click ng "Delivered ✓").
3. Bawat bayad ay naitatala sa `utang_payments` at binabawas sa balance —
   makikita lahat sa ledger ng customer.
4. Online orders = COD: hindi pa utang hangga't hindi delivered.

## Security notes

- `service_role` key ay **server-side lang** (PHP) — hindi kailanman sa JavaScript/browser.
- Lahat ng tables ay naka-RLS lock; tanging service key ang nakakadaan.
- Lahat ng staff pages ay may session guard + CSRF token sa bawat form.
- Lahat ng output ay naka-escape (`e()`); prices ay kinukuha server-side, hindi galing sa form.
- Public order form ay may honeypot laban sa bots.

## Sensible next builds (v2 ideas)

- Auto-deduct ng inventory kapag may nabentang bagong container (link products → inventory items).
- SMS notification pag "for delivery" na (Semaphore/Twilio API).
- Products management UI (ngayon: i-edit sa Supabase table editor).
- Weekly/monthly sales report page na may export to CSV.
- Refund/void flow para sa completed orders (ngayon: manual adjustment sa Supabase).

## Known simplifications

- Balance updates are read-then-write (hindi atomic). Sa dami ng transaksyon ng
  isang water station, hindi ito praktikal na isyu; kung lalaki ang scale,
  ilipat sa isang Postgres function (`rpc`) ang balance math.
- Isang role lang ang meaningful sa ngayon (`owner`/`staff` ay pareho ang access).
