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
        $this->SendDebug(__FUNCTION__, 'Die Methode wird mit dem Parameter $SenderID = ' . $SenderID . ' ausgeführt. (' . microtime(true) . ')', 0);
        $ids = json_decode($this->ReadPropertyString('EmergencySensors'));
        if (!empty($ids)) {
            foreach ($ids as $id) {
                if ($id->ID == $SenderID && $id->UseSensor) {
                    // Blind position
                    $level = $id->BlindPosition / 100;
                    $this->SetBlindLevel($level, false);
                    // Automatic mode
                    if ($id->DisableAutomaticMode) {
                        $this->ToggleAutomaticMode(false);
                    }
                }
            }
        }
    }
}