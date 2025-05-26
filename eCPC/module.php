<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/schlauhaus.php';
require_once __DIR__ . '/../libs/QuickChartHelper.php';

class eCPC extends IPSModule
{
    use Schlauhaus;

    public array $eCPCs = ['TotalHomeConsumptionID', 'TotalHomeConsumptionGridID', 'TotalDCPVEnergyID', 'TotalHomeConsumptionPVID', 'TotalEnergyACSideToGridID', 'TotalHomeConsumptionBatteryID', 'TotalDCchargeEnergyID', 'TotalACchargeEnergyID', 'TotalACdischargeEnergyID'];

    public array $labelFormat = [2 => 'W', 3 => 'M', 4 => 'Y'];

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     */
    public function Create() {
        parent::Create();

        if (!IPS_VariableProfileExists('eCPC.AggregationLevel')) {
            IPS_CreateVariableProfile('eCPC.AggregationLevel', 1);
            IPS_SetVariableProfileValues('eCPC.AggregationLevel', 2, 4, 1);
            IPS_SetVariableProfileIcon('eCPC.AggregationLevel', 'box-archive');

            IPS_SetVariableProfileAssociation('eCPC.AggregationLevel', 2, 'weekly', '', -1);
            IPS_SetVariableProfileAssociation('eCPC.AggregationLevel', 3, 'monthly', '', -1);
            IPS_SetVariableProfileAssociation('eCPC.AggregationLevel', 4, 'yearly', '', -1);
        }

        $this->RegisterVariableInteger('AggregationLevel', 'Level', 'eCPC.AggregationLevel');

        $this->RegisterPropertyBoolean('InstanceActive', false);

        foreach ($this->eCPCs as $eCPC) {
            $this->RegisterPropertyInteger($eCPC, 0);
        }

        $this->EnableAction('AggregationLevel');

        $this->RegisterAttributeInteger('ArchiveStart', strtotime('-1 months'));
        $this->RegisterAttributeInteger('ArchiveEnd', time());

        $this->RegisterAttributeInteger('ArchiveVarSelect', 0);

        $this->RegisterPropertyInteger('FontColor', 0xff0000);
        $this->RegisterPropertyInteger('BarColor', 0xff0000);

        $this->SetVisualizationType(1);

//        $this->RegisterPropertyBoolean('AutoDebug', false);
    }


    /**
     * Overrides the internal IPSApplyChanges($id) function
     * @return void
     */
    public function ApplyChanges() {
        parent::ApplyChanges();

        $noValuesSet = true;
        foreach ($this->eCPCs as $eCPC) {
            if ($this->ReadPropertyInteger($eCPC) > 0) {
                $noValuesSet = false;
            }
        }
        if (!$noValuesSet) {
            $this->SetStatus(201); // No Archive values set
            return false;
        }

        $this->Reload();
    }

    /**
     * @return string
     */
    public function GetVisualizationTile() {
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';

        // Add static HTML content from a file to make editing easier
        $module = file_get_contents(__DIR__ . '/module.html');

        // Return everything to render our fancy tile!
        return $module . $initialHandling;
    }

    public function Reload() {
        $this->ReloadForm();
    }

    private function GetFullUpdateMessage() {
        $result = [];

        $AggregationLevel = $this->GetValue('AggregationLevel');

        $AggregationLevelOptions = IPS_GetVariableProfile('eCPC.AggregationLevel')['Associations'];
        $AggregationLevelOptions = array_map(
            function ($a) use ($AggregationLevel) {
                $a['Selected'] = $a['Value'] == $AggregationLevel;
                return $a;
            },
            $AggregationLevelOptions
        );
        $result['AggregationLevelSelect'] = $AggregationLevelOptions;

        $generateChart = false;
        $initializedArchiveValues = [];
        foreach ($this->eCPCs as $eCPC) {
            if ($this->ReadPropertyInteger($eCPC) > 0) {
                $initializedArchiveValues[] = ['Name' => $eCPC, 'Value' => $this->ReadPropertyInteger($eCPC)];
                $generateChart = true;
            }
        }
        $result['ArchiveVarOptions'] = $initializedArchiveValues;

        $result['ArchiveStart'] = date('Y-m-d', $this->ReadAttributeInteger('ArchiveStart'));
        $result['ArchiveEnd'] = date('Y-m-d', $this->ReadAttributeInteger('ArchiveEnd'));

        if($generateChart && $this->ReadAttributeInteger('ArchiveVarSelect') > 0) {
            $result['Chart'] = $this->generateChart();
        }

        return json_encode($result);
    }

