-- ================================================================
--  VERCEL / BROWSER VERSION — ACCESS POLICIES
--  Run AFTER schema.sql, in Supabase Dashboard → SQL Editor.
--
--  The dashboard now runs fully in the browser with the anon key,
--  so signed-in staff (Supabase Auth) need RLS policies to read
--  and write. Security model:
--    1. RLS grants access only to the `authenticated` role.
--    2. You DISABLE public sign-ups, so the only authenticated
--       users are the accounts YOU create in the dashboard.
--
--  Required manual steps in the Supabase Dashboard:
--    A. Authentication → Users → Add user
--       (email + password, tick "Auto Confirm User")
--    B. Authentication → Sign In / Providers → Email →
--       turn OFF "Allow new users to sign up"
-- ================================================================

create policy "staff full access" on customers
  for all to authenticated using (true) with check (true);

create policy "staff full access" on products
  for all to authenticated using (true) with check (true);

create policy "staff full access" on orders
  for all to authenticated using (true) with check (true);

create policy "staff full access" on order_items
  for all to authenticated using (true) with check (true);

create policy "staff full access" on utang_payments
  for all to authenticated using (true) with check (true);

create policy "staff full access" on inventory_items
  for all to authenticated using (true) with check (true);

create policy "staff full access" on inventory_movements
  for all to authenticated using (true) with check (true);

create policy "staff full access" on expenses
  for all to authenticated using (true) with check (true);

-- ----------------------------------------------------------------
-- staff_users is NOT granted any policy on purpose: it holds the
-- old PHP version's password hashes and the browser app no longer
-- uses it. It stays locked. If you never deployed the PHP version,
-- you can drop it entirely:
--
--   drop table if exists staff_users;
-- ----------------------------------------------------------------
