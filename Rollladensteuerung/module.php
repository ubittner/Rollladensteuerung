<?php

/*
 * @module      Rollladensteuerung
 *
 * @prefix      RS
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @copyright   (c) 2019, 2020
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.00-6
 * @date        2020-01-06, 18:00, 1578330000
 *
 * @see         https://github.com/ubittner/Rollladensteuerung
 *
 * @guids       Library
 *              {BC55481C-37C5-4232-979F-6494F9F6893C}
 *
 *              Rollladensteuerung
 *             	{CEAE98E6-EFB4-4D0E-A7DE-3A9764F12DB6}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Rollladensteuerung extends IPSModule
{
    // Traits
    use RS_blindControl;
    use RS_doorWindowSensors;
    use RS_weeklySchedule;

    // Constants
    private const MINIMUM_DELAY_MILLISECONDS = 100;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Create profiles
        $this->CreateProfiles();

        // Register variables
        $this->RegisterVariables();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Create links
        $this->CreateLinks();

        // Set options
        $this->SetOptions();

        // Register messages
        $this->RegisterMessages();

        // Check door and window sensors
        $this->CheckDoorWindowSensors();

        // Update blind slider
        $this->UpdateBlindSlider();

        // Adjust blind level
        $this->AdjustBlindLevel();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            // $Data[0] = actual value
            // $Data[1] = difference to last value
            // $Data[2] = last value
            case VM_UPDATE:
                // Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->CheckDoorWindowSensors();
                        }
                    }
                }
                // Blind level process
                $name = 'BlindLevelProcess';
                if ($this->ValidatePropertyVariable($name)) {
                    if ($SenderID == $this->ReadPropertyInteger($name)) {
                        if ($Data[0] == 0 && $Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'BlindSlider aktualisieren', 0);
                            $this->UpdateBlindSlider();
                        }
                    }
                }
                break;

            // $Data[0] = last run
            // $Data[1] = next run
            case EM_UPDATE:
                // Weekly schedule
                if ($this->ValidateEventPlan()) {
                    $this->SetActualAction(true);
                }
                break;

        }
    }

    protected function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    public function ShowRegisteredMessages(): void
    {
        $registeredMessages = $this->GetMessageList();
        echo "Registrierte Nachrichten:\n\n";
        print_r($registeredMessages);
    }

    //#################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AutomaticMode':
                $this->ToggleAutomaticMode($Value);
                break;

            case 'BlindSlider':
                $this->SetBlindSlider($Value);
                break;

        }
    }

    //##################### Private

    private function RegisterProperties(): void
    {
        // Visibility
        $this->RegisterPropertyBoolean('EnableAutomaticMode', true);
        $this->RegisterPropertyBoolean('EnableWeeklySchedule', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableBlindSlider', true);

        // Blind actuator
        $this->RegisterPropertyInteger('BlindLevel', 0);
        $this->RegisterPropertyInteger('BlindLevelProcess', 0);
        $this->RegisterPropertyInteger('BlindActuator', 0);
        $this->RegisterPropertyInteger('BlindActuatorProperty', 0);

        // Blind positions
        $this->RegisterPropertyInteger('BlindPositionClosed', 0);
        $this->RegisterPropertyInteger('BlindPositionOpened', 100);
        $this->RegisterPropertyBoolean('UseCheckBlindPosition', false);
        $this->RegisterPropertyInteger('BlindPositionDifference', 3);

        // Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);
        $this->RegisterPropertyBoolean('UseSetBlindLevel', false);
        $this->RegisterPropertyInteger('ActionDelay', 0);

        // Door and window sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyBoolean('UseLockoutProtection', false);
        $this->RegisterPropertyInteger('LockoutPosition', 60);
    }

    private function ValidatePropertyVariable(string $Name): bool
    {
        $validate = false;
        $variable = $this->ReadPropertyInteger($Name);
        if ($variable != 0 && @IPS_ObjectExists($variable)) {
            $validate = true;
        }
        return $validate;
    }

    private function CreateProfiles(): void
    {
        // Door and window state
        $profile = 'RS.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', 'Window', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', 'Window', 0x0000FF);

        // Blind slider
        $profile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 2);
        }
        IPS_SetVariableProfileIcon($profile, 'Intensity');
        IPS_SetVariableProfileText($profile, '', '%');
        IPS_SetVariableProfileDigits($profile, 1);
        IPS_SetVariableProfileValues($profile, 0, 1, 0.05);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['DoorWindowState', 'BlindSlider.Reversed'];
        foreach ($profiles as $profile) {
            $profileName = 'RS.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Automatic mode
        $this->RegisterVariableBoolean('AutomaticMode', 'Automatik', '~Switch', 0);
        $this->EnableAction('AutomaticMode');
        IPS_SetIcon($this->GetIDForIdent('AutomaticMode'), 'Clock');

        // Door and window state
        $profile = 'RS.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Tür- / Fensterstatus', $profile, 2);

        // Blind slider
        $profile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        $this->RegisterVariableFloat('BlindSlider', 'Rollladen', $profile, 3);
        $this->EnableAction('BlindSlider');
        IPS_SetIcon($this->GetIDForIdent('BlindSlider'), 'Jalousie');
    }

    private function CreateLinks(): void
    {
        // Create link for weekly schedule
        $weeklySchedule = $this->ReadPropertyInteger('WeeklySchedule');
        $link = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($weeklySchedule != 0 && @IPS_ObjectExists($weeklySchedule)) {
            // Check for existing link
            if ($link === false) {
                $link = IPS_CreateLink();
            }
            IPS_SetParent($link, $this->InstanceID);
            IPS_SetPosition($link, 1);
            IPS_SetName($link, 'Wochenplan');
            IPS_SetIcon($link, 'Calendar');
            IPS_SetLinkTargetID($link, $weeklySchedule);
        } else {
            if ($link !== false) {
                IPS_SetHidden($link, true);
            }
        }
    }

    private function SetOptions(): void
    {
        // Automatic mode
        IPS_SetHidden($this->GetIDForIdent('AutomaticMode'), !$this->ReadPropertyBoolean('EnableAutomaticMode'));

        // Weekly schedule
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($id !== false) {
            IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableWeeklySchedule'));
        }

        // Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));

        // Blind slider
        IPS_SetHidden($this->GetIDForIdent('BlindSlider'), !$this->ReadPropertyBoolean('EnableBlindSlider'));
    }

    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
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

    private function RegisterMessages(): void
    {
        // Unregister first
        $this->UnregisterMessages();

        // Weekly schedule
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, EM_UPDATE);
        }

        // Door and window sensors
        $doorWindowSensors = $this->GetDoorWindowSensors();
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $id) {
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $this->RegisterMessage($id, VM_UPDATE);
                }
            }
        }

        // Blind level process
        $id = $this->ReadPropertyInteger('BlindLevelProcess');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
    }
}