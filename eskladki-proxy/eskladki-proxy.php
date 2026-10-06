<?php
/**
 * Plugin Name: eSkładki XLSX Proxy
 * Description: Pobiera XLSX w pamięci i zwraca wyłącznie dane dla podanego loginu.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/SimpleXLSX.php';

function eskladki_normalize_login($value) {
    if (class_exists('Normalizer')) {
        $value = Normalizer::normalize($value, Normalizer::FORM_C);
    }
    // NFC Polish initials also work without the optional intl/mbstring extensions.
    $value = strtr(trim((string) $value), [
        "a\u{0328}" => 'ą', "A\u{0328}" => 'Ą', "c\u{0301}" => 'ć', "C\u{0301}" => 'Ć',
        "e\u{0328}" => 'ę', "E\u{0328}" => 'Ę', "n\u{0301}" => 'ń', "N\u{0301}" => 'Ń',
        "o\u{0301}" => 'ó', "O\u{0301}" => 'Ó', "s\u{0301}" => 'ś', "S\u{0301}" => 'Ś',
        "z\u{0301}" => 'ź', "Z\u{0301}" => 'Ź', "z\u{0307}" => 'ż', "Z\u{0307}" => 'Ż',
        'ą' => 'Ą', 'ć' => 'Ć', 'ę' => 'Ę', 'ł' => 'Ł', 'ń' => 'Ń',
        'ó' => 'Ó', 'ś' => 'Ś', 'ź' => 'Ź', 'ż' => 'Ż',
    ]);
    // strtr replaces in one pass, so uppercase composed characters separately.
    return strtoupper(strtr($value, ['ą'=>'Ą','ć'=>'Ć','ę'=>'Ę','ł'=>'Ł','ń'=>'Ń','ó'=>'Ó','ś'=>'Ś','ź'=>'Ź','ż'=>'Ż']));
}

function eskladki_lower($value) {
    return strtolower(strtr(trim((string) $value), ['Ą'=>'ą','Ć'=>'ć','Ę'=>'ę','Ł'=>'ł','Ń'=>'ń','Ó'=>'ó','Ś'=>'ś','Ź'=>'ź','Ż'=>'ż']));
}

/** SheetJS-compatible raw values: absent cells are null, explicit empty strings stay empty. */
function eskladki_read_rows(\Shuchkin\SimpleXLSX $xlsx, $index) {
    $worksheet = $xlsx->worksheet($index);
    if ($worksheet === false) throw new RuntimeException('Invalid worksheet');
    list($columns, $row_count) = $xlsx->dimension($index);
    if ($columns > 512 || $row_count > 20000 || $columns * $row_count > 2000000) {
        throw new RuntimeException('Worksheet too large');
    }
    $rows = array_fill(0, $row_count, array_fill(0, $columns, null));
    foreach ($worksheet->sheetData->row as $row) {
        foreach ($row->c as $cell) {
            list($column, $line) = $xlsx->getIndex((string) $cell['r']);
            if ($column < 0 || $line < 0 || $column >= $columns || $line >= $row_count) {
                throw new RuntimeException('Invalid cell coordinates');
            }
            $type = (string) $cell['t'];
            if (!isset($cell->v) && $type !== 'inlineStr') continue;
            $value = $xlsx->value($cell);
            // sheet_to_json({header: 1}) omits Excel error cells by default.
            if ($type === 'e') $value = null;
            $rows[$line][$column] = $value;
        }
    }
    return $rows;
}

