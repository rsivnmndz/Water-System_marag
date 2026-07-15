<?php
require_once __DIR__ . '/includes/supabase.php';

$products = [];
$loadErr  = '';
try {
    $products = db()->select('products', ['is_active' => 'eq.true'], '*', 'sort_order.asc', 100);
} catch (Throwable $t) {
    $loadErr = 'Pasensya na — hindi available ang online ordering ngayon.';
}

$done  = false;
$error = '';
$orderId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$loadErr) {
    // Honeypot: bots fill every field; tao hindi nakikita ito.
    if (trim($_POST['website'] ?? '') !== '') {
        $done = true;
    } else {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');
        $qtys    = $_POST['qty'] ?? [];

        $items = [];
        $total = 0.0;
        foreach ($products as $p) {
            $q = (int)($qtys[$p['id']] ?? 0);
            if ($q <= 0) continue;
            $sub     = $q * (float)$p['price'];
            $total  += $sub;
            $items[] = [
                'product_id'   => $p['id'],
                'product_name' => $p['name'],
                'qty'          => $q,
                'price'        => (float)$p['price'],
                'subtotal'     => $sub,
            ];
        }

        if ($name === '' || $phone === '' || $address === '') {
            $error = 'Pakikumpleto ang pangalan, phone, at address.';
        } elseif (!$items) {
            $error = 'Pumili ng kahit isang item.';
        } else {
            try {
                // I-match sa existing suki via phone; kung wala, gawa ng record
                $match = db()->select('customers', ['phone' => 'eq.' . $phone], 'id', '', 1);
                if ($match) {
                    $customer_id = $match[0]['id'];
                } else {
                    $created     = db()->insert('customers', [
                        'name' => $name, 'phone' => $phone, 'address' => $address,
                        'notes' => 'Gumawa ng record via online order',
                    ]);
                    $customer_id = $created[0]['id'];
                }

                $order = db()->insert('orders', [
                    'customer_id'    => $customer_id,
                    'order_type'     => 'delivery',
                    'status'         => 'pending',
                    'total'          => $total,
                    'amount_paid'    => 0,
                    'payment_status' => 'utang',   // COD — settled pag-deliver
                    'source'         => 'online',
                    'notes'          => $notes,
                ]);
                $orderId = $order[0]['id'];

                foreach ($items as &$it) $it['order_id'] = $orderId;
                unset($it);
                db()->insert('order_items', $items);

                $done = true;
            } catch (Throwable $t) {
                $error = 'May problema sa pag-send ng order. Subukan ulit o tumawag sa ' . BRAND_PHONE . '.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="fil">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order Online · <?= e(BRAND_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&family=Figtree:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>:root{--brand:<?= BRAND_COLOR ?>;--ink:<?= BRAND_INK ?>;}</style>
</head>
<body>
<div class="public-wrap">
  <div class="public-head">
    <div class="brand-drop" aria-hidden="true"></div>
    <h1><?= e(BRAND_NAME) ?></h1>
    <p class="sub"><?= e(BRAND_TAGLINE) ?><br>
      <span class="muted"><?= e(BRAND_ADDRESS) ?> · <?= e(BRAND_PHONE) ?></span>
    </p>
  </div>

  <?php if ($loadErr): ?>
    <div class="card"><p><?= e($loadErr) ?> Tumawag na lang sa <strong><?= e(BRAND_PHONE) ?></strong>.</p></div>

  <?php elseif ($done): ?>
    <div class="card" style="text-align:center;padding:34px 24px;">
      <h2 style="font-size:22px;">Salamat<?= $orderId ? ', order #' . (int)$orderId : '' ?>! 💧</h2>
      <p>Natanggap na namin ang order mo. Ihahatid ito sa lalong madaling panahon —
         bayad pagdating (COD).</p>
      <p class="muted">May tanong? Tawag/text sa <?= e(BRAND_PHONE) ?>.</p>
      <a class="btn mt" href="order-online.php" style="display:inline-block;">Mag-order ulit</a>
    </div>

  <?php else: ?>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>

    <form method="post" id="pubForm">
      <div class="card">
        <h2>Ano ang idedeliver namin?</h2>
        <div class="qtygrid">
          <?php foreach ($products as $p): ?>
            <div class="pname"><?= e($p['name']) ?></div>
            <div class="pprice"><?= peso($p['price']) ?></div>
            <input type="number" name="qty[<?= (int)$p['id'] ?>]" min="0" step="1" value="0"
                   class="qty" data-price="<?= (float)$p['price'] ?>" inputmode="numeric">
          <?php endforeach; ?>
        </div>
        <div class="total-bar">
          <span class="t-label">Total (bayad pagdating)</span>
          <span class="t-amt" id="totalOut"><?= CURRENCY ?>0.00</span>
        </div>
      </div>

      <div class="card">
        <h2>Saan namin ihahatid?</h2>
        <div class="field"><label for="name">Pangalan *</label><input id="name" name="name" required></div>
        <div class="field"><label for="phone">Phone *</label><input id="phone" name="phone" required inputmode="tel" placeholder="09xx xxx xxxx"></div>
        <div class="field"><label for="address">Kumpletong address *</label><input id="address" name="address" required placeholder="Bahay/Blk/Lot, street, landmark"></div>
        <div class="field"><label for="notes">Notes (optional)</label><textarea id="notes" name="notes" placeholder="hal. Palitan ang 2 empty containers"></textarea></div>
        <input type="text" name="website" value="" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">
        <button class="btn btn-block" type="submit">I-send ang order</button>
        <div class="hint" style="text-align:center;">Cash on delivery. Walang online payment na kailangan.</div>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
(function () {
  var qtys = document.querySelectorAll('.qty');
  var out  = document.getElementById('totalOut');
  if (!out) return;
  var cur = <?= json_encode(CURRENCY) ?>;
  function recalc() {
    var t = 0;
    qtys.forEach(function (q) { t += (parseInt(q.value, 10) || 0) * parseFloat(q.dataset.price); });
    out.textContent = cur + t.toFixed(2);
  }
  qtys.forEach(function (q) { q.addEventListener('input', recalc); });
})();
</script>
</body>
</html>
