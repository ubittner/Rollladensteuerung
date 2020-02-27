<?php

// Declare
declare(strict_types=1);

trait RS_weeklySchedule
{
    /**
     * Shows the actual action.
     */
    public function ShowActualAction(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        if (!$this->ValidateEventPlan()) {
            echo 'Ein Wochenplan ist nicht vorhanden oder der Wochenplan ist inaktiv!';
            return;
        }
        $actionID = $this->DetermineAction();
        $actionName = '0 = keine Aktion gefunden!';
        $event = IPS_GetEvent($this->ReadPropertyInteger('WeeklySchedule'));
        foreach ($event['ScheduleActions'] as $action) {
            if ($action['ID'] === $actionID) {
                $actionName = $actionID . ' = ' . $action['Name'];
            }
        }
        echo "Aktuelle Aktion:\n\n" . $actionName;
    }

    //#################### Private

    /**
     * Validates the event plan.
     * The event plan must be existing and active.
     *
     * @return bool
     * false    = validation failed
     * true     = validation ok
     */
    private function ValidateEventPlan(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $result = false;
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $event = IPS_GetEvent($id);
            if ($event['EventActive'] == 1) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * Triggers the action of the weekly schedule and sets the blind level.
     *
     * @return bool
     * false    = an error occurred
     * true     = true
     */
    private function TriggerActionByWeeklySchedule(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $result = false;
        // Check event plan
        if (!$this->ValidateEventPlan()) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Der Wochenplan ist nicht vorhanden oder nicht aktiv!', 0);
            return $result;
        }
        // Trigger action if automatic mode is enabled and sleep mode is disabled
        if ($this->CheckModes(__FUNCTION__)) {
            switch ($this->DetermineAction()) {
                // No actual action found
                case 0:
                    $this->SendDebug(__FUNCTION__, '0 = Keine Aktion gefunden!', 0);
                    break;

                // Close blind
                case 1:
                    $this->SendDebug(__FUNCTION__, '1 = Schließungsmodus', 0);
                    $level = $this->ReadPropertyInteger('BlindPositionClosed') / 100;
                    $direction = 0; // down
                    break;

                // Open blind
                case 2:
                    $this->SendDebug(__FUNCTION__, '2 = Öffnungsmodus', 0);
                    $level = $this->ReadPropertyInteger('BlindPositionOpened') / 100;
                    $direction = 1; // up
                    break;

                // Blind shading
                case 3:
                    $this->SendDebug(__FUNCTION__, '3 = Beschattungsmodus', 0);
                    $level = $this->ReadPropertyInteger('BlindPositionShading') / 100;
                    $direction = 0; // up
                    break;

            }
            if (isset($level) && isset($direction)) {
                $level = $this->CheckPosition($level, $direction);
                if ($level == -1) {
                    // Abort, level is not valid
                    return $result;
                }
                $this->SetValue('SetpointPosition', $level * 100);
                $result = $this->SetBlindLevel($level, true);
            }
        }
        return $result;
    }

    /**
     * Determines the action from the weekly schedule.
     *
     * @return int
     * Returns the action id.
     */
    private function DetermineAction(): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $actionID = 0;
        if ($this->ValidateEventPlan()) {
            $timestamp = time();
            $searchTime = date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
            $weekDay = date('N', $timestamp);
            $event = IPS_GetEvent($this->ReadPropertyInteger('WeeklySchedule'));
            foreach ($event['ScheduleGroups'] as $group) {
                if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                    $points = $group['Points'];
                    foreach ($points as $point) {
                        $startTime = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                        if ($startTime <= $searchTime) {
                            $actionID = $point['ActionID'];
                        }
                    }
                }
            }
        }
        return $actionID;
    }
}
