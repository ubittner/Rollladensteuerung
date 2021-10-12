<?php

/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rollladensteuerung/tree/master/Rollladensteuerung
 */

declare(strict_types=1);

trait RS_backupRestore
{
    public function CreateBackup(int $BackupCategory): void
    {
        if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] == 102) {
            $name = 'Konfiguration (' . IPS_GetName($this->InstanceID) . ' #' . $this->InstanceID . ') ' . date('d.m.Y H:i:s');
            $config = json_decode(IPS_GetConfiguration($this->InstanceID), true);
            $config['CloseBlind'] = json_decode($config['CloseBlind'], true);
            $config['Timer'] = json_decode($config['Timer'], true);
            $config['OpenBlind'] = json_decode($config['OpenBlind'], true);
            $config['PositionPresets'] = json_decode($config['PositionPresets'], true);
            $config['DoorWindowSensors'] = json_decode($config['DoorWindowSensors'], true);
            $config['DoorWindowOpenAction'] = json_decode($config['DoorWindowOpenAction'], true);
            $config['DoorWindowCloseAction'] = json_decode($config['DoorWindowCloseAction'], true);
            $config['SwitchingTimeOneActions'] = json_decode($config['SwitchingTimeOneActions'], true);
            $config['SwitchingTimeTwoActions'] = json_decode($config['SwitchingTimeTwoActions'], true);
            $config['SwitchingTimeThreeActions'] = json_decode($config['SwitchingTimeThreeActions'], true);
            $config['SwitchingTimeFourActions'] = json_decode($config['SwitchingTimeFourActions'], true);
            $config['SunriseActions'] = json_decode($config['SunriseActions'], true);
            $config['SunsetActions'] = json_decode($config['SunsetActions'], true);
            $config['WeeklyScheduleActionOne'] = json_decode($config['WeeklyScheduleActionOne'], true);
            $config['WeeklyScheduleActionTwo'] = json_decode($config['WeeklyScheduleActionTwo'], true);
            $config['WeeklyScheduleActionThree'] = json_decode($config['WeeklyScheduleActionThree'], true);
            $config['WeeklyScheduleActionFour'] = json_decode($config['WeeklyScheduleActionFour'], true);
            $config['NightAction'] = json_decode($config['NightAction'], true);
            $config['DayAction'] = json_decode($config['DayAction'], true);
            $config['TwilightDayAction'] = json_decode($config['TwilightDayAction'], true);
            $config['TwilightNightAction'] = json_decode($config['TwilightNightAction'], true);
            $config['AbsenceAction'] = json_decode($config['AbsenceAction'], true);
            $config['PresenceAction'] = json_decode($config['PresenceAction'], true);
            $config['Triggers'] = json_decode($config['Triggers'], true);
            $config['EmergencyTriggers'] = json_decode($config['EmergencyTriggers'], true);
            $json_string = json_encode($config, JSON_HEX_APOS | JSON_PRETTY_PRINT);
            $content = "<?php\n// Backup " . date('d.m.Y, H:i:s') . "\n// ID " . $this->InstanceID . "\n$" . "config = '" . $json_string . "';";
            $backupScript = IPS_CreateScript(0);
            IPS_SetParent($backupScript, $BackupCategory);
            IPS_SetName($backupScript, $name);
            IPS_SetHidden($backupScript, true);
            IPS_SetScriptContent($backupScript, $content);
            echo 'Die Konfiguration wurde erfolgreich gesichert!';
        }
    }

    public function RestoreConfiguration(int $ConfigurationScript): void
    {
        if ($ConfigurationScript != 0 && IPS_ObjectExists($ConfigurationScript)) {
            $object = IPS_GetObject($ConfigurationScript);
            if ($object['ObjectType'] == 3) {
                $content = IPS_GetScriptContent($ConfigurationScript);
                preg_match_all('/\'([^;]+)\'/', $content, $matches);
                $config = json_decode($matches[1][0], true);
                $config['CloseBlind'] = json_encode($config['CloseBlind']);
                $config['Timer'] = json_encode($config['Timer']);
                $config['OpenBlind'] = json_encode($config['OpenBlind']);
                $config['PositionPresets'] = json_encode($config['PositionPresets']);
                $config['DoorWindowSensors'] = json_encode($config['DoorWindowSensors']);
                $config['DoorWindowOpenAction'] = json_encode($config['DoorWindowOpenAction']);
                $config['DoorWindowCloseAction'] = json_encode($config['DoorWindowCloseAction']);
                $config['SwitchingTimeOneActions'] = json_encode($config['SwitchingTimeOneActions']);
                $config['SwitchingTimeTwoActions'] = json_encode($config['SwitchingTimeTwoActions']);
                $config['SwitchingTimeThreeActions'] = json_encode($config['SwitchingTimeThreeActions']);
                $config['SwitchingTimeFourActions'] = json_encode($config['SwitchingTimeFourActions']);
                $config['SunriseActions'] = json_encode($config['SunriseActions']);
                $config['SunsetActions'] = json_encode($config['SunsetActions']);
                $config['WeeklyScheduleActionOne'] = json_encode($config['WeeklyScheduleActionOne']);
                $config['WeeklyScheduleActionTwo'] = json_encode($config['WeeklyScheduleActionTwo']);
                $config['WeeklyScheduleActionThree'] = json_encode($config['WeeklyScheduleActionThree']);
                $config['WeeklyScheduleActionFour'] = json_encode($config['WeeklyScheduleActionThree']);
                $config['NightAction'] = json_encode($config['NightAction']);
                $config['DayAction'] = json_encode($config['DayAction']);
                $config['TwilightDayAction'] = json_encode($config['TwilightDayAction']);
                $config['TwilightNightAction'] = json_encode($config['TwilightNightAction']);
                $config['AbsenceAction'] = json_encode($config['AbsenceAction']);
                $config['PresenceAction'] = json_encode($config['PresenceAction']);
                $config['Triggers'] = json_encode($config['Triggers']);
                $config['EmergencyTriggers'] = json_encode($config['EmergencyTriggers']);
                IPS_SetConfiguration($this->InstanceID, json_encode($config));
                if (IPS_HasChanges($this->InstanceID)) {
                    IPS_ApplyChanges($this->InstanceID);
                }
            }
            echo 'Die Konfiguration wurde erfolgreich wiederhergestellt!';
        }
    }
}
