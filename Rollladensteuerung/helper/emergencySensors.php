<?php

// Declare
declare(strict_types=1);

trait RS_emergencySensors
{
    //#################### Private

    /**
     * Triggers the emegency sensor.
     *
     * @param int $SenderID
     */
    private function TriggerEmergencySensor(int $SenderID): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $ids = json_decode($this->ReadPropertyString('EmergencySensors'));
        if (!empty($ids)) {
            foreach ($ids as $id) {
                if ($id->ID == $SenderID && $id->UseSensor) {
                    $actualValue = boolval(GetValue($SenderID));
                    $alertingValue = boolval($id->AlertingValue);
                    if ($actualValue == $alertingValue) {
                        // Blind position
                        $level = $id->BlindPosition / 100;
                        $this->SetBlindLevel($level, true);
                        // Automatic mode
                        if ($id->DisableAutomaticMode) {
                            $this->ToggleAutomaticMode(false);
                        }
                    }
                }
            }
        }
    }
}