/** Preserve only config cells consumed by excel.ts, including their row/column positions. */
function eskladki_filter_config(array $rows, $team_name) {
    $result = array_fill(0, count($rows), []);
    // Legacy fallback fields B3:B8 (amount, account, recipient, transfer title).
    for ($i = 2; $i <= 7; $i++) {
        if (isset($rows[$i][1])) {
            $result[$i][1] = $rows[$i][1];
        }
    }
    $rates_started = false;
    foreach ($rows as $i => $row) {
        for ($j = 0; $j < min(count($row), 3); $j++) {
            $label = eskladki_lower($row[$j] ?? '');
            $is_history = strpos($label, 'historia stawek') !== false || strpos($label, 'obowiązuje od') !== false;
            if ($j < 2 && $is_history) {
                $rates_started = true;
            }
            $is_field = strpos($label, 'konta') !== false || strpos($label, 'rachunk') !== false
                || strpos($label, 'odbiorca') !== false || strpos($label, 'tytu') !== false
                || strpos($label, 'aktualizacj') !== false || $label === 'kwota'
                || strpos($label, 'kwota bieżąca') !== false || strpos($label, 'kwota biezaca') !== false;
            if ($is_field || $is_history) {
                $result[$i][$j] = $row[$j];
                if (isset($row[$j + 1])) {
                    $result[$i][$j + 1] = $row[$j + 1];
                } elseif (isset($rows[$i + 1][$j])) {
                    $result[$i + 1][$j] = $rows[$i + 1][$j];
                }
            }
        }
        if ($rates_started) {
            // The rate parser checks A/B for dates and B/C for their amounts.
            // Only copy candidate rate rows, never unrelated config text/logins.
            $date_candidate = static function ($value) {
                return is_numeric($value) || (is_string($value) && preg_match('/\d{4}/u', $value)
                    && !preg_match('/^[A-ZĄĆĘŁŃÓŚŹŻ]{2}[0-9]{5}$/u', eskladki_normalize_login($value)));
            };
            $numeric_amount = static function ($value) {
                return is_numeric($value) || (is_string($value) && preg_match('/^[\s]*[+-]?(?:\d+(?:[.,]\d*)?|[.,]\d+)/', $value));
            };
            if (($date_candidate($row[0] ?? null) && $numeric_amount($row[1] ?? null))
                || ($date_candidate($row[1] ?? null) && $numeric_amount($row[2] ?? null))) {
                for ($j = 0; $j < 3; $j++) {
                    if (isset($row[$j])) $result[$i][$j] = $row[$j];
                }
            }
        }
    }
    if ($team_name !== null) {
        foreach ($rows as $i => $row) {
            if (eskladki_lower($row[3] ?? '') !== eskladki_lower($team_name)) continue;
            $result[$i][3] = $row[3];
            for ($j = $i + 1; $j < min($i + 5, count($rows)); $j++) {
                $label = eskladki_lower($rows[$j][3] ?? '');
                if (strpos($label, 'płacących') !== false || strpos($label, 'placacych') !== false || strpos($label, 'saldo') !== false) {
                    $result[$j][3] = $rows[$j][3];
                    $result[$j][4] = $rows[$j][4] ?? null;
                } elseif ($label !== '') {
                    break;
                }
            }
            break;
        }
    }
    // Sparse PHP arrays must become JSON arrays, with null placeholders.
    foreach ($result as &$row) {
        if (!$row) continue;
        $dense = array_fill(0, max(array_keys($row)) + 1, null);
        foreach ($row as $col => $value) $dense[$col] = $value;
        $row = $dense;
    }
    unset($row);
    return $result;
}

