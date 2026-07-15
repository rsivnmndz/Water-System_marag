<?php
/**
 * ================================================================
 *  WHITE-LABEL CONFIG
 *  This is the ONLY file you touch when deploying for a new
 *  water station client. Brand + keys, tapos deploy na.
 * ================================================================
 */

// ---- Brand ------------------------------------------------------
define('BRAND_NAME',    'AquaSuki Water Station');
define('BRAND_TAGLINE', 'Malinis na tubig, suki-approved.');
define('BRAND_COLOR',   '#0FA3B1');   // primary accent (buttons, links)
define('BRAND_INK',     '#0E2A3A');   // dark tone (sidebar, headings)
define('BRAND_PHONE',   '0917 000 0000');
define('BRAND_ADDRESS', 'Purok 1, Barangay Sampaguita');
define('CURRENCY',      '₱');

// ---- Supabase  (Dashboard → Project Settings → API) --------------
define('SUPABASE_URL',         'https://YOUR-PROJECT-REF.supabase.co');
define('SUPABASE_SERVICE_KEY', 'YOUR-service_role-KEY'); // server-side ONLY — never in JS

// ---- Locale ------------------------------------------------------
define('APP_TZ', 'Asia/Manila');
date_default_timezone_set(APP_TZ);

if (session_status() === PHP_SESSION_NONE) {
    session_name('ws_sess');
    session_start();
}

// ---- Small helpers used everywhere -------------------------------
function peso($n): string
{
    return CURRENCY . number_format((float)$n, 2);
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// PHP < 8.1 polyfill so the app runs on older shared hosting
if (!function_exists('array_is_list')) {
    function array_is_list(array $a): bool
    {
        return $a === [] || array_keys($a) === range(0, count($a) - 1);
    }
}
