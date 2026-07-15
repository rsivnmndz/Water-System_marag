<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'adjust') {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $change  = (float)($_POST['change_qty'] ?? 0);
            $reason  = trim($_POST['reason'] ?? '');
            if ($item_id <= 0 || $change == 0.0) throw new RuntimeException('Maglagay ng quantity (positibo = pasok, negatibo = labas).');

            $rows = db()->select('inventory_items', ['id' => 'eq.' . $item_id], 'name,stock_qty', '', 1);
            $item = $rows[0] ?? null;
            if (!$item) throw new RuntimeException('Hindi mahanap ang item.');

            db()->insert('inventory_movements', [
                'item_id'    => $item_id,
                'change_qty' => $change,
                'reason'     => $reason ?: ($change > 0 ? 'Stock in' : 'Stock out'),
            ]);
            db()->update('inventory_items', ['id' => 'eq.' . $item_id], [
                'stock_qty' => (float)$item['stock_qty'] + $change,
            ]);
            flash(($change > 0 ? 'Nadagdagan' : 'Nabawasan') . ' ang ' . $item['name'] . ' ng ' . abs($change) . '.');
        }

        if ($action === 'add_item') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Kailangan ng item name.');
            db()->insert('inventory_items', [
                'name'          => $name,
                'unit'          => trim($_POST['unit'] ?? 'pcs') ?: 'pcs',
                'stock_qty'     => (float)($_POST['stock_qty'] ?? 0),
                'reorder_level' => (float)($_POST['reorder_level'] ?? 0),
            ]);
            flash('Naidagdag ang item na ' . $name . '.');
        }
    } catch (Throwable $t) {
        flash($t->getMessage(), 'err');
    }
    header('Location: inventory.php');
    exit;
}

$items = db()->select('inventory_items', [], '*', 'name.asc', 200);
$moves = db()->select('inventory_movements', [], '*,inventory_items(name)', 'created_at.desc', 12);

$title = 'Inventory';
require __DIR__ . '/includes/header.php';
?>

<h1>Inventory</h1>
<p class="sub">Containers, caps, seals, at iba pang supplies — may kasamang movement log.</p>

<div class="card">
  <div class="table-wrap">
    <table>
      <tr><th>Item</th><th class="num">Stock</th><th class="num">Reorder sa</th><th>Adjust (+pasok / −labas)</th></tr>
      <?php if (!$items): ?>
        <tr><td colspan="4" class="muted">Wala pang items — magdagdag sa ibaba.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $it): $low = (float)$it['stock_qty'] <= (float)$it['reorder_level']; ?>
        <tr>
          <td>
            <strong><?= e($it['name']) ?></strong>
            <?php if ($low): ?> <span class="chip chip-low">Kulang na</span><?php endif; ?>
          </td>
          <td class="num"><?= (float)$it['stock_qty'] ?> <?= e($it['unit']) ?></td>
          <td class="num"><?= (float)$it['reorder_level'] ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="adjust">
              <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
              <input type="number" name="change_qty" step="0.01" placeholder="+10 / −5" required aria-label="Adjust <?= e($it['name']) ?>">
              <input type="text" name="reason" placeholder="Dahilan" style="width:150px;">
              <button class="btn btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Bagong item</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_item">
      <div class="field"><label for="name">Item name *</label><input id="name" name="name" required placeholder="hal. Bleach / Sanitizer"></div>
      <div class="row">
        <div class="field"><label for="unit">Unit</label><input id="unit" name="unit" value="pcs"></div>
        <div class="field"><label for="stock_qty">Starting stock</label><input id="stock_qty" name="stock_qty" type="number" step="0.01" value="0"></div>
        <div class="field"><label for="reorder_level">Reorder level</label><input id="reorder_level" name="reorder_level" type="number" step="0.01" value="0"></div>
      </div>
      <button class="btn">Idagdag ang item</button>
    </form>
  </div>

  <div class="card">
    <h2>Huling movements</h2>
    <div class="table-wrap">
      <table>
        <tr><th>Petsa</th><th>Item</th><th class="num">Qty</th><th>Dahilan</th></tr>
        <?php if (!$moves): ?>
          <tr><td colspan="4" class="muted">Wala pang movements.</td></tr>
        <?php endif; ?>
        <?php foreach ($moves as $m): $chg = (float)$m['change_qty']; ?>
          <tr>
            <td class="muted" style="white-space:nowrap;"><?= date('M j, g:ia', strtotime($m['created_at'])) ?></td>
            <td><?= e($m['inventory_items']['name'] ?? '—') ?></td>
            <td class="num <?= $chg >= 0 ? 'amt-pos' : 'amt-neg' ?>"><?= $chg >= 0 ? '+' : '' ?><?= $chg ?></td>
            <td class="muted"><?= e($m['reason']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
