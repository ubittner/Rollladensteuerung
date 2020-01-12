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
    // Helper
    use RS_astroMode;
    use RS_blindActuator;
    use RS_doorWindowSensors;
    use RS_emergencySensors;
    use RS_weeklySchedule;

    // Constants
    private const MINIMUM_DELAY_MILLISECONDS = 100;
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

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

        // Register timers
        $this->RegisterTimers();
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

        // Validate configuration
        $this->ValidateConfiguration();

        // Disable timers
        $this->DisableTimers();

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
                // Sunrise
                $id = $this->ReadPropertyInteger('Sunrise');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $timeStamp = date('d.m.Y, H:i:s');
                            $this->LogMessage('Variable Sonnenaufgang hat sich geändert! ' . $timeStamp, 10201);
                            $this->TriggerSunrise();
                        }
                    }
                }
                // Sunset
                $id = $this->ReadPropertyInteger('Sunset');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $timeStamp = date('d.m.Y, H:i:s');
                            $this->LogMessage('Variable Sonnenuntergang hat sich geändert! ' . $timeStamp, 10201);
                            $this->TriggerSunset();
                        }
                    }
                }
                // Actuator state level
                $id = $this->ReadPropertyInteger('ActuatorStateLevel');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'BlindSlider aktualisieren', 0);
                            $this->UpdateBlindSlider();
                        }
                    }
                }
                // Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->CheckDoorWindowSensors();
                        }
                    }
                }
                // Emergency sensors
                $id = json_decode($this->ReadPropertyString('EmergencySensors'), true);
                if (!empty($id)) {
                    if (array_search($SenderID, array_column($id, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->TriggerEmergencySensor($SenderID);
                        }
                    }
                }
                break;

            // $Data[0] = last run
            // $Data[1] = next run
            case EM_UPDATE:
                // Weekly schedule
                if ($this->ValidateEventPlan()) {
                    $this->TriggerAction(true);
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
        $kernelMessages = [];
        $eventMessages = [];
        $variableMessages = [];
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == IPS_KERNELSTARTED) {
                    $kernelMessages[] = ['id' => $id];
                }
                if ($messageType == EM_UPDATE) {
                    $eventMessages[] = ['id' => $id, 'name' => IPS_GetName($id)];
                }
                if ($messageType == VM_UPDATE) {
                    $parent = IPS_GetParent($id);
                    $parentName = '';
                    if ($parent != 0) {
                        $parentName = IPS_GetName($parent);
                    }
                    $variableMessages[] = ['id' => $id, 'name' => IPS_GetName($id), 'parentName' => $parentName];
                }
            }
        }
        echo "IPS_KERNELSTARTED:\n\n";
        foreach ($kernelMessages as $kernelMessage) {
            echo $kernelMessage['id'] . "\n\n";
        }
        echo "\n\nEM_UPDATE:\n\n";
        foreach ($eventMessages as $eventMessage) {
            echo $eventMessage['id'] . "\n";
            echo $eventMessage['name'] . "\n\n";
        }
        echo "\n\nVM_UPDATE:\n\n";
        foreach ($variableMessages as $variableMessage) {
            echo $variableMessage['id'] . "\n";
            echo $variableMessage['name'] . "\n";
            echo $variableMessage['parentName'] . "\n\n";
        }
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

            case 'SleepMode':
                $this->ToggleSleepMode($Value);
                break;

        }
    }

    //##################### Private

    private function RegisterProperties(): void
    {
        // Visibility
        $this->RegisterPropertyBoolean('EnableAutomaticMode', true);
        $this->RegisterPropertyBoolean('EnableWeeklySchedule', true);
        $this->RegisterPropertyBoolean('EnableSunset', true);
        $this->RegisterPropertyBoolean('EnableSunrise', true);
        $this->RegisterPropertyBoolean('EnableBlindSlider', true);
        $this->RegisterPropertyBoolean('EnableSleepMode', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);

        // Blind actuator
        $this->RegisterPropertyInteger('DeviceType', 0);
        $this->RegisterPropertyInteger('ActuatorInstance', 0);
        $this->RegisterPropertyInteger('ActuatorStateLevel', 0);
        $this->RegisterPropertyInteger('ActuatorStateProcess', 0);
        $this->RegisterPropertyInteger('ActuatorControlLevel', 0);
        $this->RegisterPropertyInteger('ActuatorProperty', 0);

        // Blind positions
        $this->RegisterPropertyInteger('BlindPositionClosed', 0);
        $this->RegisterPropertyInteger('BlindPositionOpened', 100);
        $this->RegisterPropertyInteger('BlindPositionShading', 50);
        $this->RegisterPropertyBoolean('UseCheckBlindPosition', false);
        $this->RegisterPropertyInteger('BlindPositionDifference', 3);

        // Sleep duration
        $this->RegisterPropertyInteger('SleepDuration', 12);

        // Astro
        $this->RegisterPropertyInteger('Sunrise', 0);
        $this->RegisterPropertyInteger('Sunset', 0);

        // Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);
        $this->RegisterPropertyBoolean('AdjustBlindLevel', false);

        // Execution delay
        $this->RegisterPropertyInteger('ExecutionDelay', 0);

        // Door and window sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyBoolean('LockoutProtection', false);
        $this->RegisterPropertyInteger('LockoutPosition', 60);

        // Emergency sensors
        $this->RegisterPropertyString('EmergencySensors', '[]');
    }

    private function CreateProfiles(): void
    {
        // Automatic mode
        $profile = 'RS.' . $this->InstanceID . '.AutomaticMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Execute', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Clock', 0x00FF00);

        // Blind slider
        $profile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 2);
        }
        IPS_SetVariableProfileIcon($profile, 'Intensity');
        IPS_SetVariableProfileText($profile, '', '%');
        IPS_SetVariableProfileDigits($profile, 1);
        IPS_SetVariableProfileValues($profile, 0, 1, 0.05);

        // Sleep mode
        $profile = 'RS.' . $this->InstanceID . '.SleepMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Sleep', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Sleep', 0x0000FF);

        // Door and window state
        $profile = 'RS.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', 'Window', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', 'Window', 0x0000FF);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['BlindSlider.Reversed', 'SleepMode', 'DoorWindowState'];
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
        $profile = 'RS.' . $this->InstanceID . '.AutomaticMode';
        $this->RegisterVariableBoolean('AutomaticMode', 'Automatik', $profile, 0);
        $this->EnableAction('AutomaticMode');

        // Blind slider
        $profile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        $this->RegisterVariableFloat('BlindSlider', 'Rollladen', $profile, 4);
        $this->EnableAction('BlindSlider');
        IPS_SetIcon($this->GetIDForIdent('BlindSlider'), 'Jalousie');

        // Sleep mode
        $profile = 'RS.' . $this->InstanceID . '.SleepMode';
        $this->RegisterVariableBoolean('SleepMode', 'Ruhe-Modus', $profile, 5);
        $this->EnableAction('SleepMode');

        // Door and window state
        $profile = 'RS.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Tür- / Fensterstatus', $profile, 6);
    }

    private function CreateLinks(): void
    {
        // Weekly schedule
        $targetID = $this->ReadPropertyInteger('WeeklySchedule');
        $linkID = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 1);
            IPS_SetName($linkID, 'Wochenplan');
            IPS_SetIcon($linkID, 'Calendar');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Sunrise
        $targetID = $this->ReadPropertyInteger('Sunrise');
        $linkID = @IPS_GetLinkIDByName('Sonnenaufgang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 1);
            IPS_SetName($linkID, 'Sonnenaufgang');
            IPS_SetIcon($linkID, 'Sun');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Sunset
        $targetID = $this->ReadPropertyInteger('Sunset');
        $linkID = @IPS_GetLinkIDByName('Sonnenuntergang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 1);
            IPS_SetName($linkID, 'Sonnenuntergang');
            IPS_SetIcon($linkID, 'Moon');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
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
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableWeeklySchedule') && $this->GetValue('AutomaticMode')) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }

        // Sunrise
        $id = @IPS_GetLinkIDByName('Sonnenaufgang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableSunrise') && $this->GetValue('AutomaticMode')) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }

        // Sunset
        $id = @IPS_GetLinkIDByName('Sonnenuntergang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableSunset') && $this->GetValue('AutomaticMode')) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }

        // Blind slider
        IPS_SetHidden($this->GetIDForIdent('BlindSlider'), !$this->ReadPropertyBoolean('EnableBlindSlider'));

        // Sleep Mode
        IPS_SetHidden($this->GetIDForIdent('SleepMode'), !$this->ReadPropertyBoolean('EnableSleepMode'));

        // Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('DeactivateSleepMode', 0, 'RS_ToggleSleepMode(' . $this->InstanceID . ', false);');
    }

    private function DisableTimers(): void
    {
        $this->SetTimerInterval('DeactivateSleepMode', 0);
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
        // Sunset
        $id = $this->ReadPropertyInteger('Sunset');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Sunrise
        $id = $this->ReadPropertyInteger('Sunrise');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Weekly schedule
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, EM_UPDATE);
        }
        // Actuator state level
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Actuator state process
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
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
    }

    private function ValidateConfiguration(): void
    {
        $state = 102;
        $deviceType = $this->ReadPropertyInteger('DeviceType');
        // Blind actuator
        $id = $this->ReadPropertyInteger('ActuatorInstance');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Instanz Rollladenaktor ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                    $this->LogMessage('Konfiguration: Instanz Rollladenaktor GUID ungültig!', KL_ERROR);
                    $state = 200;
                } else {
                    // Check channel
                    $config = json_decode(IPS_GetConfiguration($id));
                    $address = strstr($config->Address, ':', false);
                    switch ($deviceType) {
                        // HM
                        case 1:
                            if ($address != ':1') {
                                $this->LogMessage('Konfiguration: Instanz Rollladenaktor Kanal ungültig!', KL_ERROR);
                                $state = 200;
                            }
                            break;

                        // HmIP
                        case 2:
                        case 3:
                            if ($address != ':3') {
                                $this->LogMessage('Konfiguration: Instanz Rollladenaktor Kanal ungültig!', KL_ERROR);
                                $state = 200;
                            }
                            break;

                    }
                }
            }
        }
        // Actuator state level
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Variable Rollladenposition ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $parent = IPS_GetParent($id);
                if ($parent == 0) {
                    $this->LogMessage('Konfiguration: Variable Rollladenposition, keine übergeordnete ID gefunden!', KL_ERROR);
                    $state = 200;
                } else {
                    $instance = IPS_GetInstance($parent);
                    $moduleID = $instance['ModuleInfo']['ModuleID'];
                    if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                        $this->LogMessage('Konfiguration: Variable Rollladenposition GUID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check channel
                        $config = json_decode(IPS_GetConfiguration($parent));
                        $address = strstr($config->Address, ':', false);
                        switch ($deviceType) {
                            // HM
                            case 1:
                                if ($address != ':1') {
                                    $this->LogMessage('Konfiguration: Variable Rollladenposition Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                            // HmIP
                            case 2:
                            case 3:
                                if ($address != ':3') {
                                    $this->LogMessage('Konfiguration: Variable Rollladenposition Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                        }
                    }
                }
                $ident = IPS_GetObject($id)['ObjectIdent'];
                if ($ident != 'LEVEL') {
                    $this->LogMessage('Konfiguration: Variable Rollladenposition IDENT ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Actuator state process
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Variable Aktivitätszustand ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $parent = IPS_GetParent($id);
                if ($parent == 0) {
                    $this->LogMessage('Konfiguration: Variable Aktivitätszustand, keine übergeordnete ID gefunden!', KL_ERROR);
                    $state = 200;
                } else {
                    $instance = IPS_GetInstance($parent);
                    $moduleID = $instance['ModuleInfo']['ModuleID'];
                    if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                        $this->LogMessage('Konfiguration: Variable Aktivitätszustand GUID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check channel
                        $config = json_decode(IPS_GetConfiguration($parent));
                        $address = strstr($config->Address, ':', false);
                        switch ($deviceType) {
                            // HM
                            case 1:
                                if ($address != ':1') {
                                    $this->LogMessage('Konfiguration: Variable Aktivitätszustand Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                            // HmIP
                            case 2:
                            case 3:
                                if ($address != ':3') {
                                    $this->LogMessage('Konfiguration: Variable Aktivitätszustand Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                        }
                    }
                }
                $ident = IPS_GetObject($id)['ObjectIdent'];
                switch ($deviceType) {
                    // HM
                    case 1:
                        if ($ident != 'WORKING') {
                            $this->LogMessage('Konfiguration: Variable Aktivitätszustand IDENT ungültig!', KL_ERROR);
                            $state = 200;
                        }
                        break;

                    // HmIP
                    case 2:
                    case 3:
                        if ($ident != 'PROCESS') {
                            $this->LogMessage('Konfiguration: Variable Aktivitätszustand IDENT ungültig!', KL_ERROR);
                            $state = 200;
                        }
                        break;
                }
            }
        }
        // Actuator control level
        $id = $this->ReadPropertyInteger('ActuatorControlLevel');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Variable Rollladensteuerung ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $parent = IPS_GetParent($id);
                if ($parent == 0) {
                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung, keine übergeordnete ID gefunden!', KL_ERROR);
                    $state = 200;
                } else {
                    $instance = IPS_GetInstance($parent);
                    $moduleID = $instance['ModuleInfo']['ModuleID'];
                    if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                        $this->LogMessage('Konfiguration: Variable Rollladensteuerung GUID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check channel
                        $config = json_decode(IPS_GetConfiguration($parent));
                        $address = strstr($config->Address, ':', false);
                        switch ($deviceType) {
                            // HM
                            case 1:
                                if ($address != ':1') {
                                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                            // HmIP
                            case 2:
                            case 3:
                                if ($address != ':4') {
                                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                        }
                    }
                }
                $ident = IPS_GetObject($id)['ObjectIdent'];
                if ($ident != 'LEVEL') {
                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung IDENT ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Set state
        $this->SetStatus($state);
    }
}