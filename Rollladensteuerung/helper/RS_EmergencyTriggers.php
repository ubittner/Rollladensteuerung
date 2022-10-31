<?php

/**
 * @project       Rollladensteuerung/Rollladensteuerung
 * @file          RS_EmergencyTriggers.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait RS_EmergencyTriggers
{
    public function ExecuteEmergencyTrigger(int $VariableID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        foreach (json_decode($this->ReadPropertyString('EmergencyTriggers'), true) as $setting) {
            $id = $setting['ID'];
            if ($VariableID == $id) {
                if ($setting['UseSettings']) {
                    $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' wurde aktualisiert.', 0);
                    $actualValue = boolval(GetValue($VariableID));
                    $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . json_encode($actualValue), 0);
                    $triggerValue = boolval($setting['TriggerValue']);
                    $this->SendDebug(__FUNCTION__, 'Auslösender Wert: ' . json_encode($triggerValue), 0);
                    //We have a trigger value
                    if ($actualValue == $triggerValue) {
                        $this->SendDebug(__FUNCTION__, 'Die Aktion für die Variable ' . $VariableID . ' wird verwendet.', 0);
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
                            $this->MoveBlind($position);
                        }
                        $automaticMode = intval($setting['AutomaticMode']);
                        switch ($automaticMode) {
                            case 1: # Off
                                $this->ToggleAutomaticMode(false);
                                break;

                            case 2: # On
                                $this->ToggleAutomaticMode(true);
                                break;

                        }
                        $sleepMode = intval($setting['SleepMode']);
                        switch ($sleepMode) {
                            case 1: # Off
                                $this->ToggleSleepMode(false);
                                break;

                            case 2: # On
                                $this->ToggleSleepMode(true);
                                break;

                        }
                    } else {
                        $this->SendDebug(__FUNCTION__, 'Die Aktion für die Variable ' . $VariableID . ' wird nicht verwendet.', 0);
                    }
                }
            }
        }
    }
}