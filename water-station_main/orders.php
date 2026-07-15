<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

/** Recompute paid | partial | utang from amounts. */
function payment_status_for(float $total, float $paid): string
{
    if ($paid >= $total) return 'paid';
    return $paid > 0 ? 'partial' : 'utang';
}

// ---------------- Actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    try {
        $rows  = db()->select('orders', ['id' => 'eq.' . $order_id], '*', '', 1);
        $order = $rows[0] ?? null;
        if (!$order) throw new RuntimeException('Hindi mahanap ang order.');

        if ($action === 'advance') {
            if ($order['status'] === 'pending') {
                db()->update('orders', ['id' => 'eq.' . $order_id], ['status' => 'for_delivery']);
                flash('Order #' . $order_id . ' — inihahatid na.');
            } elseif ($order['status'] === 'for_delivery') {
                db()->update('orders', ['id' => 'eq.' . $order_id], [
                    'status'       => 'completed',
                    'delivered_at' => date('c'),
                ]);
                // Any unpaid balance now becomes utang on the customer record
                $due = (float)$order['total'] - (float)$order['amount_paid'];
                if ($due > 0 && $order['customer_id']) {
                    $c   = db()->select('customers', ['id' => 'eq.' . $order['customer_id']], 'balance', '', 1);
                    $bal = (float)($c[0]['balance'] ?? 0) + $due;
                    db()->update('customers', ['id' => 'eq.' . $order['customer_id']], ['balance' => $bal]);
                    flash('Order #' . $order_id . ' delivered. Na-lista ang ' . peso($due) . ' sa utang ni customer.');
                } else {
                    flash('Order #' . $order_id . ' delivered. ✓');
                }
            }
        }

        if ($action === 'pay') {
            $due = (float)$order['total'] - (float)$order['amount_paid'];
            $amt = min(max(0, (float)($_POST['amount'] ?? 0)), $due);
            if ($amt <= 0) throw new RuntimeException('Invalid na halaga.');

            $new_paid = (float)$order['amount_paid'] + $amt;
            db()->update('orders', ['id' => 'eq.' . $order_id], [
                'amount_paid'    => $new_paid,
                'payment_status' => payment_status_for((float)$order['total'], $new_paid),
            ]);

            // Completed na = nasa customer balance na ang utang, kaya babawasan
            // at ilalagay sa payment ledger. Kung pending pa, bayad-in-advance
            // lang ito — hindi pa pumapasok sa balance.
            if ($order['status'] === 'completed' && $order['customer_id']) {
                db()->insert('utang_payments', [
                    'customer_id' => $order['customer_id'],
                    'order_id'    => $order_id,
                    'amount'      => $amt,
                    'note'        => 'Bayad sa order #' . $order_id,
                ]);
                $c   = db()->select('customers', ['id' => 'eq.' . $order['customer_id']], 'balance', '', 1);
                $bal = (float)($c[0]['balance'] ?? 0) - $amt;
                db()->update('customers', ['id' => 'eq.' . $order['customer_id']], ['balance' => $bal]);
            }
            flash('Natanggap ang bayad na ' . peso($amt) . ' para sa order #' . $order_id . '.');
        }

        if ($action === 'cancel') {
            if ($order['status'] === 'completed') {
                throw new RuntimeException('Hindi pwedeng i-cancel ang completed order.');
            }
            db()->update('orders', ['id' => 'eq.' . $order_id], ['status' => 'cancelled']);
            flash('Order #' . $order_id . ' cancelled.');
        }
    } catch (Throwable $t) {
        flash($t->getMessage(), 'err');
    }

    header('Location: orders.php' . (isset($_GET['f']) ? '?f=' . urlencode($_GET['f']) : ''));
    exit;
}

// ---------------- List ----------------
$f = $_GET['f'] ?? 'open';
$filters = [];
if ($f === 'open')          $filters['status'] = 'in.(pending,for_delivery)';
elseif ($f === 'completed') $filters['status'] = 'eq.completed';
elseif ($f === 'unpaid')    $filters['payment_status'] = 'in.(utang,partial)';
elseif ($f === 'cancelled') $filters['status'] = 'eq.cancelled';

$orders = db()->select('orders', $filters, '*,customers(name),order_items(product_name,qty)', 'created_at.desc', 200);

$tabs = [
    'open'      => 'Open',
    'unpaid'    => 'May utang',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'all'       => 'Lahat',
];

$title = 'Orders';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div>
    <h1 style="margin-bottom:2px;">Orders</h1>
    <p class="sub" style="margin:0;">Sundan ang bawat order mula pending hanggang delivered at bayad.</p>
  </div>
  <a class="btn" href="order-new.php">+ Bagong order</a>
</div>

<div class="tabs" style="margin-bottom:16px;">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab<?= $f === $key ? ' is-active' : '' ?>" href="orders.php?f=<?= $key ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <tr>
        <th>#</th><th>Customer</th><th>Laman</th><th>Status</th>
        <th class="num">Total</th><th class="num">Bayad</th><th>Aksyon</th>
      </tr>
      <?php if (!$orders): ?>
        <tr><td colspan="7" class="muted">Walang orders sa filter na ito.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o):
          $cname = $o['customers']['name'] ?? ($o['walkin_name'] !== '' ? $o['walkin_name'] : 'Walk-in');
          $due   = (float)$o['total'] - (float)$o['amount_paid'];
          $itemsTxt = [];
          foreach (($o['order_items'] ?? []) as $it) $itemsTxt[] = $it['qty'] . '× ' . $it['product_name'];
      ?>
        <tr>
          <td class="mono"><?= (int)$o['id'] ?></td>
          <td>
            <?= e($cname) ?>
            <?php if ($o['source'] === 'online'): ?> <span class="chip chip-online">online</span><?php endif; ?>
            <div class="muted" style="font-size:12px;"><?= e($o['order_type']) ?> · <?= date('M j, g:ia', strtotime($o['created_at'])) ?></div>
          </td>
          <td style="max-width:260px;"><?= e(implode(', ', $itemsTxt)) ?></td>
          <td>
            <span class="chip chip-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span><br>
            <span class="chip chip-<?= e($o['payment_status']) ?>" style="margin-top:4px;"><?= e($o['payment_status']) ?></span>
          </td>
          <td class="num"><?= peso($o['total']) ?></td>
          <td class="num"><?= peso($o['amount_paid']) ?></td>
          <td style="min-width:210px;">
            <?php if ($o['status'] === 'pending'): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="advance"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-sm">Ihahatid na</button>
              </form>
            <?php elseif ($o['status'] === 'for_delivery'): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="advance"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-sm">Delivered ✓</button>
              </form>
            <?php endif; ?>

            <?php if ($due > 0 && $o['status'] !== 'cancelled'): ?>
              <form method="post" class="inline-form" style="margin-top:6px;">
                <?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <input type="number" name="amount" min="0.01" step="0.01" value="<?= $due ?>" inputmode="decimal" aria-label="Halaga ng bayad">
                <button class="btn btn-sm btn-amber">Bayad</button>
              </form>
            <?php endif; ?>

            <?php if (in_array($o['status'], ['pending', 'for_delivery'], true)): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-sm btn-ghost" style="margin-top:6px;">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
