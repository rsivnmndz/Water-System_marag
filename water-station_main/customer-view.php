<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: customers.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        $rows = db()->select('customers', ['id' => 'eq.' . $id], '*', '', 1);
        $cust = $rows[0] ?? null;
        if (!$cust) throw new RuntimeException('Hindi mahanap ang customer.');

        if ($action === 'edit') {
            db()->update('customers', ['id' => 'eq.' . $id], [
                'name'    => trim($_POST['name'] ?? $cust['name']),
                'phone'   => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'notes'   => trim($_POST['notes'] ?? ''),
            ]);
            flash('Na-update ang customer info.');
        }

        if ($action === 'pay') {
            $amt = (float)($_POST['amount'] ?? 0);
            if ($amt <= 0) throw new RuntimeException('Invalid na halaga.');

            db()->insert('utang_payments', [
                'customer_id' => $id,
                'amount'      => $amt,
                'note'        => trim($_POST['note'] ?? '') ?: 'Bayad sa balanse',
            ]);
            $newBal = (float)$cust['balance'] - $amt;
            db()->update('customers', ['id' => 'eq.' . $id], ['balance' => $newBal]);

            $msg = 'Natanggap ang bayad na ' . peso($amt) . '.';
            if ($newBal < 0) $msg .= ' May advance/credit na ' . peso(abs($newBal)) . '.';
            flash($msg);
        }
    } catch (Throwable $t) {
        flash($t->getMessage(), 'err');
    }
    header('Location: customer-view.php?id=' . $id);
    exit;
}

$rows = db()->select('customers', ['id' => 'eq.' . $id], '*', '', 1);
$cust = $rows[0] ?? null;
if (!$cust) { header('Location: customers.php'); exit; }

$orders   = db()->select('orders', ['customer_id' => 'eq.' . $id], 'id,total,amount_paid,status,payment_status,created_at', 'created_at.desc', 200);
$payments = db()->select('utang_payments', ['customer_id' => 'eq.' . $id], '*', 'created_at.desc', 200);

// Merge into one ledger, newest first
$ledger = [];
foreach ($orders as $o) {
    $ledger[] = ['ts' => $o['created_at'], 'kind' => 'order', 'row' => $o];
}
foreach ($payments as $p) {
    $ledger[] = ['ts' => $p['created_at'], 'kind' => 'payment', 'row' => $p];
}
usort($ledger, fn($a, $b) => strcmp($b['ts'], $a['ts']));

$bal   = (float)$cust['balance'];
$title = $cust['name'];
require __DIR__ . '/includes/header.php';
?>

<p style="margin-bottom:6px;"><a href="customers.php">← Balik sa customers</a></p>
<h1><?= e($cust['name']) ?></h1>
<p class="sub"><?= e($cust['phone'] ?: 'Walang phone') ?> · <?= e($cust['address'] ?: 'Walang address') ?></p>

<div class="grid grid-2">
  <div>
    <div class="chalkboard">
      <div class="board-title">Balanse ni <?= e($cust['name']) ?></div>
      <div class="chalk-total" style="border-top:0;margin-top:0;padding-top:0;">
        <span><?= $bal > 0 ? 'Utang' : ($bal < 0 ? 'Advance / credit' : 'Wala nang utang') ?></span>
        <span class="amt"><?= peso(abs($bal)) ?></span>
      </div>
    </div>

    <div class="card">
      <h2>Tanggap bayad</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="pay">
        <div class="row">
          <div class="field">
            <label for="amount">Halaga</label>
            <input id="amount" name="amount" type="number" min="0.01" step="0.01"
                   value="<?= $bal > 0 ? $bal : '' ?>" required inputmode="decimal">
          </div>
          <div class="field">
            <label for="note">Note</label>
            <input id="note" name="note" placeholder="hal. hulog, GCash, atbp.">
          </div>
        </div>
        <button class="btn btn-amber">Tanggap bayad</button>
        <div class="hint">Sobrang bayad = magiging advance/credit ng customer.</div>
      </form>
    </div>

    <div class="card">
      <h2>I-edit ang info</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit">
        <div class="field"><label for="name">Pangalan</label><input id="name" name="name" value="<?= e($cust['name']) ?>" required></div>
        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($cust['phone']) ?>"></div>
        <div class="field"><label for="address">Address</label><input id="address" name="address" value="<?= e($cust['address']) ?>"></div>
        <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes"><?= e($cust['notes']) ?></textarea></div>
        <button class="btn btn-ghost">I-save ang changes</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>Ledger</h2>
    <div class="table-wrap">
      <table>
        <tr><th>Petsa</th><th>Detalye</th><th class="num">Halaga</th></tr>
        <?php if (!$ledger): ?>
          <tr><td colspan="3" class="muted">Wala pang transactions.</td></tr>
        <?php endif; ?>
        <?php foreach ($ledger as $en): $r = $en['row']; ?>
          <?php if ($en['kind'] === 'order'): $due = (float)$r['total'] - (float)$r['amount_paid']; ?>
            <tr>
              <td class="muted" style="white-space:nowrap;"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
              <td>
                Order #<?= (int)$r['id'] ?>
                <span class="chip chip-<?= e($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                <span class="chip chip-<?= e($r['payment_status']) ?>"><?= e($r['payment_status']) ?></span>
                <?php if ($due > 0): ?><div class="muted" style="font-size:12px;">Balanse: <?= peso($due) ?></div><?php endif; ?>
              </td>
              <td class="num"><?= peso($r['total']) ?></td>
            </tr>
          <?php else: ?>
            <tr>
              <td class="muted" style="white-space:nowrap;"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
              <td>Bayad<?= $r['note'] ? ' — ' . e($r['note']) : '' ?></td>
              <td class="num amt-pos">−<?= peso($r['amount']) ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
