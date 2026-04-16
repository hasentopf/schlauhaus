<?php

/**
 * IPS_GetInstanceListByModuleID
 */

$roundTo2 = 2; //Anzahl Nachkommastellen bei Ergebnissen

// Funktion zum addieren der Zählerwerte
function CalcConsumption($values)
{
    global $roundTo2;
    $consumption = 0;
    foreach ($values as $value) {
        $consumption += $value['Avg'];
    }
    return round($consumption, $roundTo2);
}

function isShortlyAfterMidnight(DateTimeImmutable $dt, int $seconds = 300): bool
{
    $midnight = $dt->setTime(0, 0, 0);
    $diff = $dt->getTimestamp() - $midnight->getTimestamp();
    return $diff >= 0 && $diff < $seconds;
}

$instances = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
$archiveID = $instances[0];

// TODO Total DC charge energy (DC-side to battery)
$consumption_values = [
    ['id' => 21776, 'type' => 'verbrauch', 'name' => 'Netz', 'alt' => 'Total home consumption Grid', 'yesterday' => 35385, 'today' => 49614],       // Hausverbrauch (Netz)
    ['id' => 52543, 'type' => 'verbrauch', 'name' => 'Batterie', 'alt' => 'Total home consumption Battery', 'yesterday' => 11143, 'today' => 43577],   // Hausverbrauch (Batterie)
    ['id' => 40619, 'type' => 'verbrauch', 'name' => 'PV', 'alt' => 'Total home consumption PV', 'yesterday' => 44148, 'today' => 58837],         // Hausverbrauch (PV)
    ['id' => 45595, 'type' => 'gesamt', 'name' => 'Gesamt', 'alt' => 'Total home consumption'],         // Hausverbrauch (Gesamt)
    ['id' => 30835, 'type' => 'gesamt', 'name' => 'Tagesertrag', 'alt' => 'Daily yield'],  // Tagesertrag (Daily yield)
    ['id' => 40734, 'type' => 'ertrag', 'name' => 'Gesamtertrag', 'alt' => 'Total DC PV energy (sum of all PV inputs)', 'yesterday' => 36854, 'today' => 53459],  // PV Ertrag (Total DC PV energy (sum of all PV inputs))
    ['id' => 28007, 'type' => 'ertrag', 'name' => 'Einspeisung', 'alt' => 'Total energy AC-side to grid', 'yesterday' => 13395, 'today' => 23642]    // Einspeisung (Total energy AC-side to grid)
];

$isEarly = isShortlyAfterMidnight(new DateTimeImmutable('now', new DateTimeZone('UTC')), 600); // 10 min window

if ($isEarly) {
    foreach ($consumption_values as $v) {
        if($v['type'] != 'gesamt') {
            $data = AC_GetAggregatedValues($archiveID, $v['id'], 1, strtotime('yesterday'), strtotime('today') - 1, 0);
            SetValueFloat($v['yesterday'], CalcConsumption($data));
        }
    }
}

foreach ($consumption_values as $v) {
    if($v['type'] != 'gesamt') {
        $data = AC_GetAggregatedValues($archiveID, $v['id'], 1, strtotime('today'), time(), 0);
        SetValueFloat($v['today'], CalcConsumption($data));
    }
}

$html = '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%; text-align: left;">';
$html2 = '<br><table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%; text-align: left;">';
$html .= '<tr><th>Hausverbrauch aus</th><th>Gestern</th><th>Heute</th></tr>';
$html2 .= '<tr><th>PV-Ertrag</th><th>Gestern</th><th>Heute</th></tr>';
foreach ($consumption_values as $v) {
    if($v['type'] == 'verbrauch') {
        $html .= '<tr title="' . $v['alt'] . '">';
        $html .= '<td>' . $v['name'] . '</td>';
        $html .= '<td>' . GetValueFloat($v['yesterday']) . ' kWh</td>';
        $html .= '<td>' . GetValueFloat($v['today']) . ' kWh</td>';
        $html .= '</tr>';
    } else if($v['type'] == 'ertrag') {
        $html2 .= '<tr title="' . $v['alt'] . '">';
        $html2 .= '<td>' . $v['name'] . '</td>';
        $html2 .= '<td>' . GetValueFloat($v['yesterday']) . ' kWh</td>';
        $html2 .= '<td>' . GetValueFloat($v['today']) . ' kWh</td>';
        $html2 .= '</tr>';
    } else if($v['type'] == 'gesamt') {
        $totalTodayData = AC_GetAggregatedValues($archiveID, $v['id'], 1, strtotime('today'), time(), 0);
        $totalToday[$v['name']] = CalcConsumption($totalTodayData);
        $totalYesterdayData = AC_GetAggregatedValues($archiveID, $v['id'], 1, strtotime('yesterday'), strtotime('today') - 1, 0);
        $html .= '<tr style="font-weight: bold;"><td>' . $v['name'] . '</td><td>' . CalcConsumption($totalYesterdayData) . ' kWh</td><td>' . CalcConsumption($totalTodayData) . ' kWh</td></tr>';
    }
}

$html .= '</table>';
$html2 .= '</table>';

SetValueString(46524, $html . $html2);
