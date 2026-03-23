<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/schlauhaus.php';

class EnergyMonitor extends IPSModule
{
    use Schlauhaus;

    private const ROUND_DECIMALS = 2;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ArchiveId', 0);
        $this->RegisterPropertyString('Variables', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyBoolean('EarlyMorningUpdate', true);
        $this->RegisterPropertyInteger('EarlyMorningWindow', 600);

        $this->RegisterTimer('UpdateTimer', 0, 'PVW_Update($_IPS[\'TARGET\']);');

        $this->RegisterVariableString('HTMLContent', 'HTML Content', '', 0);
        $this->RegisterVariableFloat('TotalYesterday', 'Total Yesterday', '', 1);
        $this->RegisterVariableFloat('TotalToday', 'Total Today', '', 2);
        $this->RegisterVariableFloat('TotalYesterdayYield', 'Total Yesterday Yield', '', 3);
        $this->RegisterVariableFloat('TotalTodayYield', 'Total Today Yield', '', 4);
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterVariableFloat('TotalYesterday', 'Total Yesterday', '', 1);
        $this->RegisterVariableFloat('TotalToday', 'Total Today', '', 2);
        $this->RegisterVariableFloat('TotalYesterdayYield', 'Total Yesterday Yield', '', 3);
        $this->RegisterVariableFloat('TotalTodayYield', 'Total Today Yield', '', 4);

        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->UpdateData();
        }
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
        switch ($ident) {
            case 'Update':
                $this->UpdateData();
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    public function Update()
    {
        $this->UpdateData();
    }

    private function UpdateData()
    {
        $archiveId = $this->ReadPropertyInteger('ArchiveId');
        if ($archiveId === 0) {
            $instances = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
            $archiveId = $instances[0] ?? 0;
        }

        if ($archiveId === 0) {
            $this->LogMessage('No archive instance found', KL_ERROR);
            return;
        }

        $variables = json_decode($this->ReadPropertyString('Variables'), true);
        if (empty($variables)) {
            $this->LogMessage('No variables configured', KL_WARNING);
            return;
        }

        $earlyMorningUpdate = $this->ReadPropertyBoolean('EarlyMorningUpdate');
        $earlyMorningWindow = $this->ReadPropertyInteger('EarlyMorningWindow');
        $isEarly = $this->isShortlyAfterMidnight($earlyMorningWindow);

        $data = $this->collectData($archiveId, $variables, $isEarly);
        $this->saveData($data);
        $html = $this->generateHtml($data);
        
        $htmlContentId = $this->GetIDForIdent('HTMLContent');
        if ($htmlContentId !== false) {
            SetValueString($htmlContentId, $html);
        }
    }

    private function isShortlyAfterMidnight(int $seconds = 300): bool
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $midnight = $now->setTime(0, 0, 0);
        $diff = $now->getTimestamp() - $midnight->getTimestamp();
        return $diff >= 0 && $diff < $seconds;
    }

    private function collectData(int $archiveId, array $variables, bool $updateYesterday): array
    {
        $data = [];

        foreach ($variables as $var) {
            $varId = $var['VariableId'] ?? 0;
            $yesterdayTarget = $var['YesterdayTarget'] ?? 0;
            $todayTarget = $var['TodayTarget'] ?? 0;
            $name = $var['Title'] ?? '';
            $type = $var['Type'] ?? 'verbrauch';

            if ($varId === 0 || $yesterdayTarget === 0 || $todayTarget === 0) {
                continue;
            }

            if ($updateYesterday) {
                $yesterdayData = AC_GetAggregatedValues($archiveId, $varId, 1, strtotime('yesterday'), strtotime('today') - 1, 0);
                SetValueFloat($yesterdayTarget, $this->calcConsumption($yesterdayData));
            }

            $todayData = AC_GetAggregatedValues($archiveId, $varId, 1, strtotime('today'), time(), 0);
            SetValueFloat($todayTarget, $this->calcConsumption($todayData));

            $data[] = [
                'title' => $name,
                'type' => $type,
                'yesterday' => GetValueFloat($yesterdayTarget),
                'today' => GetValueFloat($todayTarget)
            ];
        }

        return $data;
    }

    private function calcConsumption(array $values): float
    {
        $consumption = 0;
        foreach ($values as $value) {
            $consumption += $value['Avg'];
        }
        return round($consumption, self::ROUND_DECIMALS);
    }

    private function saveData(array $data): void
    {
        $totalYesterday = 0;
        $totalToday = 0;
        $totalYesterdayYield = 0;
        $totalTodayYield = 0;

        foreach ($data as $item) {
            if ($item['type'] === 'verbrauch') {
                $totalYesterday += $item['yesterday'];
                $totalToday += $item['today'];
            } else {
                $totalYesterdayYield += $item['yesterday'];
                $totalTodayYield += $item['today'];
            }
        }

        $this->SetValue('TotalYesterday', round($totalYesterday, self::ROUND_DECIMALS));
        $this->SetValue('TotalToday', round($totalToday, self::ROUND_DECIMALS));
        $this->SetValue('TotalYesterdayYield', round($totalYesterdayYield, self::ROUND_DECIMALS));
        $this->SetValue('TotalTodayYield', round($totalTodayYield, self::ROUND_DECIMALS));
    }

    private function generateHtml(array $data): string
    {
        $html = '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%; text-align: left;">';
        $html2 = '<br><table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%; text-align: left;">';
        $html .= '<tr><th>Hausverbrauch</th><th>Gestern</th><th>Heute</th></tr>';
        $html2 .= '<tr><th>Hausertrag</th><th>Gestern</th><th>Heute</th></tr>';

        $totalYesterday = 0;
        $totalToday = 0;
        $totalYesterdayYield = 0;
        $totalTodayYield = 0;

        foreach ($data as $item) {
            if ($item['type'] === 'verbrauch') {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['title']) . '</td>';
                $html .= '<td>' . $item['yesterday'] . ' kWh</td>';
                $html .= '<td>' . $item['today'] . ' kWh</td>';
                $html .= '</tr>';
                $totalYesterday += $item['yesterday'];
                $totalToday += $item['today'];
            } else {
                $html2 .= '<tr>';
                $html2 .= '<td>' . htmlspecialchars($item['title']) . '</td>';
                $html2 .= '<td>' . $item['yesterday'] . ' kWh</td>';
                $html2 .= '<td>' . $item['today'] . ' kWh</td>';
                $html2 .= '</tr>';
                $totalYesterdayYield += $item['yesterday'];
                $totalTodayYield += $item['today'];
            }
        }

        $html .= '<tr style="font-weight: bold;"><td>Gesamt</td><td>' . round($totalYesterday, self::ROUND_DECIMALS) . ' kWh</td><td>' . round($totalToday, self::ROUND_DECIMALS) . ' kWh</td></tr></table>';
        $html2 .= '<tr style="font-weight: bold;"><td>Gesamt</td><td>' . round($totalYesterdayYield, self::ROUND_DECIMALS) . ' kWh</td><td>' . round($totalTodayYield, self::ROUND_DECIMALS) . ' kWh</td></tr></table>';

        return $html . $html2;
    }

    public function GetVisualizationTile()
    {
        $module = file_get_contents(__DIR__ . '/module.html');
        
        $htmlContentId = $this->GetIDForIdent('HTMLContent');
        if ($htmlContentId !== false) {
            $html = GetValueString($htmlContentId);
        } else {
            $html = '';
        }
        
        return str_replace('{{CONTENT}}', $html, $module);
    }
}
