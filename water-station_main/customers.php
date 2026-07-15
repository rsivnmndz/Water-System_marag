<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('Kailangan ng pangalan.', 'err');
    } else {
        try {
            db()->insert('customers', [
                'name'    => $name,
                'phone'   => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'notes'   => trim($_POST['notes'] ?? ''),
            ]);
            flash('Naidagdag si ' . $name . ' sa customer records.');
        } catch (Throwable $t) {
            flash('Hindi na-save: ' . $t->getMessage(), 'err');
        }
    }
    header('Location: customers.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$filters = [];
if ($q !== '') $filters['name'] = 'ilike.*' . $q . '*';

$customers = db()->select('customers', $filters, '*', 'name.asc', 500);

$title = 'Customers';
require __DIR__ . '/includes/header.php';
?>

<h1>Customer Records</h1>
<p class="sub">Mga suki, contact details, at kanilang balanse.</p>

<div class="grid grid-2">
  <div class="card">
    <form method="get" class="inline-form" style="margin-bottom:14px;">
      <input name="q" value="<?= e($q) ?>" placeholder="Hanapin ang pangalan…" style="width:100%;">
      <button class="btn btn-sm btn-ghost">Hanap</button>
    </form>

    <div class="table-wrap">
      <table>
        <tr><th>Pangalan</th><th>Contact</th><th class="num">Utang</th></tr>
        <?php if (!$customers): ?>
          <tr><td colspan="3" class="muted">Walang nahanap<?= $q ? ' para sa "' . e($q) . '"' : '' ?>.</td></tr>
        <?php endif; ?>
        <?php foreach ($customers as $c): $bal = (float)$c['balance']; ?>
          <tr>
            <td>
              <a href="customer-view.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['name']) ?></strong></a>
              <?php if ($c['address']): ?><div class="muted" style="font-size:12px;"><?= e($c['address']) ?></div><?php endif; ?>
            </td>
            <td class="muted"><?= e($c['phone'] ?: '—') ?></td>
            <td class="num <?= $bal > 0 ? 'amt-neg' : '' ?>"><?= $bal != 0.0 ? peso($bal) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>Bagong customer</h2>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Pangalan *</label>
        <input id="name" name="name" required placeholder="hal. Mang Berto Reyes">
      </div>
      <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" placeholder="09xx xxx xxxx" inputmode="tel">
      </div>
      <div class="field">
        <label for="address">Address / landmark</label>
        <input id="address" name="address" placeholder="hal. Blk 4 Lot 9, tapat ng barangay hall">
      </div>
      <div class="field">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" placeholder="hal. 3 slim kada linggo, bayad tuwing sweldo"></textarea>
      </div>
      <button class="btn">Idagdag sa records</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
