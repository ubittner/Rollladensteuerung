<?php

/**
 * @project       Rollladensteuerung/Rollladensteuerung
 * @file          RS_IsDayDetection.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait RS_IsDayDetection
{
    public function ExecuteIsDayDetection(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('IsDay');
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $id . ' (Ist es Tag) hat sich geändert!', 0);
        $actualStatus = boolval(GetValue($id)); # false = night, true = day
        $statusName = 'Es ist Nacht';
        $actionName = 'NightAction';
        if ($actualStatus) {
            $statusName = 'Es ist Tag';
            $actionName = 'DayAction';
        }
        $this->SendDebug(__FUNCTION__, 'Aktueller Status: ' . $statusName, 0);
        $action = $this->CheckAction('IsDay', $actionName);
        if (!$action) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $statusName . ' hat keine aktivierten Aktionen!', 0);
            return;
        }
        foreach (json_decode($this->ReadPropertyString($actionName), true) as $setting) {
            if ($setting['UseSettings']) {
                $selectPosition = $setting['SelectPosition'];
                switch ($selectPosition) {
                    case 0: # None
                        $this->SendDebug(__FUNCTION__, 'Abbruch, keine Aktion ausgewählt!', 0);
                        continue 2;

                    case 1: # Defined position
                        $position = $setting['Position'];
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
                        ['type' => 1, 'condition' => ['Position' => $position, 'CheckLockoutProtection' => $setting['CheckLockoutProtection']]],
                        ['type' => 2, 'condition' => $setting['CheckAutomaticMode']],
                        ['type' => 3, 'condition' => $setting['CheckSleepMode']],
                        ['type' => 4, 'condition' => $setting['CheckBlindMode']],
                        ['type' => 6, 'condition' => $setting['CheckTwilight']],
                        ['type' => 7, 'condition' => $setting['CheckPresence']],
                        ['type' => 8, 'condition' => $setting['CheckDoorWindowStatus']]];
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
                    $this->TriggerExecutionDelay(intval($setting['ExecutionDelay']));
                    $this->MoveBlind($position);
                }
            }
        }
    }
}