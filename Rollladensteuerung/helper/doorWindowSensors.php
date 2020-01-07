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
     */
    private function CheckDoorWindowSensors(): bool
    {
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
}