function eskladki_select_rows(array $sheets, $login) {
    $config = [];
    $selected = [];
    $leader_sheet = null;
    foreach ($sheets as $sheet) {
        if ($sheet['name'] === 'KONFIGURACJA') {
            $config = $sheet['rows'];
        } elseif ($leader_sheet === null && count($sheet['rows']) >= 3
            && eskladki_normalize_login($sheet['rows'][0][1] ?? '') === $login) {
            $leader_sheet = $sheet;
        }
    }
    if ($leader_sheet !== null) {
        $selected[] = $leader_sheet;
    } else {
        foreach ($sheets as $sheet) {
            if ($sheet['name'] === 'KONFIGURACJA' || count($sheet['rows']) < 3) continue;
            foreach (array_slice($sheet['rows'], 2) as $row) {
                if (eskladki_normalize_login($row[0] ?? '') !== $login) continue;
                $headers = array_slice($sheet['rows'], 0, 2);
                // B1 contains another person's leader login, not a month label.
                $headers[0][1] = null;
                $selected[] = ['name' => $sheet['name'], 'rows' => [$headers[0], $headers[1], $row]];
                break; // Match the original first-row lookup within each sheet.
            }
        }
    }
    if (!$selected) return ['version' => 1, 'mode' => 'not_found', 'sheets' => []];
    array_unshift($selected, ['name' => 'KONFIGURACJA', 'rows' => eskladki_filter_config($config, $leader_sheet['name'] ?? null)]);
    return ['version' => 1, 'mode' => $leader_sheet === null ? 'member' : 'team', 'sheets' => $selected];
}

function eskladki_lookup(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $raw_login = is_array($params) ? ($params['login'] ?? null) : null;
    if (!is_string($raw_login) || strlen($raw_login) > 64) {
        return new WP_Error('eskladki_invalid_login', 'Nieprawidłowy login.', ['status' => 400]);
    }
    $login = eskladki_normalize_login($raw_login);
    if (!preg_match('/^[A-ZĄĆĘŁŃÓŚŹŻ]{2}[0-9]{5}$/u', $login)) {
        return new WP_Error('eskladki_invalid_login', 'Nieprawidłowy login.', ['status' => 400]);
    }
    try {
        // Fixed server-side URL: the browser cannot choose a download target.
        $url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQrFAzpuwJqK6EsC4puGrSyENLwEkhQQI1GtEdvKoa0fjBzCNkzG6Vh9APqkytRGws_FeI9OeN7YIg8/pub?output=xlsx';
        $response = wp_remote_get($url . '&_t=' . time(), [
            'timeout' => 20, 'redirection' => 5, 'sslverify' => true,
            'stream' => false, 'limit_response_size' => 8 * 1024 * 1024,
            'headers' => ['Cache-Control' => 'no-cache'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new RuntimeException('XLSX download failed');
        }
        $body = wp_remote_retrieve_body($response);
        if (strlen($body) >= 8 * 1024 * 1024 || substr($body, 0, 2) !== 'PK') {
            throw new RuntimeException('Invalid XLSX response');
        }
        $xlsx = \Shuchkin\SimpleXLSX::parseData($body);
        if (!$xlsx) throw new RuntimeException('XLSX parsing failed');
        // Keep numeric dates and amounts as in SheetJS's default raw output.
        $xlsx->setDateTimeFormat(false);
        $sheets = [];
        foreach ($xlsx->sheetNames() as $index => $name) {
            $sheets[] = ['name' => $name, 'rows' => eskladki_read_rows($xlsx, $index)];
        }
        if (!in_array('KONFIGURACJA', $xlsx->sheetNames(), true)) {
            throw new RuntimeException('Missing configuration');
        }
        return new WP_REST_Response(eskladki_select_rows($sheets, $login), 200);
    } catch (Throwable $error) {
        // Do not expose upstream addresses, rows or parser diagnostics.
        return new WP_Error('eskladki_unavailable', 'Nie udało się pobrać danych. Spróbuj ponownie później.', ['status' => 502]);
    }
}

add_action('rest_api_init', static function () {
    register_rest_route('eskladki/v1', '/lookup', [
        'methods' => 'POST',
        'callback' => 'eskladki_lookup',
        // This app intentionally uses a lookup code rather than WP accounts.
        'permission_callback' => '__return_true',
    ]);
});

// Apply to successes AND validation/upstream errors; CDNs must not cache lookups.
add_filter('rest_post_dispatch', static function ($response, $server, $request) {
    if ($request->get_route() === '/eskladki/v1/lookup') {
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('X-Content-Type-Options', 'nosniff');
    }
    return $response;
}, 10, 3);
