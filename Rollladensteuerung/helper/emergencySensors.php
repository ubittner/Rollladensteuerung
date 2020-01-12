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
        $ids = json_decode($this->ReadPropertyString('EmergencySensors'));
        if (!empty($ids)) {
            foreach ($ids as $id) {
                if ($id->ID == $SenderID && $id->UseSensor) {
                    // Open
                    $level = 1;
                    // Close
                    if ($id->ActionID == 1) {
                        $level = 0;
                    }
                    $this->SetBlindLevel($level, false);
                }
            }
        }
    }
}