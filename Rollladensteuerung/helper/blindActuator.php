<?php

// Declare
declare(strict_types=1);

trait RS_blindActuator
{
    /**
     * Determines the necessary variables of the blind actuator.
     */
    public function DetermineBlindActuatorVariables(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
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
                    case 1:
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
                    case 2:
                    case 3:
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
        // Homematic IP uses also Channel 4
        if ($deviceType == 2 || $deviceType == 3) {
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
     * Sets the blind level.
     *
     * @param float $Level
     *
     * @param bool $UseDelay
     * false    = no delay, move blind immediately
     * true     = move blind after delay
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    public function SetBlindLevel(float $Level, bool $UseDelay): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $Level = ' . $Level, 0);
        $result = false;
        // Check process, if still running then stop blind
        $setLevel = true;
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if (GetValue($id) == 1) {
                $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt noch nicht abgeschlossen!', 0);
                $setLevel = false;
            }
        }
        // Check property first
        if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
            $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
            // We have to change the value from 1 to 0 and vise versa
            $Level = (float) abs($Level - 1);
            $this->SendDebug(__FUNCTION__, 'Neuer Wert: ' . $Level . '.', 0);
        }
        $id = $this->ReadPropertyInteger('ActuatorControlLevel');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if ($setLevel) {
                $this->SetValue('BlindSlider', $Level);
                if ($UseDelay) {
                    $executionDelay = $this->ReadPropertyInteger('ExecutionDelay');
                    if ($executionDelay > 0) {
                        // Delay
                        $min = self::MINIMUM_DELAY_MILLISECONDS;
                        $max = $executionDelay * 1000;
                        $delay = rand($min, $max);
                        IPS_Sleep($delay);
                    }
                }
                $result = @RequestAction($id, $Level);
                if (!$result) {
                    $this->LogMessage(__FUNCTION__ . ' Fehler, Die Rollladenposition ' . $Level * 100 . '% konnte nicht angefahren werden!', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Fehler, Die Rollladenposition ' . $Level * 100 . '% konnte nicht angefahren werden!', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenposition ' . $Level * 100 . '% wird angefahren', 0);
                }
            } else {
                $parentID = IPS_GetParent($id);
                $result = HM_WriteValueBoolean($parentID, 'STOP', true);
                if (!$result) {
                    $this->LogMessage(__FUNCTION__ . ' Fehler, Die Rollladenfahrt konnte nicht gestoppt werden!', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Fehler, Die Rollladenfahrt konnte nicht gestoppt werden!', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt wurde gestoppt', 0);
                }
            }
        }
        return $result;
    }

    //#################### Private

    /**
     * Updates the blind slider.
     */
    private function UpdateBlindSlider(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if (GetValue($id) == 0) {
                $actualLevel = $this->GetActualLevel();
                $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $actualLevel * 100 . '%.', 0);
                $this->SetValue('BlindSlider', $actualLevel);
                if ($this->ReadAttributeBoolean('UpdateSetpointPosition')) {
                    $this->SetValue('SetpointPosition', $actualLevel * 100);
                }
                $this->ResetAttributes();
            }
        }
    }

    //#################### Logic

    /**
     * Gets the actual blind position.
     *
     * @return float
     */
    private function GetActualLevel(): float
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $actualLevel = (float) 0;
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $actualLevel = (float) GetValue($id);
            // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
            if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                // We have to change the value from 1 to 0 and vise versa
                $actualLevel = (float) abs($actualLevel - 1);
            }
            $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $actualLevel * 100 . '%.', 0);
        }
        return $actualLevel;
    }

    /**
     * Checks the position logic.
     *
     * @param float $Level
     *
     * @param int $MovingDirection
     * 0    = move blind down
     * 1    = move blind up
     *
     * @return float
     * Retruns the level value.
     */
    private function CheckPositions(float $Level, int $MovingDirection): float
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $Level = ' . $Level, 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $MovingDirection = ' . $MovingDirection, 0);
        $newPosition = (int) ($Level * 100);
        // Check minimum blind position difference
        $check = $this->CheckMinimumPositionDifference($Level);
        if ($check == -1) {
            // Abort
            return (float) -1;
        }
        $actualPosition = (int) $this->GetActualLevel() * 100;
        // Down
        if ($MovingDirection == 0) {
            // Check open doors and windows
            if ($this->GetValue('DoorWindowState')) {
                if ($this->ReadPropertyBoolean('LockoutProtection')) {
                    $lockoutPosition = $this->ReadPropertyInteger('LockoutPosition');
                    // New position is lower then the lockout position
                    if ($newPosition < $lockoutPosition) {
                        $this->SendDebug(__FUNCTION__, 'Tür-/Fensterprüfung: Die neue Position überschreitet die Aussperrschutzposition!', 0);
                        // Limit new position to lockout position
                        $Level = $lockoutPosition / 100;
                        $this->SendDebug(__FUNCTION__, 'Neuer Wert: ' . $Level, 0);
                        $newPosition = $lockoutPosition;
                        $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $newPosition, 0);
                    }
                }
            }
            // Only move blind down, if new position is lower then the actual position
            if ($newPosition >= $actualPosition) {
                $this->SendDebug(__FUNCTION__, 'Rollladen runterfahren: Abbruch, Die aktuelle Position ist bereits niedriger als die neue Position!', 0);
                return (float) -1;
            }
        }
        // Up
        if ($MovingDirection == 1) {
            // Only move blind up, if new position is higher then the actual position
            if ($newPosition <= $actualPosition) {
                $this->SendDebug(__FUNCTION__, 'Rollladen rauffahren: Abbruch, Die aktuelle Position ist bereits höher als die neue Position!', 0);
                return (float) -1;
            }
        }
        return $Level;
    }

    /**
     * Checks the minimum position difference of actual and new position.
     *
     * @param float $Level
     * @return float
     */
    private function CheckMinimumPositionDifference(float $Level): float
    {
        $actualPosition = (int) ($this->GetActualLevel() * 100);
        $newPosition = (int) ($Level * 100);
        // Check minimum blind position difference
        if ($this->ReadPropertyBoolean('CheckMinimumBlindPositionDifference')) {
            $this->SendDebug(__FUNCTION__, 'Der Mindest-Positionsunterschied wird geprüft', 0);
            $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $actualPosition . '%.', 0);
            $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $newPosition . '%.', 0);
            $minimumDifference = $this->ReadPropertyInteger('BlindPositionDifference');
            if ($minimumDifference > 0) {
                $actualDifference = abs($actualPosition - $newPosition);
                $this->SendDebug(__FUNCTION__, 'Positionsunterschied: ' . $actualDifference . '%.', 0);
                if ($actualDifference <= $minimumDifference) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Der Positionsunterschied ist zu gering!', 0);
                    // Abort
                    return (float) -1;
                }
            }
        }
        return $Level;
    }
}