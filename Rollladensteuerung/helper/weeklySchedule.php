<?php

// Declare
declare(strict_types=1);

trait RS_weeklySchedule
{
    /**
     * Toggles the automatic mode.
     *
     * @param bool $State
     */
    public function ToggleAutomaticMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $this->SetValue('AutomaticMode', $State);
        $this->AdjustBlindLevel();
        // Weekly schedule visibility
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableWeeklySchedule') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
        // Sunset visibility
        $id = @IPS_GetLinkIDByName('Sonnenuntergang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableSunset') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
        // Sunrise visibility
        $id = @IPS_GetLinkIDByName('Sonnenaufgang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableSunrise') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
    }

    /**
     * Shows the actual action.
     */
    public function ShowActualAction(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
     * @param bool $CheckExecutionDelay
     * false    = don't check execution delay
     * true     = check execution delay
     */
    private function TriggerAction(bool $CheckExecutionDelay): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        // Check event plan
        if (!$this->ValidateEventPlan()) {
            return;
        }
        // Trigger action only in automatic mode
        if ($this->GetValue('AutomaticMode')) {
            switch ($this->DetermineAction()) {
                // No actual action found
                case 0:
                    $this->SendDebug(__FUNCTION__, '0 = Keine Aktion gefunden!', 0);
                    break;

                // Close blind
                case 1:
                    $this->SendDebug(__FUNCTION__, '1 = Schließungsmodus', 0);
                    $level = $this->ReadPropertyInteger('BlindPositionClosed') / 100;
                    break;

                // Open blind
                case 2:
                    $this->SendDebug(__FUNCTION__, '2 = Öffnungsmodus', 0);
                    $level = $this->ReadPropertyInteger('BlindPositionOpened') / 100;
                    break;

                // Blind shading
                case 3:
                    $this->SendDebug(__FUNCTION__, '3 = Beschattungsmodus', 0);
                    $level = $this->ReadPropertyInteger('BlindPositionShading') / 100;
                    break;

            }
            if (isset($level)) {
                if ($this->CheckLogic($level)) {
                    $executionDelay = $this->ReadPropertyInteger('ExecutionDelay');
                    if ($CheckExecutionDelay) {
                        if ($executionDelay > 0) {
                            // Delay
                            $min = self::MINIMUM_DELAY_MILLISECONDS;
                            $max = $executionDelay * 1000;
                            $delay = rand($min, $max);
                            IPS_Sleep($delay);
                        }
                    }
                    // Set blind level if sleep mode is disabled
                    if (!$this->GetValue('SleepMode')) {
                        $this->SetBlindLevel($level, false);
                    }
                }
            }
        }
    }

    /**
     * Determines the action from the weekly schedule.
     *
     * @return int
     * Returns the action id.
     */
    private function DetermineAction(): int
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
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
