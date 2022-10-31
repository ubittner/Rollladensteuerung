<?php

/**
 * @project       Rollladensteuerung/Rollladensteuerung
 * @file          RS_MoveBlind.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpVoidFunctionResultUsedInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait RS_MoveBlind
{
    public function MoveBlind(int $Position, int $Duration = 0, int $DurationUnit = 0): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, '$Position: ' . $Position, 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $id = $this->ReadPropertyInteger('ActuatorControl');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist kein Rollladenaktor vorhanden!', 0);
            return false;
        }
        //Check activity status
        $activityStatus = $this->ReadPropertyInteger('ActuatorActivityStatus');
        if ($activityStatus != 0 && @IPS_ObjectExists($activityStatus)) {
            if (intval(GetValue($activityStatus)) == 1) {
                $this->SendDebug(__FUNCTION__, 'Rollladen fährt noch, Zielposition noch nicht erreicht!', 0);
                $useStopFunction = $this->ReadPropertyBoolean('EnableStopFunction');
                if ($useStopFunction) {
                    $this->StopBlindMoving();
                    return false;
                }
            }
        }
        $result = false;
        $commandControl = $this->ReadPropertyInteger('CommandControl');
        $actualBlindMode = $this->GetValue('BlindMode');
        $actualBlindSliderValue = $this->GetValue('BlindSlider');
        $actualPositionPreset = $this->GetValue('PositionPresets');
        $actualLastPosition = $this->GetValue('LastPosition');
        //Closed
        if ($Position == 0) {
            $mode = 0;
            $modeText = 'geschlossen';
            $this->DeactivateBlindModeTimer();
        }
        //Timer
        if ($Position > 0 && $Duration != 0) {
            $mode = 2;
            $modeText = 'bewegt (Timer)';
            $this->SetBlindTimer($Duration, $DurationUnit);
        }
        //Open
        if ($Position > 0 && $Duration == 0) {
            $mode = 3;
            $modeText = 'geöffnet';
            if ($actualBlindMode == 2) {
                $this->DeactivateBlindModeTimer();
            }
        }
        if (isset($modeText)) {
            $this->SendDebug(__FUNCTION__, 'Der Rollladen wird auf ' . $Position . '% ' . $modeText . '.', 0);
        }
        if (isset($mode)) {
            $this->SetValue('BlindMode', $mode);
            $this->SetValue('BlindSlider', $Position);
            if ($this->ReadPropertyBoolean('PositionPresetsUpdateButton')) {
                $this->SetClosestPositionPreset($Position);
            }
            $variableType = @IPS_GetVariable($id)['VariableType'];
            switch ($variableType) {
                case 0: # Boolean
                    $actualVariableValue = boolval(GetValue($id));
                    $newVariableValue = boolval($Position);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $newVariableValue = !$newVariableValue;
                    }
                    break;

                case 1: # Integer
                    $actualVariableValue = intval(GetValue($id));
                    $newVariableValue = $Position;
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $newVariableValue = abs($newVariableValue - 100);
                    }
                    break;

                case 2: # Float
                    $actualVariableValue = floatval(GetValue($id));
                    $newVariableValue = floatval($Position / 100);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $newVariableValue = abs($newVariableValue - 1);
                    }
                    break;
            }
            if (isset($actualVariableValue) && isset($newVariableValue)) {
                if ($actualVariableValue == $newVariableValue) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $id . ' hat bereits den Wert: ' . json_encode($newVariableValue) . ' = ' . $Position . '%!', 0);
                    return false;
                } else {
                    $this->SendDebug(__FUNCTION__, 'Variable ' . $id . ', neuer Wert: ' . $newVariableValue . ', Position: ' . json_encode($Position) . '%', 0);
                    //Command control
                    if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) { //0 = main category, 1 = none
                        $commands = [];
                        $commands[] = '@RequestAction(' . $id . ", '" . $newVariableValue . "');";
                        $this->SendDebug(__FUNCTION__, 'Befehl: ' . json_encode(json_encode($commands)), 0);
                        $scriptText = self::ABLAUFSTEUERUNG_MODULE_PREFIX . '_ExecuteCommands(' . $commandControl . ', ' . json_encode(json_encode($commands)) . ');';
                        $this->SendDebug(__FUNCTION__, 'Ablaufsteuerung: ' . $scriptText, 0);
                        $result = @IPS_RunScriptText($scriptText);
                    } else {
                        $result = @RequestAction($id, $newVariableValue);
                        if (!$result) {
                            IPS_Sleep(self::DEVICE_DELAY_MILLISECONDS);
                            $result = @RequestAction($id, $newVariableValue);
                            if (!$result) {
                                if (isset($modeText)) {
                                    $logText = 'Fehler, der Rollladen mit der ID ' . $id . ' konnte nicht ' . $modeText . ' werden!';
                                    $this->SendDebug(__FUNCTION__, $logText, 0);
                                    $this->LogMessage('Instanz: ' . $this->InstanceID . ', ' . __FUNCTION__ . ': ' . $logText, KL_WARNING);
                                }
                            }
                        }
                    }
                    if (!$result) {
                        //Revert switch
                        $this->SetValue('BlindMode', $actualBlindMode);
                        $this->SetValue('BlindSlider', $actualBlindSliderValue);
                        if ($this->ReadPropertyBoolean('PositionPresetsUpdateButton')) {
                            $this->SetValue('PositionPresets', $actualPositionPreset);
                        }
                        $this->SetValue('LastPosition', $actualLastPosition);
                    } else {
                        if (isset($modeText)) {
                            $this->SendDebug(__FUNCTION__, 'Der Rollladen wurde ' . $modeText . '.', 0);
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function StopBlindTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->DeactivateBlindModeTimer();
        $settings = json_decode($this->ReadPropertyString('Timer'), true)[0];
        $operationalAction = intval($settings['OperationalAction']);
        switch ($operationalAction) {
            case 0: # None
                $this->SendDebug(__FUNCTION__, 'Aktion: Keine', 0);
                break;

            case 1: # Last position
                $lastPosition = intval($this->GetValue('LastPosition'));
                $this->SendDebug(__FUNCTION__, 'Aktion: Letzte Position, ' . $lastPosition . '%', 0);
                $this->MoveBlind($lastPosition);
                break;

            case 2: # Setpoint position
                $setpointPosition = intval($this->GetValue('SetpointPosition'));
                $this->SendDebug(__FUNCTION__, 'Aktion: Soll-Position, ' . $setpointPosition . '%', 0);
                $this->MoveBlind($setpointPosition);
                break;

            case 3: # Defined position
                $definedPosition = intval($settings['DefinedPosition']);
                $this->SendDebug(__FUNCTION__, 'Aktion: Definerte Position, ' . $definedPosition . '%', 0);
                $this->MoveBlind($definedPosition);
                break;
        }
    }

    public function UpdateBlindPosition(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ActuatorActivityStatus');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $updateBlindPosition = $this->ReadPropertyBoolean('ActuatorUpdateBlindPosition');
            if (!$updateBlindPosition) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Aktualisierung der Rollladenposition ist deaktiviert!', 0);
                return;
            }
            if (GetValue($id) == 0) { # WORKING / PROCESS, 0 = idle
                IPS_Sleep(250);
                $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $actualPosition = $this->GetActualBlindPosition();
                    $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $actualPosition . '%.', 0);
                    $blindMode = 0;
                    if ($actualPosition > 0) {
                        $blindMode = 3;
                    }
                    $this->SetValue('BlindMode', $blindMode);
                    $this->SetValue('BlindSlider', $actualPosition);
                    if ($this->ReadPropertyBoolean('PositionPresetsUpdateButton')) {
                        $this->SetClosestPositionPreset($actualPosition);
                    }
                    if ($this->ReadPropertyBoolean('ActuatorUpdateSetpointPosition')) {
                        $this->SetValue('SetpointPosition', $actualPosition);
                    }
                    if ($this->ReadPropertyBoolean('ActuatorUpdateLastPosition')) {
                        $this->SetValue('LastPosition', $actualPosition);
                    }
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'Rollladen fährt noch, Zielposition noch nicht erreicht!', 0);
            }
        }
    }

    #################### Private

    private function StopBlindMoving(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ActuatorControl');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, es ist kein Rollladenaktor vorhanden!', 0);
            return;
        }
        $parent = IPS_GetParent($id);
        if ($parent != 0 && @IPS_ObjectExists($parent)) {
            $moduleID = IPS_GetInstance($parent)['ModuleInfo']['ModuleID'];
            if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, der zugewiesene Rollladenaktor ist kein Homematic Gerät!', 0);
                return;
            }
            //Command control
            $commandControl = $this->ReadPropertyInteger('CommandControl');
            if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) { //0 = main category, 1 = none
                $commands = [];
                $commands[] = '@HM_WriteValueInteger(' . $id . ', "STOP", true);';
                $this->SendDebug(__FUNCTION__, 'Befehl: ' . json_encode(json_encode($commands)), 0);
                $scriptText = self::ABLAUFSTEUERUNG_MODULE_PREFIX . '_ExecuteCommands(' . $commandControl . ', ' . json_encode(json_encode($commands)) . ');';
                $this->SendDebug(__FUNCTION__, 'Ablaufsteuerung: ' . $scriptText, 0);
                $result = @IPS_RunScriptText($scriptText);
            } else {
                $result = HM_WriteValueBoolean($parent, 'STOP', true);
            }
            if (!$result) {
                $this->SendDebug(__FUNCTION__, 'Fehler, die Rollladenfahrt konnte nicht gestoppt werden!', 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Fehler, die Rollladenfahrt konnte nicht gestoppt werden!', KL_ERROR);
            } else {
                $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt wurde gestoppt.', 0);
            }
        }
    }

    private function CheckBlindMovingDirection(int $Position): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = 0; # Down
        $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $variableType = @IPS_GetVariable($id)['VariableType'];
            switch ($variableType) {
                case 0: # Boolean
                    $actualBlindPosition = boolval(GetValue($id) * 100);
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualBlindPosition = !$actualBlindPosition;
                    }
                    break;

                case 1: #  Integer
                    $actualBlindPosition = intval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualBlindPosition = abs($actualBlindPosition - 100);
                    }
                    break;

                case 2: # Float
                    $actualLevel = floatval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualLevel = abs($actualLevel - 1);
                    }
                    $actualBlindPosition = $actualLevel * 100;
                    break;
            }
            if (isset($actualBlindPosition)) {
                if ($Position > $actualBlindPosition) {
                    $result = 1; # Up
                }
            }
        }
        if ($result == 0) {
            $movingText = 'heruntergefahren';
        } else {
            $movingText = 'hochgefahren';
        }
        $this->SendDebug(__FUNCTION__, 'Der Rollladen soll ' . $movingText . ' werden.', 0);
        return $result;
    }

    private function GetActualBlindPosition(): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $actualBlindPosition = 0;
        $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $variableType = @IPS_GetVariable($id)['VariableType'];
            switch ($variableType) {
                case 0: # Boolean
                    $actualLevel = boolval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualLevel = !$actualLevel;
                    }
                    $actualBlindPosition = intval($actualLevel * 100);
                    break;

                case 1: #Integer
                    $actualBlindPosition = intval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualBlindPosition = abs($actualBlindPosition - 100);
                    }
                    break;

                case 2: # Float
                    $actualLevel = floatval(GetValue($id));
                    if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                        $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                        $actualLevel = abs($actualLevel - 1);
                    }
                    $actualBlindPosition = intval($actualLevel * 100);
                    break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'Aktuelle Rollladenposition: ' . $actualBlindPosition . '%', 0);
        return $actualBlindPosition;
    }

    private function SetBlindTimer(int $Duration, int $DurationUnit): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($DurationUnit == 1) {
            $Duration = $Duration * 60;
        }
        $this->SetTimerInterval('StopBlindTimer', $Duration * 1000);
        $timestamp = time() + $Duration;
        $this->SetValue('BlindModeTimer', $this->GetTimeStampString($timestamp));
        $this->SendDebug(__FUNCTION__, 'Die Dauer des Timers wurde festgelegt.', 0);
    }

    private function DeactivateBlindModeTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('StopBlindTimer', 0);
        $this->SetValue('BlindModeTimer', '-');
        $this->SendDebug(__FUNCTION__, 'Der Timer wurde deaktiviert.', 0);
    }

    private function TriggerExecutionDelay(int $Delay): void
    {
        if ($Delay != 0) {
            $this->SendDebug(__FUNCTION__, 'Die Verzögerung von ' . $Delay . ' Sekunden wird ausgeführt.', 0);
            IPS_Sleep($Delay * 1000);
        }
    }
}