<?php

// Declare
declare(strict_types=1);

trait RS_doorWindowSensors
{
    //#################### Private

    /**
     * Gets the activated door and window sensors.
     *
     * @return array
     * Returns an array of activated door and window sensors.
     */
    private function GetDoorWindowSensors(): array
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $sensors = [];
        $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'));
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $doorWindowSensor) {
                $id = $doorWindowSensor->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($doorWindowSensor->UseSensor) {
                        array_push($sensors, $id);
                    }
                }
            }
        }
        return $sensors;
    }

    /**
     * Checks the state of the activated door and window sensors.
     *
     * @return bool
     * false    = all doors and windows are closed
     * true     = at least one door or window is opened
     */
    private function CheckDoorWindowSensors(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $state = false;
        $sensors = $this->GetDoorWindowSensors();
        if (!empty($sensors)) {
            foreach ($sensors as $sensor) {
                if (boolval(GetValue($sensor))) {
                    $state = true;
                }
            }
        }
        $this->SetValue('DoorWindowState', $state);
        return $state;
    }

    /**
     * Triggers an action by a door or window sensor.
     *
     * @param bool $State
     * false    = closed
     * true     = opened
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    private function TriggerActionByDoorWindowSensor(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $stateName = 'geschlossen';
        if ($State) {
            $stateName = 'geöffnet';
        }
        $this->SendDebug(__FUNCTION__, 'Parameter $State = ' . $State . ' = ' . $stateName, 0);
        $result = false;
        // Set blind level if automatic mode is enabled and sleep mode is disabled
        if ($this->CheckModes(__FUNCTION__)) {
            $doorWindowState = $this->GetValue('DoorWindowState');
            // Opened
            if ($State && $doorWindowState) {
                if ($this->ReadPropertyBoolean('OpenBlind')) {
                    $actualPosition = $this->GetActualPosition();
                    $newPosition = $this->ReadPropertyInteger('OpenBlindPosition');
                    if ($actualPosition < $newPosition) {
                        $result = $this->SetBlindLevel($newPosition / 100, false);
                    }
                }
            }
            // Closed
            if (!$State && !$doorWindowState) {
                if ($this->ReadPropertyBoolean('CloseBlind')) {
                    $actualPosition = $this->GetActualPosition();
                    $newPosition = $this->GetValue('SetpointPosition');
                    if ($actualPosition > $newPosition) {
                        $result = $this->SetBlindLevel($newPosition / 100, false);
                    }
                }
            }
        }
        return $result;
    }
}