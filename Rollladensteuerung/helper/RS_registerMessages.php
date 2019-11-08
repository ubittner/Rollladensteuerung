<?php

// Declare
declare(strict_types=1);

trait RS_registerMessages
{
    /**
     * Unregisters messages.
     */
    private function UnregisterMessages()
    {
        $registeredMessages = $this->GetMessageList();
        foreach ($registeredMessages as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
                if ($messageType == EM_UPDATE) {
                    $this->UnregisterMessage($id, EM_UPDATE);
                }
            }
        }
    }

    /**
     * Registers the weekly event plan.
     */
    private function RegisterWeeklyEventPlan()
    {
        $weeklyEventPlan = $this->ReadPropertyInteger('WeeklyEventPlan');
        if ($weeklyEventPlan != 0 && IPS_ObjectExists($weeklyEventPlan)) {
            $this->RegisterMessage($weeklyEventPlan, EM_UPDATE);
        }
    }

    /**
     * Registers the blind state variable.
     */
    private function RegisterBlindLevelState()
    {
        $blindLevelState = $this->ReadPropertyInteger('BlindLevelState');
        if ($blindLevelState != 0 && IPS_ObjectExists($blindLevelState)) {
            $this->RegisterMessage($blindLevelState, VM_UPDATE);
        }
    }
}