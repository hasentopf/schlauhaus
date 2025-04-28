<?php

declare(strict_types=1);
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

        $this->RegisterPropertyBoolean('InstanceActive', false);
        $this->RegisterPropertyFloat('TotalHomeConsumptionID', 0);
        $this->RegisterPropertyFloat('TotalHomeConsumptionGridID', 0);
        $this->RegisterPropertyFloat('TotalDCPVEnergyID', 0);
        $this->RegisterPropertyFloat('TotalHomeConsumptionPVID', 0);

    }

    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
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