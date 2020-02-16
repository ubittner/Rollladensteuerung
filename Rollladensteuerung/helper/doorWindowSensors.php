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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
     * false    = closed
     * true     = opened
     *
     * @throws Exception
     */
    private function CheckDoorWindowSensors(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
     * Opens the blind if the door or window is opened.
     *
     * @param bool $State
     * false    = closed
     * true     = opened
     */
    private function OpenBlindByDoorWindowSensor(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Only if automatic mode is turned on, opening mode is activated and door or window is opened
        if ($this->ReadPropertyBoolean('OpenBlind') && $State && $this->GetValue('AutomaticMode')) {
            $actualPosition = $this->GetValue('BlindSlider') * 100;
            $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $actualPosition, 0);
            $openPosition = $this->ReadPropertyInteger('OpenBlindPosition');
            $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $actualPosition, 0);
            if ($actualPosition < $openPosition) {
                $level = $openPosition / 100;
                $this->SendDebug(__FUNCTION__, 'Position wird angefahren. Wert: ' . $level, 0);
                $this->SetBlindLevel($level, false);
            }
        }
    }
}