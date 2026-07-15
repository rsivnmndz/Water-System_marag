<?php
require_once __DIR__ . '/../config.php';

/**
 * Minimal Supabase (PostgREST) client over cURL.
 * Chosen over PDO pgsql on purpose: most cheap PH shared hosting
 * (Hostinger, z.com, etc.) ships cURL but NOT the Postgres driver.
 * This runs anywhere PHP 7.4+ runs.
 */
class Supabase
{
    private string $url;
    private string $key;

    public function __construct()
    {
        $this->url = rtrim(SUPABASE_URL, '/') . '/rest/v1/';
        $this->key = SUPABASE_SERVICE_KEY;
    }

    private function request(string $method, string $path, array $query = [], $body = null): array
    {
        $qs = $query ? '?' . http_build_query($query) : '';
        $ch = curl_init($this->url . $path . $qs);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $this->key,
                'Authorization: Bearer ' . $this->key,
                'Content-Type: application/json',
                'Prefer: return=representation',
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Supabase connection failed: ' . $err);
        }
        $data = json_decode($raw, true);
        if ($status >= 400) {
            $msg = is_array($data) && isset($data['message']) ? $data['message'] : $raw;
            throw new RuntimeException('Supabase error (' . $status . '): ' . $msg);
        }
        return is_array($data) ? $data : [];
    }

    /**
     * SELECT — filters use PostgREST syntax:
     *   ['status' => 'eq.pending', 'balance' => 'gt.0']
     * $columns supports embeds: '*,customers(name),order_items(qty)'
     */
    public function select(string $table, array $filters = [], string $columns = '*', string $order = '', int $limit = 0): array
    {
        $q = array_merge(['select' => $columns], $filters);
        if ($order !== '') $q['order'] = $order;
        if ($limit > 0)    $q['limit'] = $limit;
        return $this->request('GET', $table, $q);
    }

    /** INSERT one assoc row or a list of rows. Returns inserted rows. */
    public function insert(string $table, array $rows): array
    {
        $payload = array_is_list($rows) ? $rows : [$rows];
        return $this->request('POST', $table, [], $payload);
    }

    /** UPDATE rows matching PostgREST filters. Returns updated rows. */
    public function update(string $table, array $filters, array $data): array
    {
        return $this->request('PATCH', $table, $filters, $data);
    }

    /** DELETE rows matching PostgREST filters. */
    public function delete(string $table, array $filters): array
    {
        return $this->request('DELETE', $table, $filters);
    }
}

/** Shared singleton. Usage: db()->select('customers'); */
function db(): Supabase
{
    static $instance = null;
    if ($instance === null) $instance = new Supabase();
    return $instance;
}
