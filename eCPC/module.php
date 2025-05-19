<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/schlauhaus.php';

class eCPC extends IPSModule
{
    use Schlauhaus;

    public array $eCPCs = ['TotalHomeConsumptionID', 'TotalHomeConsumptionGridID', 'TotalDCPVEnergyID', 'TotalHomeConsumptionPVID', 'TotalEnergyACSideToGridID', 'TotalHomeConsumptionBatteryID', 'TotalDCchargeEnergyID', 'TotalACchargeEnergyID', 'TotalACdischargeEnergyID'];

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
            $this->RegisterPropertyFloat($eCPC, 0);
        }

        $this->EnableAction('AggregationLevel');

        $this->RegisterPropertyInteger('ArchiveStart', strtotime('-1 months'));
        $this->RegisterPropertyInteger('ArchiveEnd', time());
        $this->RegisterAttributeInteger('ArchiveVarSelect', 0);

        // Eine Eigenschaft für die Hintergrundfarbe
        $this->RegisterPropertyInteger('Color', 0xff0000);

        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
    }

    /**
     * @return void
     */
    public function Destroy() {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * Overrides the internal IPSApplyChanges($id) function
     * @return void
     */
    public function ApplyChanges() {
        parent::ApplyChanges();

        $noValuesSet = true;
        foreach ($this->eCPCs as $eCPC) {
            if ($this->ReadPropertyFloat($eCPC) > 0) {
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

        // Add static HTML content from file to make editing easier
        $module = file_get_contents(__DIR__ . '/module.html');

        // Return everything to render our fancy tile!
        return $module . $initialHandling;
    }

    public function Reload() {
        $this->ReloadForm();
    }

    private function GetFullUpdateMessage() {
        $result = [];

        $result['ArchiveID'] = $this->GetArchivID();
        $AggregationLevel = $this->GetValue('AggregationLevel');
        $result['AggregationLevel'] = $AggregationLevel;

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
            if ($this->ReadPropertyFloat($eCPC) > 0) {
                $initializedArchiveValues[] = ['Name' => $eCPC, 'Value' => $this->ReadPropertyFloat($eCPC)];
                $generateChart = true;
            }
        }
        $result['ArchiveVarSelect'] = $initializedArchiveValues;
        
        $result['ArchiveStart'] = date('Y-m-d', $this->ReadPropertyInteger('ArchiveStart'));
        $result['ArchiveEnd'] = date('Y-m-d', $this->ReadPropertyInteger('ArchiveEnd'));

        if($generateChart) {
            $result['Chart'] = $this->generateChart();
        }

        return json_encode($result) ;
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'AggregationLevel':
                $this->SetValue('AggregationLevel', $Value);
                break;
            case 'ArchiveVarSelect':
                $this->WriteAttributeInteger('ArchiveVarSelect', $Value);
                break;
                // TODO Add more actions here
        }
    }

    private function generateChart() {
        return $this->ReadAttributeInteger('ArchiveVarSelect');
/*
        $temp = AC_GetAggregatedValues($this->GetArchivID(), $this->ReadAttributeInteger('ArchiveVarSelect'), $this->ReadPropertyInteger('AggregationLevel'), $this->ReadPropertyInteger('ArchiveStart'), $this->ReadPropertyInteger('ArchiveEnd'), 0); // 1 = day, 2 = Week, 3 = month,4 =year, 0 = Hour
        $label_format_helper = [2 => 'W', 3 => 'M', 4 => 'Y'];
        $label_format = array_key_exists($this->ReadPropertyInteger('AggregationLevel'), $label_format_helper) ? $label_format_helper[$this->ReadPropertyInteger('AggregationLevel')] : "d.m.Y";

        $labels = $dates = [];
        for ($count=0; $count < count($temp); $count++) {
            $verbrauch = (string) round($temp[$count]['Avg'], 2); // Durchschnittswert
            $zahl_neu = str_replace(".",",", $verbrauch); // Punkt durch Komma ersetzen
            // $values[] = $verbrauch;
            $dat = date($label_format, $temp[$count]['TimeStamp']);
            // $labels[] = $dat;
            $liste[$count] = $dat.": " . $zahl_neu;
//            echo $liste[$count]."<br>\n";
        }
        return $liste;
*/
    }
    
    
}