<?php

/**
 * 
 */
trait Schlauhaus {
    
    /**
     * Determines the archive ID
     * @param $guid
     * @return false|mixed
     */
    protected function GetArchivID($guid = "{43192F0B-135B-4CE7-A0A7-1475603F3060}")
    {
        $array = IPS_GetInstanceListByModuleID($guid);
        $archive_id = @$array[0];
        if (!isset($archive_id)) {
            IPSLogger_Dbg(basename(__FILE__), "Archive Control nicht gefunden!");
            return false;
        }
        return $archive_id;
    }
    
}