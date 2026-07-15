<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$CATS = ['electricity' => 'Kuryente', 'water' => 'Tubig/Source', 'supplies' => 'Supplies',
         'salary' => 'Sweldo', 'maintenance' => 'Maintenance', 'others' => 'Iba pa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $desc = trim($_POST['description'] ?? '');
    $amt  = (float)($_POST['amount'] ?? 0);
    $cat  = $_POST['category'] ?? 'others';

    if ($desc === '' || $amt <= 0) {
        flash('Kailangan ng description at tamang halaga.', 'err');
    } else {
        try {
            db()->insert('expenses', [
                'description'  => $desc,
                'category'     => isset($CATS[$cat]) ? $cat : 'others',
                'amount'       => $amt,
                'expense_date' => $_POST['expense_date'] ?: date('Y-m-d'),
            ]);
            flash('Naitala ang gastos na ' . peso($amt) . '.');
        } catch (Throwable $t) {
            flash('Hindi na-save: ' . $t->getMessage(), 'err');
        }
    }
    header('Location: expenses.php?m=' . urlencode($_GET['m'] ?? date('Y-m')));
    exit;
}

$m = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$start = $m . '-01';
$end   = date('Y-m-d', strtotime($start . ' +1 month'));

$expenses = db()->select('expenses', [
    'expense_date' => 'gte.' . $start,
    'and'          => '(expense_date.lt.' . $end . ')',
], '*', 'expense_date.desc', 500);

$total = 0.0;
$byCat = [];
foreach ($expenses as $x) {
    $total += (float)$x['amount'];
    $byCat[$x['category']] = ($byCat[$x['category']] ?? 0) + (float)$x['amount'];
}

$prev = date('Y-m', strtotime($start . ' -1 month'));
$next = date('Y-m', strtotime($start . ' +1 month'));

$title = 'Gastos';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div>
    <h1 style="margin-bottom:2px;">Gastos</h1>
    <p class="sub" style="margin:0;">Buwanang operating expenses ng istasyon.</p>
  </div>
  <div class="tabs">
    <a class="tab" href="expenses.php?m=<?= $prev ?>">← <?= date('M', strtotime($prev . '-01')) ?></a>
    <span class="tab is-active"><?= date('F Y', strtotime($start)) ?></span>
    <?php if ($next <= date('Y-m')): ?><a class="tab" href="expenses.php?m=<?= $next ?>"><?= date('M', strtotime($next . '-01')) ?> →</a><?php endif; ?>
  </div>
</div>

<div class="grid grid-2">
  <div>
    <div class="card stat warn">
      <div class="label">Kabuuang gastos — <?= date('F Y', strtotime($start)) ?></div>
      <div class="value mono"><?= peso($total) ?></div>
    </div>

    <div class="card">
      <h2>Bagong gastos</h2>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="description">Description *</label><input id="description" name="description" required placeholder="hal. Bayad kuryente"></div>
        <div class="row">
          <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
              <?php foreach ($CATS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="amount">Halaga *</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required inputmode="decimal"></div>
          <div class="field"><label for="expense_date">Petsa</label><input id="expense_date" name="expense_date" type="date" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <button class="btn">Itala ang gastos</button>
      </form>
    </div>

    <?php if ($byCat): ?>
    <div class="card">
      <h2>Per category</h2>
      <div class="table-wrap"><table>
        <?php foreach ($byCat as $k => $v): ?>
          <tr><td><?= e($CATS[$k] ?? $k) ?></td><td class="num"><?= peso($v) ?></td></tr>
        <?php endforeach; ?>
      </table></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Listahan</h2>
    <div class="table-wrap">
      <table>
        <tr><th>Petsa</th><th>Description</th><th>Category</th><th class="num">Halaga</th></tr>
        <?php if (!$expenses): ?>
          <tr><td colspan="4" class="muted">Walang gastos na naitala ngayong buwan.</td></tr>
        <?php endif; ?>
        <?php foreach ($expenses as $x): ?>
          <tr>
            <td class="muted" style="white-space:nowrap;"><?= date('M j', strtotime($x['expense_date'])) ?></td>
            <td><?= e($x['description']) ?></td>
            <td class="muted"><?= e($CATS[$x['category']] ?? $x['category']) ?></td>
            <td class="num"><?= peso($x['amount']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
