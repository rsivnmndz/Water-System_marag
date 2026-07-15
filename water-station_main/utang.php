<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid = (int)($_POST['customer_id'] ?? 0);
    $amt = (float)($_POST['amount'] ?? 0);

    try {
        if ($cid <= 0 || $amt <= 0) throw new RuntimeException('Invalid na bayad.');
        $rows = db()->select('customers', ['id' => 'eq.' . $cid], 'name,balance', '', 1);
        $cust = $rows[0] ?? null;
        if (!$cust) throw new RuntimeException('Hindi mahanap ang customer.');

        db()->insert('utang_payments', [
            'customer_id' => $cid,
            'amount'      => $amt,
            'note'        => 'Bayad sa balanse',
        ]);
        db()->update('customers', ['id' => 'eq.' . $cid], [
            'balance' => (float)$cust['balance'] - $amt,
        ]);
        flash('Natanggap ang ' . peso($amt) . ' mula kay ' . $cust['name'] . '.');
    } catch (Throwable $t) {
        flash($t->getMessage(), 'err');
    }
    header('Location: utang.php');
    exit;
}

$utangers = db()->select('customers', ['balance' => 'gt.0'], 'id,name,phone,balance', 'balance.desc', 500);
$total = 0.0;
foreach ($utangers as $c) $total += (float)$c['balance'];

$paymentsToday = db()->select('utang_payments', [
    'created_at' => 'gte.' . date('c', strtotime('today')),
], 'amount', '', 500);
$collected = 0.0;
foreach ($paymentsToday as $p) $collected += (float)$p['amount'];

$title = 'Utang';
require __DIR__ . '/includes/header.php';
?>

<h1>Utang Tracker</h1>
<p class="sub">Ang digital na bersyon ng listahan sa pisara — pero hindi nabubura.</p>

<div class="grid grid-2" style="margin-bottom:4px;">
  <div class="card stat warn">
    <div class="label">Kabuuang utang</div>
    <div class="value mono"><?= peso($total) ?></div>
  </div>
  <div class="card stat good">
    <div class="label">Nakolektang bayad ngayon</div>
    <div class="value mono"><?= peso($collected) ?></div>
  </div>
</div>

<div class="chalkboard">
  <div class="board-title">Listahan ng Utang · <?= count($utangers) ?> customer<?= count($utangers) === 1 ? '' : 's' ?></div>
  <?php if (!$utangers): ?>
    <div class="chalk-empty">Walang utang sa ngayon. Linis ang libro. 🎉</div>
  <?php endif; ?>
  <?php foreach ($utangers as $c): ?>
    <div class="chalk-row" style="align-items:center;">
      <span>
        <a href="customer-view.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a>
        <?php if ($c['phone']): ?><span style="opacity:.65;font-size:12.5px;"> · <?= e($c['phone']) ?></span><?php endif; ?>
      </span>
      <span style="display:flex;gap:10px;align-items:center;">
        <span class="amt"><?= peso($c['balance']) ?></span>
        <form method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
          <input type="number" name="amount" min="0.01" step="0.01" value="<?= (float)$c['balance'] ?>" inputmode="decimal" aria-label="Halaga ng bayad ni <?= e($c['name']) ?>">
          <button class="btn btn-sm btn-amber">Bayad</button>
        </form>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if ($utangers): ?>
    <div class="chalk-total">
      <span>Kabuuan</span>
      <span class="amt"><?= peso($total) ?></span>
    </div>
  <?php endif; ?>
</div>

<p class="muted" style="font-size:13px;">
  Paano pumapasok ang utang dito? Kapag na-save ang order na kulang ang bayad
  (walk-in) o na-mark na <em>Delivered</em> ang delivery na may balanse — automatic
  itong nalilista sa customer. Ang bawat bayad ay may resibo sa ledger ng customer.
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
