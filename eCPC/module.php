<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/schlauhaus.php';

class eCPC extends IPSModule
{
    use Schlauhaus;

    public array $eCPCs = ['HomeConsumption' => ['TotalHomeConsumptionID', 'TotalHomeConsumptionBatteryID', 'TotalHomeConsumptionPVID', 'TotalHomeConsumptionGridID']];

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     */
    public function Create() {
        parent::Create();

        foreach ($this->eCPCs['HomeConsumption'] as $eCPC) {
            $this->RegisterPropertyInteger($eCPC, 0);
        }

        $this->RegisterAttributeInteger('SelectDay', strtotime('-1 months'));

        $this->SetVisualizationType(1);
        $this->RegisterPropertyBoolean('AutoDebug', false);
    }


    /**
     * Overrides the internal IPSApplyChanges($id) function
     * @return void
     */
    public function ApplyChanges() {
        parent::ApplyChanges();

        $noValuesSet = true;
        foreach ($this->eCPCs['HomeConsumption'] as $homeConsumption) {
            if ($this->ReadPropertyInteger($homeConsumption) > 0) {
                $noValuesSet = false;
            }
        }
        if ($noValuesSet) {
            $this->SetStatus(201); // No home consumption values set
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

        $result['SelectDay'] = date('Y-m-d', $this->ReadAttributeInteger('SelectDay'));
        $result['Table'] = $this->generateTable();

        return json_encode($result);
    }

    private function generateTable() {
        $archiveID = $this->GetArchivID();
        $aggregationLevel = 1; // täglich
        $tableValues = [];
        $selectDay = $this->ReadAttributeInteger('SelectDay');
        $startTime = date("d.m.Y", $selectDay) . ' 00:00:00';
        $startTime = strtotime($startTime);
        $endTime = date("d.m.Y", $selectDay) . ' 23:59:59';
        $endTime = strtotime($endTime);

        foreach ($this->eCPCs['HomeConsumption'] as $homeConsumption) {
            $archiveVariable = $this->ReadPropertyInteger($homeConsumption);
            $temp = AC_GetAggregatedValues($archiveID, $archiveVariable, $aggregationLevel, $startTime, $endTime, 0);

            $labels = $values = [];
            for ($count=0; $count < count($temp); $count++) {
                $verbrauch = (string) round($temp[$count]['Avg'], 2); // Durchschnittswert
                $values[] = $verbrauch;
            }
            $tableValues[$homeConsumption] = $values;
        }

        return $tableValues;
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'SelectDay':
                $this->WriteAttributeInteger('SelectDay', strtotime($Value));
                break;
        }
        $this->UpdateVisualizationValue(json_encode([
            'SelectDay' => $Value,
            'Table' => $this->generateTable()
        ]));
    }

}
