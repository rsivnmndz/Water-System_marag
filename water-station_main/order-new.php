<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$products  = db()->select('products', ['is_active' => 'eq.true'], '*', 'sort_order.asc', 100);
$customers = db()->select('customers', [], 'id,name,phone', 'name.asc', 1000);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $qtys        = $_POST['qty'] ?? [];
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $walkin_name = trim($_POST['walkin_name'] ?? '');
    $order_type  = ($_POST['order_type'] ?? 'walkin') === 'delivery' ? 'delivery' : 'walkin';
    $tendered    = max(0, (float)($_POST['amount_paid'] ?? 0));
    $notes       = trim($_POST['notes'] ?? '');

    // Build line items from server-side prices (never trust posted prices)
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

    if (!$items) {
        flash('Walang laman ang order — maglagay ng quantity sa kahit isang item.', 'err');
        header('Location: order-new.php');
        exit;
    }

    $paid = min($tendered, $total);
    $due  = $total - $paid;

    if ($due > 0 && $customer_id === 0) {
        flash('May balanseng ' . peso($due) . ' — pumili ng customer record para ma-lista ang utang.', 'err');
        header('Location: order-new.php');
        exit;
    }

    $payment_status = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'utang');
    $is_walkin      = $order_type === 'walkin';

    $order = [
        'customer_id'    => $customer_id ?: null,
        'walkin_name'    => $walkin_name,
        'order_type'     => $order_type,
        'status'         => $is_walkin ? 'completed' : 'pending',
        'total'          => $total,
        'amount_paid'    => $paid,
        'payment_status' => $payment_status,
        'source'         => 'counter',
        'notes'          => $notes,
        'delivered_at'   => $is_walkin ? date('c') : null,
    ];

    try {
        $created  = db()->insert('orders', $order);
        $order_id = $created[0]['id'];

        foreach ($items as &$it) $it['order_id'] = $order_id;
        unset($it);
        db()->insert('order_items', $items);

        // Utang lands on the customer balance only once the order is completed.
        // Walk-ins complete agad; deliveries magba-balance sa "Delivered na" action.
        if ($is_walkin && $due > 0 && $customer_id > 0) {
            $cust = db()->select('customers', ['id' => 'eq.' . $customer_id], 'balance', '', 1);
            $bal  = (float)($cust[0]['balance'] ?? 0) + $due;
            db()->update('customers', ['id' => 'eq.' . $customer_id], ['balance' => $bal]);
        }

        flash('Order #' . $order_id . ' saved — ' . peso($total) . ' (' . $payment_status . ').');
        header('Location: orders.php');
        exit;
    } catch (Throwable $t) {
        flash('Hindi na-save ang order: ' . $t->getMessage(), 'err');
        header('Location: order-new.php');
        exit;
    }
}

$title = 'Bagong Order';
require __DIR__ . '/includes/header.php';
?>

<h1>Bagong Order</h1>
<p class="sub">Punan ang quantities, piliin kung walk-in o delivery, tapos i-save.</p>

<form method="post" id="posForm">
  <?= csrf_field() ?>

  <div class="grid grid-2">
    <div class="card">
      <h2>Mga Item</h2>
      <div class="qtygrid">
        <?php foreach ($products as $p): ?>
          <div class="pname"><?= e($p['name']) ?></div>
          <div class="pprice"><?= peso($p['price']) ?></div>
          <input type="number" name="qty[<?= (int)$p['id'] ?>]" min="0" step="1" value="0"
                 class="qty" data-price="<?= (float)$p['price'] ?>" inputmode="numeric">
        <?php endforeach; ?>
      </div>

      <div class="total-bar">
        <span class="t-label">Total</span>
        <span class="t-amt" id="totalOut"><?= CURRENCY ?>0.00</span>
      </div>
    </div>

    <div class="card">
      <h2>Customer at Bayad</h2>

      <div class="field">
        <label>Uri ng order</label>
        <div class="row">
          <label style="font-weight:500;"><input type="radio" name="order_type" value="walkin" checked style="width:auto;margin-right:6px;">Walk-in / pickup</label>
          <label style="font-weight:500;"><input type="radio" name="order_type" value="delivery" style="width:auto;margin-right:6px;">Delivery</label>
        </div>
      </div>

      <div class="field">
        <label for="customer_id">Customer (kailangan kung may utang o delivery)</label>
        <select id="customer_id" name="customer_id">
          <option value="0">— Walk-in / wala sa listahan —</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Wala pa sa listahan? <a href="customers.php">Idagdag muna sa Customers</a>.</div>
      </div>

      <div class="field">
        <label for="walkin_name">Pangalan (optional, para sa walk-in na walang record)</label>
        <input id="walkin_name" name="walkin_name" placeholder="hal. Aling Nena">
      </div>

      <div class="field">
        <label for="amount_paid">Halagang binayad ngayon</label>
        <input id="amount_paid" name="amount_paid" type="number" min="0" step="0.01" value="0" inputmode="decimal">
        <div class="hint" id="payHint">0 = buong utang · sobra sa total = may sukli</div>
      </div>

      <div class="field">
        <label for="notes">Notes (optional)</label>
        <textarea id="notes" name="notes" placeholder="hal. Iwan sa gate, 2nd floor, atbp."></textarea>
      </div>

      <button class="btn btn-block" type="submit">I-save ang order</button>
    </div>
  </div>
</form>

<script>
(function () {
  var qtys  = document.querySelectorAll('.qty');
  var paid  = document.getElementById('amount_paid');
  var out   = document.getElementById('totalOut');
  var hint  = document.getElementById('payHint');
  var cur   = <?= json_encode(CURRENCY) ?>;

  function fmt(n) { return cur + n.toFixed(2); }

  function recalc() {
    var total = 0;
    qtys.forEach(function (q) {
      total += (parseInt(q.value, 10) || 0) * parseFloat(q.dataset.price);
    });
    out.textContent = fmt(total);

    var p = parseFloat(paid.value) || 0;
    if (total <= 0)        hint.textContent = 'Maglagay muna ng items.';
    else if (p >= total)   hint.textContent = 'Sukli: ' + fmt(p - total);
    else if (p > 0)        hint.textContent = 'Partial — matitirang utang: ' + fmt(total - p);
    else                   hint.textContent = 'Buong utang: ' + fmt(total) + ' (kailangan ng customer record)';
  }

  qtys.forEach(function (q) { q.addEventListener('input', recalc); });
  paid.addEventListener('input', recalc);
  recalc();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
