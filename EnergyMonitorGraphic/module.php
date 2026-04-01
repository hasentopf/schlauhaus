<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/schlauhaus.php';

class EnergyMonitorGraphic extends IPSModule
{
    use Schlauhaus;

    private const ROUND_DECIMALS = 2;
    private const ROUND_FLOATS = 1;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyFloat('TemperatureVariable', 0);
        $this->RegisterPropertyInteger('BatterySocVariable', 0);
        $this->RegisterPropertyInteger('eCarVariable', 0);
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', "SenderID: $SenderID, Message: $Message", 0);

        if ($Message === IPS_KERNELSTARTED) {
            $this->UpdateData();
        }
    }

    public function RequestAction($ident, $value)
    {

    }

    private function generateJS()
    {
        return $this->GenerateData();
    }

    public function GenerateData()
    {
        $consumption_values = [
            ['id' => 49614, 'js_id' => 'bezug_netz'],
            ['id' => 43577, 'js_id' => 'bezug_akku'],
            ['id' => 53459, 'js_id' => 'pv_erzeugung'],
        ];
        $data = [];
        foreach ($consumption_values as $value) {
            $data[$value['js_id']] = GetValueFloat($value['id']) . ' kWh';
        }
        $tempVar = $this->ReadPropertyFloat('TemperatureVariable');
        if ($tempVar > 0) {
            $data['temperatur'] = round(GetValueFloat($tempVar), self::ROUND_FLOATS) . '°C';
        }
        $batterySocVar = $this->ReadPropertyInteger('BatterySocVariable');
        if ($batterySocVar > 0) {
            $data['akku_stand'] = round(GetValueInteger($batterySocVar), self::ROUND_DECIMALS) . '%';
        }
        $eCarVar = $this->ReadPropertyInteger('eCarVariable');
        if ($eCarVar > 0) {
            // Add eCar consumption data if variable is configured
            $data['verbrauch_eAuto'] = GetValueFloat($eCarVar) . ' kWh';
        }
        return json_encode($data);
    }

    public function GetDataProvider()
    {
        return [
            'DataID' => '{3708, ' . $this->InstanceID . ', "GenerateData"}',
            'JSData' => $this->generateJS()
        ];
    }

    public function GetVisualizationTile()
    {
        $module = file_get_contents(__DIR__ . '/module.html');
        
        // Check if battery and eCar variables are configured
        $batterySocVar = $this->ReadPropertyInteger('BatterySocVariable');
        $eCarVar = $this->ReadPropertyInteger('eCarVariable');
        
        // Generate CSS to show/hide SVG elements based on configuration
        $customCSS = '<style>';
        if ($batterySocVar > 0) {
            $customCSS .= '#Akku { display: block !important; }';
        }
        if ($eCarVar > 0) {
            $customCSS .= '#eAuto { display: block !important; }';
        }
        $customCSS .= '</style>';
        
        // Insert custom CSS before the closing </head> tag
        $module = str_replace('</head>', $customCSS . '</head>', $module);
        
        $script = '<script>
    const elementMap = {
        pv_erzeugung: document.getElementById("pv_erzeugung"),
        pv_einspeisung: document.getElementById("pv_einspeisung"),
        bezug_netz_heute: document.getElementById("bezug_netz"),
        bezug_akku_heute: document.getElementById("bezug_akku"),
        temperatur: document.getElementById("temperatur"),
        verbrauch_heizung: document.getElementById("verbrauch_heizung"),
        verbrauch_eAuto: document.getElementById("verbrauch_eAuto"),
        akku_stand: document.getElementById("akku_stand")
    };

    function handleMessage(data) {
        if (typeof data === "string") {
            data = JSON.parse(data);
        }
        Object.keys(data).forEach(function(key) {
            const el = elementMap[key];
            if (el) {
                el.textContent = data[key];
            }
        });
    }

    function reloadData() {
        IPS_RunScriptChannel({ "DataID": "{3708, ' . $this->InstanceID . ', GenerateData}", "Callback": "handleMessage" });
    }

    window.onload = function () {
        handleMessage(' . json_encode($this->generateJS()) . ');
        reloadData();
        setInterval(reloadData, 60000);
    }
</script>';
        return $module . $script;
    }
}
