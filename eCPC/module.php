<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/_traits.php';

class eCPC extends IPSModule
{
    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     */
    public function Create()
    {
        //Never delete this line!
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
        $this->RegisterPropertyFloat('TotalHomeConsumptionID', 0);
        $this->RegisterPropertyFloat('TotalHomeConsumptionGridID', 0);
        $this->RegisterPropertyFloat('TotalDCPVEnergyID', 0);
        $this->RegisterPropertyFloat('TotalHomeConsumptionPVID', 0);
        $this->RegisterPropertyFloat('TotalEnergyACSideToGridID', 0);
        $this->RegisterPropertyFloat('TotalHomeConsumptionBatteryID', 0);
        $this->RegisterPropertyFloat('TotalDCchargeEnergyID', 0);
        $this->RegisterPropertyFloat('TotalACchargeEnergyID', 0);
        $this->RegisterPropertyFloat('TotalACdischargeEnergyID', 0);

        $this->EnableAction('AggregationLevel');

        $this->SetVisualizationType(1);
    }

    /**
     * Overrides the internal IPSApplyChanges($id) function
     * @return void
     */
    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
    }


    public function RequestAction($Ident, $Value) {
        $this->SetValue($Ident, $Value);
        $this->UpdateVisualizationValue($this->GetUpdatedValue($Ident));
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     */
    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm()
    {
        $jsonform = json_decode(file_get_contents(__DIR__."/form.json"), true);

        return json_encode($jsonform);
    }
}