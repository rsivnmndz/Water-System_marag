-- ================================================================
--  WHITE-LABEL WATER STATION SYSTEM — SUPABASE SCHEMA
--  Run once: Supabase Dashboard → SQL Editor → New query → paste → Run
-- ================================================================

create extension if not exists pgcrypto;

-- ---------------------------------------------------------------
-- Staff logins (app-level auth; bcrypt hashes via pgcrypto)
-- ---------------------------------------------------------------
create table if not exists staff_users (
  id bigint generated always as identity primary key,
  username text unique not null,
  password_hash text not null,
  full_name text not null default '',
  role text not null default 'staff',          -- owner | staff
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------
-- Customers (suki records + running utang balance)
-- ---------------------------------------------------------------
create table if not exists customers (
  id bigint generated always as identity primary key,
  name text not null,
  phone text default '',
  address text default '',
  notes text default '',
  balance numeric(10,2) not null default 0,     -- outstanding utang
  created_at timestamptz not null default now()
);
create index if not exists idx_customers_phone on customers(phone);

-- ---------------------------------------------------------------
-- Products (price list shown in POS + online order form)
-- ---------------------------------------------------------------
create table if not exists products (
  id bigint generated always as identity primary key,
  name text not null,
  price numeric(10,2) not null default 0,
  is_active boolean not null default true,
  sort_order int not null default 0
);

-- ---------------------------------------------------------------
-- Orders + line items
-- ---------------------------------------------------------------
create table if not exists orders (
  id bigint generated always as identity primary key,
  customer_id bigint references customers(id) on delete set null,
  walkin_name text default '',
  order_type text not null default 'walkin',    -- walkin | delivery
  status text not null default 'pending',       -- pending | for_delivery | completed | cancelled
  total numeric(10,2) not null default 0,
  amount_paid numeric(10,2) not null default 0,
  payment_status text not null default 'paid',  -- paid | partial | utang
  source text not null default 'counter',       -- counter | online
  notes text default '',
  created_at timestamptz not null default now(),
  delivered_at timestamptz
);
create index if not exists idx_orders_status  on orders(status);
create index if not exists idx_orders_created on orders(created_at);

create table if not exists order_items (
  id bigint generated always as identity primary key,
  order_id bigint not null references orders(id) on delete cascade,
  product_id bigint references products(id) on delete set null,
  product_name text not null,
  qty int not null default 1,
  price numeric(10,2) not null default 0,
  subtotal numeric(10,2) not null default 0
);
create index if not exists idx_items_order on order_items(order_id);

-- ---------------------------------------------------------------
-- Utang payments (cash-ins against completed orders / balances)
-- ---------------------------------------------------------------
create table if not exists utang_payments (
  id bigint generated always as identity primary key,
  customer_id bigint references customers(id) on delete cascade,
  order_id bigint references orders(id) on delete set null,
  amount numeric(10,2) not null,
  note text default '',
  created_at timestamptz not null default now()
);
create index if not exists idx_payments_customer on utang_payments(customer_id);

-- ---------------------------------------------------------------
-- Inventory (consumables + containers) with movement log
-- ---------------------------------------------------------------
create table if not exists inventory_items (
  id bigint generated always as identity primary key,
  name text not null,
  unit text not null default 'pcs',
  stock_qty numeric(10,2) not null default 0,
  reorder_level numeric(10,2) not null default 0,
  created_at timestamptz not null default now()
);

create table if not exists inventory_movements (
  id bigint generated always as identity primary key,
  item_id bigint not null references inventory_items(id) on delete cascade,
  change_qty numeric(10,2) not null,            -- positive = stock in, negative = stock out
  reason text default '',
  created_at timestamptz not null default now()
);
create index if not exists idx_moves_item on inventory_movements(item_id);

-- ---------------------------------------------------------------
-- Expenses (gastos)
-- ---------------------------------------------------------------
create table if not exists expenses (
  id bigint generated always as identity primary key,
  description text not null,
  category text not null default 'others',      -- electricity | water | supplies | salary | maintenance | others
  amount numeric(10,2) not null,
  expense_date date not null default current_date,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------------
-- Row Level Security: lock every table. The PHP app talks to
-- PostgREST with the service_role key, which bypasses RLS.
-- NEVER expose the service_role key in browser-side code.
-- ---------------------------------------------------------------
alter table staff_users         enable row level security;
alter table customers           enable row level security;
alter table products            enable row level security;
alter table orders              enable row level security;
alter table order_items         enable row level security;
alter table utang_payments      enable row level security;
alter table inventory_items     enable row level security;
alter table inventory_movements enable row level security;
alter table expenses            enable row level security;

-- ---------------------------------------------------------------
-- Seed: default login  →  admin / admin123   (CHANGE AFTER SETUP)
-- To change later:
--   update staff_users set password_hash = crypt('NewPass123', gen_salt('bf'))
--   where username = 'admin';
-- ---------------------------------------------------------------
insert into staff_users (username, password_hash, full_name, role)
values ('admin', crypt('admin123', gen_salt('bf')), 'Station Owner', 'owner')
on conflict (username) do nothing;

-- Seed: common PH water station price list (edit freely in Supabase)
insert into products (name, price, sort_order) values
  ('Refill — Slim (5 gal)',              25, 1),
  ('Refill — Round (5 gal)',             25, 2),
  ('Bagong Slim Container (may laman)', 230, 3),
  ('Bagong Round Container (may laman)',250, 4),
  ('Delivery Fee',                       10, 5);

-- Seed: starter inventory
insert into inventory_items (name, unit, stock_qty, reorder_level) values
  ('Empty Slim Containers',  'pcs',  30, 10),
  ('Empty Round Containers', 'pcs',  20, 10),
  ('Caps / Covers',          'pcs', 200, 50),
  ('Heat-Shrink Seals',      'pcs', 200, 50),
  ('Faucet / Non-Leak Caps', 'pcs',  40, 10);