    private function generateChart() {
        $archiveID = $this->GetArchivID();
        $archiveVariable = $this->ReadAttributeInteger('ArchiveVarSelect');
        $aggregationLevel = $this->GetValue('AggregationLevel');

        $Profile = IPS_GetVariableProfile('Aggregationsstufe');
        $profile_name = '';
        foreach($Profile['Associations'] as $association) {
            if($association['Value'] == $aggregationLevel) {
                $profile_name = $association['Name'];
                break;
            }
        }

        $this->SendDebug('Debug', 'Debug $archiveVariable: '. $archiveVariable, 0);
        $temp = AC_GetAggregatedValues($archiveID, $archiveVariable, $aggregationLevel, $this->ReadAttributeInteger('ArchiveStart'), $this->ReadAttributeInteger('ArchiveEnd'), 0);  // 1 = day, 2 = Week, 3 = month,4 =year, 0 = Hour
        $label_format = $this->labelFormatHelper($aggregationLevel);
        $labels = $values = [];
        for ($count=0; $count < count($temp); $count++) {
            $verbrauch = (string) round($temp[$count]['Avg'], 2); // Durchschnittswert
            $zahl_neu = str_replace(".",",", $verbrauch); // Punkt durch Komma ersetzen
            $values[] = $verbrauch;
            $dat = date($label_format, $temp[$count]['TimeStamp']);
            $labels[] = $dat;
            $liste[$count] = $dat.": " . $zahl_neu;
        }

        $fontColor = $this->getHexColor($this->ReadPropertyInteger('FontColor'));
        $barColor = $this->getHexColor($this->ReadPropertyInteger('BarColor'));

        $headline = IPS_GetName($archiveVariable);

        return $this->drawQuickChart($labels, $values, $profile_name, $headline, $fontColor, $barColor);
    }

    private function labelFormatHelper($aggregationLevel) {
        return array_key_exists($aggregationLevel, $this->labelFormat) ? $this->labelFormat[$aggregationLevel] : "d.m.Y";
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'AggregationLevel':
                $this->SetValue('AggregationLevel', $Value);
                break;
            case 'ArchiveVarSelect':
                $this->WriteAttributeInteger('ArchiveVarSelect', $Value);
                break;
            case 'ArchiveStart':
                $this->WriteAttributeInteger('ArchiveStart', strtotime($Value));
                break;
            case 'ArchiveEnd':
                $this->WriteAttributeInteger('ArchiveEnd', strtotime($Value));
                break;
        }
        $this->UpdateVisualizationValue(json_encode([
            'Chart' => $this->generateChart()
        ]));
    }

    private function getHexColor($colorInt) {
        return sprintf('#%02x%02x%02x', ($colorInt >> 16) & 0xFF, ($colorInt >> 8) & 0xFF, $colorInt & 0xFF);
    }

    private function drawQuickChart($labels, $values, $label, $headline, $fontColor = '#FFFFFF', $barColor = '#000000') {
        // new chart object
        $chart = new QuickChart(['width' => 500, 'height' => 500, 'format' => 'svg']);
        // chart config
        $chart->setConfig("{
            type: 'bar',
            data: {
                labels: ['".implode("','", $labels)."'],   // Set X-axis labels
                datasets: [{
                    label: '$label',
                    backgroundColor: '".$barColor."',
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
                        color: '".$fontColor."',
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
        //$this->SendDebug('Debug', 'Debug $chart->getConfig: '. print_r($chart->getConfig()), 0);
        return $chart->toBinary();
    }
}