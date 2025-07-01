<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/schlauhaus.php';
require_once __DIR__ . '/../libs/QuickChartHelper.php';

class PVWattSVG extends IPSModule
{
    use Schlauhaus;

    private const HTML_Color_White = 0xFFFFFF;
    private const HTML_Color_Black = 0x606c76;

    public function Create() {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyFloat('TotalDCPVPower',0);
        $this->RegisterPropertyInteger('MaxPVPower', 600);
//        $this->RegisterAttributeString('CurrentPVPowerSVG', 'Keine Daten');
        $this->RegisterPropertyInteger('IntervalTime', 45);

        // RegisterTimer to update the SVG periodically
        $this->RegisterTimer('UpdateSvg', 0, 'PVW_UpdateSvgTimer($_IPS[\'TARGET\']);');

        $this->RegisterAttributeInteger('SvgFontColor', self::HTML_Color_White);

        $this->SetVisualizationType(1);

//        $this->RegisterPropertyBoolean('AutoDebug', false);
    }

    public function ApplyChanges(){
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function PrintSvg() {
        if($this->ReadPropertyFloat('TotalDCPVPower') > 0) {
            // $PV_value_id = $this->GetIDForIdent('TotalDCPVPower');
            $current_pv = round(GetValueFloat($this->ReadPropertyFloat('TotalDCPVPower')) * 1000);
            $max_pv = $this->ReadPropertyInteger('MaxPVPower');
            $current_perc = ($current_pv / $max_pv) * 100;

            $font_color = $this->GetHexColor($this->ReadAttributeInteger('SvgFontColor'));

            $chart = $this->DrawChart($current_perc, $current_pv, $font_color);

//            $this->SendDebug('Debug', 'Debug $max_pv: '. $max_pv, 0);
//            $this->SendDebug('Debug', 'Debug $current_pv: '. $current_pv, 0);

            $this->SetTimerInterval('UpdateSvg', $this->GetIntervalTime());

//            $this->WriteAttributeString('CurrentPVPowerSVG', $chart);

            return $chart . '<div id="IntervalTime" data-time="'.$this->GetIntervalTime(false).'"></div>';
        } else {
            return 'Keine Daten für Total DC PV Power gesetzt.';
        }
    }

    public function UpdateSvgTimer() {
        if($this->ReadPropertyFloat('TotalDCPVPower') > 0) {
            $this->SetTimerInterval('UpdateSvg', $this->GetIntervalTime());
            $this->UpdateVisualizationValue($this->PrintSvg());
        } else {
            $this->SetTimerInterval('UpdateSvg', 0);
        }
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'DarkMode':
                $fontColor = ($Value) ? self::HTML_Color_White : self::HTML_Color_Black;
//                $this->SendDebug('Debug', 'DarkMode $fontColor: '. $fontColor, 0);
                $this->WriteAttributeInteger('SvgFontColor', $fontColor);
                break;
        }
    }
    public function GetVisualizationTile() {
        $initialHandling = '<script>handleMessage(' . json_encode($this->PrintSvg()) . ')</script>';
//        $this->SendDebug('Debug', 'GetVisualizationTile this Debug', 0);
        $module = file_get_contents(__DIR__ . '/module.html');
        return $module . $initialHandling;
    }

    /**
     * Returns the interval time in s or ms
     * e.g. 45000 ms is equal to 45 seconds.
     * @return float|int
     */
    private function GetIntervalTime($inMs = true) {
        return ($inMs) ? $this->ReadPropertyInteger('IntervalTime') * 1000 : $this->ReadPropertyInteger('IntervalTime');
    }

    private function DrawChart($value, $current, $font_color) {
        // new chart object
        $chart = new QuickChart(['width' => 250, 'height' => 220, 'format' => 'svg']);
        // chart config
        $chart->setConfig("{
        type: 'gauge',
        data: {
            labels: ['0-20%', '20-40%', '40-60%', '60-80%', '80-100%'],
            datasets: [
                {
                    value: $value,
                    data: [20, 40, 60, 80, 100],
                    minValue: 0,
                    backgroundColor: ['red', 'orange', 'yellow', 'rgb(110,182,67)', 'rgb(14,147,137)'],
                    borderWidth: 0.5,
                    borderColor: '$font_color',
                },
            ],
        },
        options: {
            title: {
                display: false,
                text: 'Aktuelle Leistung',
                position: 'bottom',
            },
            needle: {
                radiusPercentage: 1,
                widthPercentage: 1,
                lengthPercentage: 60,
                color: '$font_color', // #6a5d4d
            },
            valueLabel: {
                fontSize: 10,
                backgroundColor: 'transparent',
                color: '$font_color',
                formatter: function (value, context) {
                    return  '$current W';
                },
                bottomMarginPercentage: 15,
            },
            plugins: {
                datalabels: {
                    display: 'auto',
                    formatter: function (value, context) {
                      return context.chart.data.labels[context.dataIndex];
                    },
                    color: '#6a5d4d',
                    font: {
                        size: 8,
                    },
                },
            },
        },
    }");
        return $chart->toBinary();
    }
}
