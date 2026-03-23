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

$consumption_values = [
    ['id' => 21776, 'type' => 'verbrauch', 'name' => 'Netz', 'yesterday' => 35385, 'today' => 49614],       // Hausverbrauch (Netz)
    ['id' => 52543, 'type' => 'verbrauch', 'name' => 'Batterie', 'yesterday' => 11143, 'today' => 43577],   // Hausverbrauch (Batterie)
    ['id' => 40619, 'type' => 'verbrauch', 'name' => 'PV', 'yesterday' => 44148, 'today' => 58837],         // Hausverbrauch (PV)
    ['id' => 40734, 'type' => 'ertrag', 'name' => 'PV Ertrag', 'yesterday' => 36854, 'today' => 53459],     // PV Ertrag
];

$isEarly = isShortlyAfterMidnight(new DateTimeImmutable('now', new DateTimeZone('UTC')), 600); // 10 min window

if ($isEarly) {
    foreach ($consumption_values as $v) {
        $data = AC_GetAggregatedValues($archiveID, $v['id'], 1, strtotime('yesterday'), strtotime('today') - 1, 0);
        SetValueFloat($v['yesterday'], CalcConsumption($data));
    }
}

foreach ($consumption_values as $v) {
    $data = AC_GetAggregatedValues($archiveID, $v['id'], 1, strtotime('today'), time(), 0);
    SetValueFloat($v['today'], CalcConsumption($data));
}

$totalYesterday = $totalYesterdayYield = 0;
$totalToday = $totalTodayYield = 0;
foreach ($consumption_values as $v) {
    if($v['type'] == 'verbrauch') {
        $totalYesterday += GetValueFloat($v['yesterday']);
        $totalToday += GetValueFloat($v['today']);
    } else {
        $totalYesterdayYield += GetValueFloat($v['yesterday']);
        $totalTodayYield += GetValueFloat($v['today']);
    }
}

$html = '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%; text-align: left;">';
$html2 = '<br><table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%; text-align: left;">';
$html .= '<tr><th>Hausverbrauch</th><th>Gestern</th><th>Heute</th></tr>';
$html2 .= '<tr><th>PV-Ertrag</th><th>Gestern</th><th>Heute</th></tr>';
foreach ($consumption_values as $v) {
    if($v['type'] == 'verbrauch') {
        $html .= '<tr>';
        $html .= '<td>' . $v['name'] . '</td>';
        $html .= '<td>' . GetValueFloat($v['yesterday']) . ' kWh</td>';
        $html .= '<td>' . GetValueFloat($v['today']) . ' kWh</td>';
        $html .= '</tr>';
    } else {
        $html2 .= '<tr>';
        $html2 .= '<td>' . $v['name'] . '</td>';
        $html2 .= '<td>' . GetValueFloat($v['yesterday']) . ' kWh</td>';
        $html2 .= '<td>' . GetValueFloat($v['today']) . ' kWh</td>';
        $html2 .= '</tr>';
    }
}

// Total amount
$html .= '<tr style="font-weight: bold;"><td>Gesamt</td><td>' . round($totalYesterday, $roundTo2) . ' kWh</td><td>' . round($totalToday, $roundTo2) . ' kWh</td></tr></table>';
$html2 .= '<tr style="font-weight: bold;"><td>Gesamt</td><td>' . round($totalYesterdayYield, $roundTo2) . ' kWh</td><td>' . round($totalTodayYield, $roundTo2) . ' kWh</td></tr></table>';

SetValueString(46524, $html . $html2);
