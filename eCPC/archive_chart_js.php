<?php

# Include PHP Client quickchart.io
# https://quickchart.io/documentation/
require_once(IPS_GetKernelDir()."scripts".DIRECTORY_SEPARATOR.'System.QuickChart.ips.php');

//$instances = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
//$IDArchiveHandler = $instances[0];

$liste = array();
$archive_start = GetValueInteger(58725); //strtotime("01.01.2025");
$archive_end = GetValueInteger(50566); //time();
//print_r(date('d M Y H:i:s', $archive_start)); die;
$count = 0;

// array AC_GetAggregatedValues (int $InstanzID, int $VariablenID, int $Aggregationsstufe, int $Startzeit, int $Endzeit, int $Limit)
// https://www.symcon.de/de/service/dokumentation/modulreferenz/archive-control/ac-getaggregatedvalues/
/* AGGREGATIONSSTUFE
0	Stündliche Aggregation (00:00 - 59:59)
1	Tägliche Aggregation (00:00:00 - 23:59:59)
2	Wöchentliche Aggregation (Montag 00:00:00 - Sonntag 23:59:59)
3	Monatliche Aggregation (Erster Monatstag 00:00:00 - Letzter Monatstag 23:59:59)
4	Jährliche Aggregation (01.01. 00:00:00 - 31.12. 23:59:59)
5	5-Minütige Aggregation (Aus Rohdaten berechnet)
6	1-Minütige Aggregation (Aus Rohdaten berechnet)
*/
$Aggregationsstufe = GetValueInteger(42411);
$Profile = IPS_GetVariableProfile('Aggregationsstufe');
//print_r($Aggregationsstufe);
$profile_name = '';
foreach($Profile['Associations'] as $association) {
    if($association['Value'] == $Aggregationsstufe) {
        $profile_name = $association['Name'];
        //print_r($association); die;
    }
}

$archive_variable = 42641; // 34428;
$archive_name = IPS_GetName($archive_variable);

$ArchivInstanzID = FP_GetArchivID(); // 11917
$temp = AC_GetAggregatedValues($ArchivInstanzID, $archive_variable, $Aggregationsstufe, $archive_start, $archive_end, 0); // 1 = day, 2 = Week, 3 = month,4 =year, 0 = Hour
$label_format_helper = [2 => 'W', 3 => 'M', 4 => 'Y'];
$label_format = array_key_exists($Aggregationsstufe, $label_format_helper) ? $label_format_helper[$Aggregationsstufe] : "d.m.Y";

echo '<h3>Aggregationsstufe: ' . $profile_name . "</h3><br>\n";
$labels = $dates = [];
for ($count=0; $count < count($temp); $count++) {
    $verbrauch = round($temp[$count]['Avg'], 2); // Durchschnittswert
    $zahl_neu = str_replace(".",",",$verbrauch); // Punkt durch Komma ersetzen
    $values[] = $verbrauch;
    $dat = date($label_format, $temp[$count]['TimeStamp']);
    $labels[] = $dat;
    $liste[$count] = $dat.": " . $zahl_neu;
    echo $liste[$count]."<br>\n";
}

// SVG Chart
$svg = DrawChart($labels, $values, $profile_name, $archive_name);

SetValue(26373, $svg);

function DrawChart($labels, $values, $label, $headline) {
    // new chart object
    $chart = new QuickChart(['width' => 500, 'height' => 500, 'format' => 'svg']);
    // chart config
    $chart->setConfig("{
        type: 'bar',
        data: {
            labels: ['".implode("','", $labels)."'],   // Set X-axis labels
            datasets: [{
                label: '$label',
                data: [".implode(',', $values)."]
            }]
        },
        options: {        
            title: {
                display: true,
                text: '$headline',
                position: 'bottom',
            },
            plugins: {
                datalabels: {
                    anchor: 'center',
                    align: 'center',
                    color: '#FFF',
                    formatter: function (value, context) {
                        return value + ' kWh';
                    },
                    font: {
                        size: 8,
                        weight: 'normal',
                    },
                },
            }
        }
    }");
    //print_r($chart->getConfig());
    return $chart->toBinary();
}

//******************************************************************************
//	Ermittelt die Archiv ID
//******************************************************************************
function FP_GetArchivID() {
    $guid = "{43192F0B-135B-4CE7-A0A7-1475603F3060}";
    $array = IPS_GetInstanceListByModuleID($guid);
    $archive_id =  @$array[0];
    if ( !isset($archive_id) ) {
        IPSLogger_Dbg(basename(__FILE__), "Archive Control nicht gefunden!");
        return false;
    }
    return $archive_id;
}