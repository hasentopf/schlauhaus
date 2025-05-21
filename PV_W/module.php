<?php


declare(strict_types=1);

require_once __DIR__ . '/../libs/QuickChartHelper.php';

class PVWattSVG extends IPSModule
{
    
        public function Create() {
            //Never delete this line!
            parent::Create();

//            $this->RegisterVariableString('PV_W', 'PV Power');
//            $this->EnableAction('PV_W');
            $this->RegisterPropertyInteger('MaxPVPower', 600);

            $this->SetVisualizationType(1);
        }

//    public function ApplyChanges() {
//        //Never delete this line!
//        parent::ApplyChanges();
//
//        //Lets register a variable with action
//        $this->RegisterVariableString('PV_W', 'PV Power');
//        $this->EnableAction("PV_W");
//
//        $this->RegisterPropertyInteger('MaxPVPower', 600);
//
//    }

    public function PrintSvg() {

        $PV_value_id = $this->GetIDForIdent('TotalDCPVPower');
        $current_pv = round(GetValueFloat($PV_value_id) * 1000);
        $max_pv = $this->ReadPropertyInteger('MaxPVPower');
        $current_perc = ($current_pv / $max_pv) * 100;

        // SVG Chart
        return $this->DrawChart($current_perc, $current_pv);
    }

    public function RequestAction($Ident, $Value) {

    }

    public function GetVisualizationTile() {
        return  '<script>function handleMessage(data) { document.getElementById("display").innerText = data; }</script>'.
            '<div id="display">' . $this->PrintSvg() . '</div>';
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