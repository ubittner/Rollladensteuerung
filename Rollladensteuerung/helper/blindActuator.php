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
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
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
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $Level = ' . $Level, 0);
        $result = false;
        // Check process, if still running then stop blind
        $setLevel = true;
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if (GetValue($id) == 1) {
                $this->SendDebug(__FUNCTION__, 'Rollladenfahrt noch nicht abgeschlossen!', 0);
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
                    $this->LogMessage(__FUNCTION__ . ' Fehler, Rollladenposition ' . $Level * 100 . '% konnte nicht angefahren werden!', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Fehler, Rollladenposition ' . $Level * 100 . '% konnte nicht angefahren werden!', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Rollladenposition ' . $Level * 100 . '% wird angefahren', 0);
                }
            } else {
                $parentID = IPS_GetParent($id);
                $result = HM_WriteValueBoolean($parentID, 'STOP', true);
                if (!$result) {
                    $this->LogMessage(__FUNCTION__ . ' Fehler, Rollladenfahrt konnte nicht gestoppt werden!', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Fehler, Rollladenfahrt konnte nicht gestoppt werden!', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Rollladenfahrt wurde gestoppt', 0);
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
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $actualPosition = $this->GetActualPosition();
        $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $actualPosition . '%.', 0);
        $this->SetValue('BlindSlider', $actualPosition / 100);
    }

    //#################### Logic

    /**
     * Gets the actual blind position.
     *
     * @return int
     */
    private function GetActualPosition(): int
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $actualPosition = 0;
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $actualLevel = (float) GetValue($id);
            // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
            if ($this->ReadPropertyInteger('ActuatorProperty') == 2) {
                $this->SendDebug(__FUNCTION__, 'Logik = 2 = geschlossen bei 100%', 0);
                // We have to change the value from 1 to 0 and vise versa
                $actualLevel = (float) abs($actualLevel - 1);
            }
            $actualPosition = $actualLevel * 100;
            $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $actualPosition . '%.', 0);
        }
        return (int) $actualPosition;
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
    private function CheckPosition(float $Level, int $MovingDirection): float
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $Level = ' . $Level, 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $MovingDirection = ' . $MovingDirection, 0);
        $newLevel = (float) -1;
        $actualPosition = $this->GetActualPosition();
        $newPosition = $Level * 100;
        // Check minimum blind position difference
        if ($this->ReadPropertyBoolean('CheckMinimumBlindPositionDifference')) {
            $this->SendDebug(__FUNCTION__, 'Mindest-Positionsunterschied wird geprüft', 0);
            $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $newPosition . '%.', 0);
            $minimumDifference = $this->ReadPropertyInteger('BlindPositionDifference');
            if ($minimumDifference > 0) {
                $actualDifference = abs($actualPosition - $newPosition);
                $this->SendDebug(__FUNCTION__, 'Positionsunterschied: ' . $actualDifference . '%.', 0);
                if ($actualDifference <= $minimumDifference) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Positionsunterschied ist zu gering!', 0);
                    // Abort
                    return $newLevel;
                }
            }
        }
        // Down
        if ($MovingDirection == 0) {
            // Check open doors and windows
            if ($this->GetValue('DoorWindowState')) {
                if ($this->ReadPropertyBoolean('LockoutProtection')) {
                    $lockoutPosition = $this->ReadPropertyInteger('LockoutPosition');
                    if ($newPosition < $lockoutPosition) {
                        // Abort
                        $this->SendDebug(__FUNCTION__, 'Abbruch Tür-/Fensterprüfung, Aussperrschutzposition überschritten!', 0);
                        return $newLevel;
                    }
                    if ($newPosition >= $lockoutPosition) {
                        if ($actualPosition < $newPosition) {
                            // Abort
                            $this->SendDebug(__FUNCTION__, 'Abbruch Tür-/Fensterprüfung, Aktuelle Position ausserhalb Aussperrschutzposition!', 0);
                            return $newLevel;
                        }
                    }
                }
            }
            // Only move blind down, if new position is lower then the actual position
            if ($newPosition >= $actualPosition) {
                $this->SendDebug(__FUNCTION__, 'Abbruch Rollladen runterfahren, Aktuelle Rollladenposition ist niedriger!', 0);
                return $newLevel;
            }
        }
        // Up
        if ($MovingDirection == 1) {
            // Only move blind up, if new position is higher then the actual position
            if ($newPosition <= $actualPosition) {
                $this->SendDebug(__FUNCTION__, 'Abbruch Rollladen rauffahren, Aktuelle Rollladenposition ist höher!', 0);
                return $newLevel;
            }
        }
        return $Level;
    }
}