<?php

/**
 * @project       Rollladensteuerung/Rollladensteuerung
 * @file          RS_DoorWindowSensors.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

trait RS_DoorWindowSensors
{
    private function CheckDoorWindowSensors(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $status = false;
        foreach (json_decode($this->ReadPropertyString('DoorWindowSensors')) as $sensor) {
            if ($sensor->UseSettings) {
                $id = $sensor->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $actualValue = boolval(GetValue($id));
                    $triggerValue = boolval($sensor->TriggerValue);
                    if ($actualValue == $triggerValue) {
                        $status = true;
                    }
                }
            }
        }
        $doorWindowStatus = boolval($this->GetValue('DoorWindowStatus'));
        $this->SetValue('DoorWindowStatus', $status);
        if ($doorWindowStatus != $status) {
            $this->SendDebug(__FUNCTION__, 'Der Tür- / Fensterstatus hat sich auf "' . GetValueFormatted($this->GetIDForIdent('DoorWindowStatus')) . '" geändert!', 0);
            //Closed
            $settings = json_decode($this->ReadPropertyString('DoorWindowCloseAction'), true);
            if ($status) {
                $settings = json_decode($this->ReadPropertyString('DoorWindowOpenAction'), true);
            }
            if (!empty($settings)) {
                foreach ($settings as $setting) {
                    if ($setting['UseSettings']) {
                        $selectPosition = $setting['SelectPosition'];
                        switch ($selectPosition) {
                            case 0: # None
                                $this->SendDebug(__FUNCTION__, 'Abbruch, keine Aktion ausgewählt!', 0);
                                continue 2;

                            case 1: # Defined position
                                $position = $setting['DefinedPosition'];
                                break;

                            case 2: # Last position
                                $position = intval($this->GetValue('LastPosition'));
                                break;

                            case 3: # Setpoint position
                                $position = intval($this->GetValue('SetpointPosition'));
                                break;
                        }
                        if (isset($position)) {
                            //Check conditions
                            $conditions = [
                                ['type' => 0, 'condition' => ['Position' => $position, 'CheckPositionDifference' => $setting['CheckPositionDifference']]],
                                ['type' => 2, 'condition' => $setting['CheckAutomaticMode']],
                                ['type' => 3, 'condition' => $setting['CheckSleepMode']],
                                ['type' => 4, 'condition' => $setting['CheckBlindMode']],
                                ['type' => 5, 'condition' => $setting['CheckIsDay']],
                                ['type' => 6, 'condition' => $setting['CheckTwilight']],
                                ['type' => 7, 'condition' => $setting['CheckPresence']]];
                            $checkConditions = $this->CheckConditions(json_encode($conditions));
                            if (!$checkConditions) {
                                continue;
                            }
                            //Trigger action
                            if ($setting['UpdateSetpointPosition']) {
                                $this->SetValue('SetpointPosition', $position);
                            }
                            if ($setting['UpdateLastPosition']) {
                                $this->SetValue('LastPosition', $position);
                            }
                            $this->MoveBlind($position);
                        }
                    }
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Fensterstatus hat sich nicht geändert!', 0);
        }
    }
}