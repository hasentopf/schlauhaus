<?php

/**
 * EventHelper.php
 *
 * Part of the Trait-Libraray for IP-Symcon Modules.
 *
 * @package       traits
 * @author        Heiko Wilknitz <heiko@wilkware.de>
 * @copyright     2020 Heiko Wilknitz
 * @link          https://wilkware.de
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

declare(strict_types=1);

namespace libs;
/**
 * Helper trait to create timer and events.
 */
trait EventHelper
{
    /**
     * Determines the archive ID
     * @param $guid
     * @return false|mixed
     */
    protected function ECPC_GetArchivID($guid = "{43192F0B-135B-4CE7-A0A7-1475603F3060}")
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
