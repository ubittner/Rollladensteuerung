<?php

// Declare
declare(strict_types=1);

trait RS_weekplanAction
{
    //#################### Get action

    /**
     * Gets the action at timestamp.
     * If there is no actual action for the timestamp, then get the last action.
     *
     * @param int $Timestamp
     * @return int
     */
    public function GetAction(int $Timestamp): int
    {
        $action = 0;
        $weeklyEventPlan = $this->ReadPropertyInteger('WeeklyEventPlan');
        if ($weeklyEventPlan != 0 && IPS_ObjectExists($weeklyEventPlan)) {
            $events = IPS_GetEvent($weeklyEventPlan);
            $eventActive = $events['EventActive'];
            if ($eventActive) {
                $this->SendDebug('GetAction', 'Timestamp: ' . $Timestamp . ' @ ' . date('d.m.Y H:i:s', $Timestamp), 0);
                $actualHour = date('H', $Timestamp);
                $actualMinute = date('i', $Timestamp);
                $weekDay = date('N', $Timestamp);
                $groups = $events['ScheduleGroups'];
                foreach ($groups as $group) {
                    if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                        foreach ($group['Points'] as $point) {
                            $hour = $point['Start']['Hour'];
                            $minute = $point['Start']['Minute'];
                            if ($hour == $actualHour && $minute == $actualMinute) {
                                $action = $point['ActionID'];
                            }
                        }
                    }
                }
                $this->SendDebug('GetAction', 'Action: ' . $action, 0);
                if ($action == 0) {
                    $action = $this->GetLastAction();
                }
            }
        }
        return $action;
    }

    //#################### Get last action

    /**
     * Gets the last action.
     *
     * @return int
     */
    public function GetLastAction(): int
    {
        $action = 0;
        $weeklyEventPlan = $this->ReadPropertyInteger('WeeklyEventPlan');
        if ($weeklyEventPlan != 0 && IPS_ObjectExists($weeklyEventPlan)) {
            $events = IPS_GetEvent($weeklyEventPlan);
            $eventActive = $events['EventActive'];
            if ($eventActive) {
                $nextRunTimestamp = $events['LastRun'];
                $this->SendDebug('GetLastAction', 'Timestamp: ' . $nextRunTimestamp . ' @ ' . date('d.m.Y H:i:s', $nextRunTimestamp), 0);
                $nextRunHour = date('H', $nextRunTimestamp);
                $nextRunMinute = date('i', $nextRunTimestamp);
                $weekDay = date('N', $nextRunTimestamp);
                $groups = $events['ScheduleGroups'];
                foreach ($groups as $group) {
                    if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                        foreach ($group['Points'] as $point) {
                            $hour = $point['Start']['Hour'];
                            $minute = $point['Start']['Minute'];
                            if ($hour == $nextRunHour && $minute == $nextRunMinute) {
                                $action = $point['ActionID'];
                            }
                        }
                    }
                }
                if ($action == 0) {
                    $nextAction = $this->GetNextAction();
                    if ($nextAction != 0) {
                        switch ($nextAction) {
                            case 1:
                                $action = 2;
                                break;
                            case 2:
                                $action = 1;
                                break;
                        }
                    }
                }
            }
        }
        $this->SendDebug('GetLastAction', 'Last action: ' . $action, 0);
        return $action;
    }

    //#################### Get next action

    /**
     * Gets the next action.
     *
     * @return int
     */
    public function GetNextAction(): int
    {
        $action = 0;
        $weeklyEventPlan = $this->ReadPropertyInteger('WeeklyEventPlan');
        if ($weeklyEventPlan != 0 && IPS_ObjectExists($weeklyEventPlan)) {
            $events = IPS_GetEvent($weeklyEventPlan);
            $eventActive = $events['EventActive'];
            if ($eventActive) {
                $nextRunTimestamp = $events['NextRun'];
                $this->SendDebug('GetNextAction', 'Timestamp: ' . $nextRunTimestamp . ' @ ' . date('d.m.Y H:i:s', $nextRunTimestamp), 0);
                $nextRunHour = date('H', $nextRunTimestamp);
                $nextRunMinute = date('i', $nextRunTimestamp);
                $weekDay = date('N', $nextRunTimestamp);
                $groups = $events['ScheduleGroups'];
                foreach ($groups as $group) {
                    if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                        foreach ($group['Points'] as $point) {
                            $hour = $point['Start']['Hour'];
                            $minute = $point['Start']['Minute'];
                            if ($hour == $nextRunHour && $minute == $nextRunMinute) {
                                $action = $point['ActionID'];
                            }
                        }
                    }
                }
            }
        }
        $this->SendDebug('GetNextAction', 'Next action: ' . $action, 0);
        return $action;
    }
}
