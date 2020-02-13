<?php

// Declare
declare(strict_types=1);

trait RS_blindActuator
{
    /**
     * Determines the necessary variables of the thermostat.
     */
    public function DetermineBlindActuatorVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $id = $this->ReadPropertyInteger('ActuatorInstance');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            return;
        }
        $moduleID = IPS_GetInstance($id)['ModuleInfo']['ModuleID'];
        if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
            return;
        }
        $deviceType = $this->ReadPropertyInteger('DeviceType');
        $children = IPS_GetChildrenIDs($id);
        if (!empty($children)) {
            foreach ($children as $child) {
                $ident = IPS_GetObject($child)['ObjectIdent'];
                switch ($deviceType) {
                    // HM
                    case 0:
                        switch ($ident) {
                            case 'LEVEL':
                                IPS_SetProperty($this->InstanceID, 'ActuatorStateLevel', $child);
                                IPS_SetProperty($this->InstanceID, 'ActuatorControlLevel', $child);
                                break;

                            case 'WORKING':
                                IPS_SetProperty($this->InstanceID, 'ActuatorStateProcess', $child);
                                break;

                        }
                        break;

                    // HmIP
                    case 1:
                    case 2:
                        switch ($ident) {
                            case 'LEVEL':
                                IPS_SetProperty($this->InstanceID, 'ActuatorStateLevel', $child);
                                break;

                            case 'PROCESS':
                                IPS_SetProperty($this->InstanceID, 'ActuatorStateProcess', $child);
                                break;

                        }
                        break;

                }
            }
        }
        if ($deviceType != 0) {
            // Actuator control level is on channel 4
            $config = json_decode(IPS_GetConfiguration($id));
            $address = strstr($config->Address, ':', true) . ':4';
            $instances = IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
            if (!empty($instances)) {
                foreach ($instances as $instance) {
                    $config = json_decode(IPS_GetConfiguration($instance));
                    if ($config->Address == $address) {
                        $children = IPS_GetChildrenIDs($instance);
                        if (!empty($children)) {
                            foreach ($children as $child) {
                                $ident = IPS_GetObject($child)['ObjectIdent'];
                                if ($ident == 'LEVEL') {
                                    IPS_SetProperty($this->InstanceID, 'ActuatorControlLevel', $child);
                                }
                            }
                        }
                    }
                }
            }
        }
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Die Variablen wurden erfolgreich ermittelt!';
    }

    /**
     * Sets the blind slider.
     *
     * @param float $Level
     */
    public function SetBlindSlider(float $Level): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $check = $this->CheckLogic($Level);
        $this->SendDebug(__FUNCTION__, 'Resultat Logikprüfung: ' . $check, 0);
        if ($check) {
            $this->SetValue('BlindSlider', $Level);
            $this->SetBlindLevel($Level, false);
        } else {
            $this->UpdateBlindSlider();
        }
    }

    /**
     * Toggles the sleep mode.
     *
     * @param bool $State
     * false    = sleep mode off
     * true     = sleep mode on
     */
    public function ToggleSleepMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        if ($State) {
            if ($this->GetValue('AutomaticMode')) {
                $this->SetValue('SleepMode', $State);
                // Duration from hours to seconds
                $duration = $this->ReadPropertyInteger('SleepDuration') * 60 * 60;
                // Set timer interval
                $this->SetTimerInterval('DeactivateSleepMode', $duration * 1000);
                $timestamp = time() + $duration;
                $this->SetValue('SleepModeTimer', date('d.m.Y, H:i:s', ($timestamp)));
            }
        } else {
            $this->SetValue('SleepMode', $State);
            $this->SetTimerInterval('DeactivateSleepMode', 0);
            $this->SetValue('SleepModeTimer', '-');
            $this->TriggerAction(false);
        }
    }

    /**
     * Sets the blind level.
     *
     * @param float $Level
     * @param bool $CheckLogic
     * false    = don't check logic, set blind level immediately
     * true     = check logic
     *
     * @return bool
     * false    = an error occured
     * true     = ok
     */
    public function SetBlindLevel(float $Level, bool $CheckLogic): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $result = false;
        if ($CheckLogic) {
            if (!$this->CheckLogic($Level)) {
                return $result;
            }
        }
        // Check process, if still running then stop blind
        $setLevel = true;
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if (GetValue($id) == 1) {
                $setLevel = false;
            }
        }
        // Check property first
        if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
            // We have to change the value from 1 to 0 and vise versa
            $Level = (float) abs($Level - 1);
            $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $Level, 0);
        }
        $id = $this->ReadPropertyInteger('ActuatorControlLevel');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if ($setLevel) {
                $this->SetValue('BlindSlider', $Level);
                $result = @RequestAction($id, $Level);
                if (!$result) {
                    $this->LogMessage(__FUNCTION__ . ' Die Rollladenposition ' . $Level * 100 . '% konnte nicht angesteuert werden.', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenposition ' . $Level * 100 . '% konnte nicht angesteuert werden.', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenposition ' . $Level * 100 . '% wird angesteuert.', 0);
                }
            } else {
                $parentID = IPS_GetParent($id);
                $result = HM_WriteValueBoolean($parentID, 'STOP', true);
                if (!$result) {
                    $this->LogMessage(__FUNCTION__ . ' Die Rollladenfahrt konnte nicht gestoppt werden.', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenfahr konnte nicht gestoppt werden.', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt wurde gestoppt.', 0);
                }
            }
        }
        return $result;
    }

    //#################### Private

    /**
     * Checks the automatic mode whether the current action should be executed.
     */
    private function AdjustBlindLevel(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        if ($this->GetValue('AutomaticMode') && $this->ReadPropertyBoolean('AdjustBlindLevel')) {
            $this->TriggerAction(false);
        }
    }

    /**
     * Updates the blind slider.
     */
    private function UpdateBlindSlider(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $level = GetValue($id);
            // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
            if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                $level = (float) abs($level - 1);
            }
            $this->SendDebug(__FUNCTION__, 'Aktualisierung wird durchgeführt, neuer Wert: ' . $level * 100 . '%.', 0);
            $this->SetValue('BlindSlider', $level);
        }
    }

    /**
     * Checks the logic for a new blind level.
     *
     * @param float $Level
     * @return bool
     * false    = new blind level is not ok
     * true     = new blind level is ok
     */
    private function CheckLogic(float $Level): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $Level = $Level * 100;
        $setLevel = true;
        // Check lockout protection
        if ($this->ReadPropertyBoolean('LockoutProtection') && $this->GetValue('DoorWindowState') && ($Level <= $this->ReadPropertyInteger('LockoutPosition'))) {
            $setLevel = false;
            $this->SendDebug(__FUNCTION__, 'Abbruch, eine Tür oder ein Fenster ist offen.', 0);
        }
        // Check blind level position difference
        if ($this->ReadPropertyBoolean('UseCheckBlindPosition')) {
            $id = $this->ReadPropertyInteger('ActuatorStateLevel');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $blindLevel = GetValue($id) * 100;
                // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
                if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                    $blindLevel = (float) abs($blindLevel - 1) * 100;
                }
                $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $blindLevel . '%', 0);
                $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $Level . '%', 0);
                $difference = $this->ReadPropertyInteger('BlindPositionDifference');
                if ($difference > 0) {
                    $actualDifference = abs($blindLevel - $Level);
                    $this->SendDebug(__FUNCTION__, 'Positionsunterschied: ' . $actualDifference . '%', 0);
                    if ($actualDifference <= $difference) {
                        $this->SendDebug(__FUNCTION__, 'Der Positionsunterschied ist zu gering.', 0);
                        $setLevel = false;
                    }
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'Resultat Logikprüfung: ' . json_encode($setLevel), 0);
        return $setLevel;
    }
}