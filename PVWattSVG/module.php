<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/QuickChartHelper.php';

class PVWattSVG extends IPSModule
{
    public function Create() {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyFloat('TotalDCPVPower',0);
        $this->RegisterPropertyInteger('MaxPVPower', 600);
        $this->RegisterAttributeString('CurrentPVPowerSVG', 'Keine Daten');

        // RegisterTimer to update the SVG periodically
        $this->RegisterTimer('UpdateSvg', 0, 'PVW_UpdateSvgTimer($_IPS[\'TARGET\']);');

        $this->SetVisualizationType(1);

//        $this->RegisterPropertyBoolean('AutoDebug', false);
    }

    public function PrintSvg() {
        if($this->ReadPropertyFloat('TotalDCPVPower') > 0) {
            // $PV_value_id = $this->GetIDForIdent('TotalDCPVPower');
            $current_pv = round(GetValueFloat($this->ReadPropertyFloat('TotalDCPVPower')) * 1000);
            $max_pv = $this->ReadPropertyInteger('MaxPVPower');
            $current_perc = ($current_pv / $max_pv) * 100;

            $chart = $this->DrawChart($current_perc, $current_pv);

//            $this->SendDebug('Debug', 'Debug $max_pv: '. $max_pv, 0);
//            $this->SendDebug('Debug', 'Debug $current_pv: '. $current_pv, 0);

            $this->SetTimerInterval('UpdateSvg', 45000); // 45000 ms is equal to 45 seconds.

            $this->WriteAttributeString('CurrentPVPowerSVG', $chart);

            return $chart;
        } else {
            return 'Keine Daten für Total DC PV Power gesetzt.';
        }
    }

    public function UpdateSvgTimer() {
        if($this->ReadPropertyFloat('TotalDCPVPower') > 0) {
            $this->SetTimerInterval('UpdateSvg', 20000);
            $this->UpdateVisualizationValue($this->PrintSvg());
        } else {
            $this->SetTimerInterval('UpdateSvg', 0);
        }

    }
    
    public function GetVisualizationTile() {
        $initialHandling = '<script>handleMessage(' . json_encode($this->PrintSvg()) . ')</script>';
//        $this->SendDebug('Debug', 'GetVisualizationTile this Debug', 0);
        $module = file_get_contents(__DIR__ . '/module.html');
        return $module . $initialHandling;
    }

    private function DrawChart($value, $current) {
        // new chart object
        $chart = new QuickChart(['width' => 250, 'height' => 220, 'format' => 'svg']);
        // chart config
        $chart->setConfig("{
        type: 'gauge',
        data: {
            datasets: [
                {
                    data: [25, 50, 75, 100],
                    value: $value,
                    minValue: 0,
                    backgroundColor: ['red', 'orange', '#f8f32b', 'green'],
                    borderWidth: 1,
                },
            ],
        },
        options: {
            legend: {
                display: false,
            },
            title: {
                display: false,
                text: 'Aktuelle Leistung',
                position: 'bottom',
            },
            needle: {
                radiusPercentage: 0,
                widthPercentage: 2,
                lengthPercentage: 40,
                color: '#6a5d4d',
            },
            valueLabel: {
                fontSize: 10,
                backgroundColor: 'transparent',
                color: '#6a5d4d',
                formatter: function (value, context) {
                    return  '$current W';
                },
                bottomMarginPercentage: 10,
            },
            plugins: {
                datalabels: {
                    display: 'auto',
                    formatter: function (value, context) {
                        return context.chart.data.labels[context.dataIndex/100];
                    },
                    color: '#6a5d4d',
                    font: {
                        weight: 'bold',
                        size: 8,
                    }
                },
            },
        },
    }");
        return $chart->toBinary();
    }
}