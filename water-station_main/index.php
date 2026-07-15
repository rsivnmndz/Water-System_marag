<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$setup_error = '';
$salesToday = 0.0; $collectedToday = 0.0; $pendingCount = 0;
$utangTotal = 0.0; $utangTop = []; $lowStock = []; $recent = [];

try {
    $todayIso = date('c', strtotime('today'));

    $todays = db()->select('orders', [
        'created_at' => 'gte.' . $todayIso,
        'status'     => 'neq.cancelled',
    ], 'total,amount_paid');
    foreach ($todays as $o) {
        $salesToday     += (float)$o['total'];
        $collectedToday += (float)$o['amount_paid'];
    }

    $pending      = db()->select('orders', ['status' => 'in.(pending,for_delivery)'], 'id', '', 500);
    $pendingCount = count($pending);

    $utangers = db()->select('customers', ['balance' => 'gt.0'], 'id,name,balance', 'balance.desc', 200);
    foreach ($utangers as $c) $utangTotal += (float)$c['balance'];
    $utangTop = array_slice($utangers, 0, 6);

    $items = db()->select('inventory_items', [], '*', 'name.asc', 200);
    foreach ($items as $it) {
        if ((float)$it['stock_qty'] <= (float)$it['reorder_level']) $lowStock[] = $it;
    }

    $recent = db()->select('orders', [], '*,customers(name)', 'created_at.desc', 8);
} catch (Throwable $t) {
    $setup_error = $t->getMessage();
}

$title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<h1>Kamusta ang benta ngayon?</h1>
<p class="sub"><?= date('l, F j, Y') ?> · <?= e(BRAND_NAME) ?></p>

<?php if ($setup_error): ?>
  <div class="card">
    <h2>Setup muna tayo</h2>
    <p>Hindi maka-connect sa Supabase. Dalawang bagay ang chine-check:</p>
    <p>1. Na-run mo na ba ang <span class="mono">schema.sql</span> sa Supabase SQL Editor?<br>
       2. Tama ba ang <span class="mono">SUPABASE_URL</span> at <span class="mono">SUPABASE_SERVICE_KEY</span> sa <span class="mono">config.php</span>?</p>
    <p class="muted mono" style="font-size:12.5px;"><?= e($setup_error) ?></p>
  </div>
<?php else: ?>

  <div class="grid grid-4">
    <div class="card stat brand">
      <div class="label">Benta ngayon</div>
      <div class="value mono"><?= peso($salesToday) ?></div>
    </div>
    <div class="card stat good">
      <div class="label">Nakolekta ngayon</div>
      <div class="value mono"><?= peso($collectedToday) ?></div>
    </div>
    <div class="card stat">
      <div class="label">Pending deliveries</div>
      <div class="value"><?= $pendingCount ?></div>
    </div>
    <div class="card stat warn">
      <div class="label">Total utang</div>
      <div class="value mono"><?= peso($utangTotal) ?></div>
    </div>
  </div>

  <div class="grid grid-2 mt">
    <div>
      <div class="chalkboard">
        <div class="board-title">Listahan ng Utang</div>
        <?php if (!$utangTop): ?>
          <div class="chalk-empty">Walang utang. Linis ang libro. 🎉</div>
        <?php else: ?>
          <?php foreach ($utangTop as $c): ?>
            <div class="chalk-row">
              <a href="customer-view.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a>
              <span class="amt"><?= peso($c['balance']) ?></span>
            </div>
          <?php endforeach; ?>
          <div class="chalk-total">
            <span>Kabuuan</span>
            <span class="amt"><?= peso($utangTotal) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <a class="btn btn-ghost" href="utang.php">Buksan ang buong listahan</a>
    </div>

    <div class="card">
      <h2>Inventory na paubos na</h2>
      <?php if (!$lowStock): ?>
        <p class="muted">Sapat pa ang stock ng lahat. Ayos!</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <tr><th>Item</th><th class="num">Stock</th><th class="num">Reorder sa</th></tr>
            <?php foreach ($lowStock as $it): ?>
              <tr>
                <td><?= e($it['name']) ?> <span class="chip chip-low">Kulang na</span></td>
                <td class="num"><?= (float)$it['stock_qty'] ?> <?= e($it['unit']) ?></td>
                <td class="num"><?= (float)$it['reorder_level'] ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <a class="btn btn-ghost btn-sm mt" href="inventory.php" style="display:inline-block;">Ayusin ang stock</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mt">
    <div class="toolbar" style="margin-bottom:8px;">
      <h2 style="margin:0;">Mga huling order</h2>
      <a class="btn btn-sm" href="order-new.php">+ Bagong order</a>
    </div>
    <div class="table-wrap">
      <table>
        <tr><th>#</th><th>Customer</th><th>Type</th><th>Status</th><th>Bayad</th><th class="num">Total</th><th>Oras</th></tr>
        <?php if (!$recent): ?>
          <tr><td colspan="7" class="muted">Wala pang orders. Simulan sa "Bagong Order".</td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $o):
            $cname = $o['customers']['name'] ?? ($o['walkin_name'] !== '' ? $o['walkin_name'] : 'Walk-in');
        ?>
          <tr>
            <td class="mono"><?= (int)$o['id'] ?></td>
            <td><?= e($cname) ?><?= $o['source'] === 'online' ? ' <span class="chip chip-online">online</span>' : '' ?></td>
            <td><?= e($o['order_type']) ?></td>
            <td><span class="chip chip-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
            <td><span class="chip chip-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></td>
            <td class="num"><?= peso($o['total']) ?></td>
            <td class="muted"><?= date('M j, g:ia', strtotime($o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
