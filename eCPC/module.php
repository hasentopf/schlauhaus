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

        $this->RegisterPropertyBoolean('Active', false);

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

        $value = array();
        $result = $this->ReadAttributeString("Homes");
        $this->SendDebug('Homes-Values_Attribute', json_encode($result),0)	;
        if ($result == '') return;
        $homes = json_decode($result, true);
        $value[] = ["caption"=> "", "value"=> "" ];
        foreach ($homes["data"]["viewer"]["homes"] as $key => $home){
            $value[] = ["caption"=> $home["appNickname"], "value"=> $home["id"] ];
        }
        $this->SendDebug('Homes-Values', json_encode($value),0)	;
        $jsonform["elements"][2]['items'][0]["options"] = $value;
        $this->SendDebug('Homes-Values', json_encode($jsonform),0)	;
        return json_encode($jsonform);
    }
}