<?php

/*
 * @module      Rollladensteuerung
 *
 * @prefix      RS
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @copyright   (c) 2019
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.00-2
 * @date        2019-08-14, 18:00
 *
 * @see         https://github.com/ubittner/Rollladensteuerung
 *
 * @guids       Library
 *              {BC55481C-37C5-4232-979F-6494F9F6893C}
 *
 *              Rollladensteuerung
 *             	{CEAE98E6-EFB4-4D0E-A7DE-3A9764F12DB6}
 *
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/RS_autoload.php';

class Rollladensteuerung extends IPSModule
{
    // Helper
    use RS_backupRestore;
    use RS_blindControl;
    use RS_registerMessages;
    use RS_weekplanAction;

    /**
     * Creates properties and variables.
     *
     * @return bool|void
     */
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        //#################### Register properties

        // Automatic
        $this->RegisterPropertyInteger('WeeklyEventPlan', 0);
        $this->RegisterPropertyBoolean('UseSetBlindLevel', false);
        $this->RegisterPropertyInteger('ActionDelay', 0);
        $this->RegisterPropertyInteger('BlindPositionClosed', 0);
        $this->RegisterPropertyInteger('BlindPositionOpened', 100);
        $this->RegisterPropertyBoolean('UseLockoutProtection', false);
        $this->RegisterPropertyInteger('LockoutProtectionSensor', 0);

        // Blind
        $this->RegisterPropertyInteger('BlindLevelState', 0);
        $this->RegisterPropertyInteger('BlindLevelProcess', 0);
        $this->RegisterPropertyInteger('BlindActuator', 0);
        $this->RegisterPropertyInteger('BlindActuatorProperty', 0);

        // Backup / Restore
        $this->RegisterPropertyInteger('BackupCategory', 0);
        $this->RegisterPropertyInteger('Configuration', 0);

        //#################### Register profiles

        // Blind slider
        $blindSliderProfile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        if (!IPS_VariableProfileExists($blindSliderProfile)) {
            IPS_CreateVariableProfile($blindSliderProfile, 2);
        }
        IPS_SetVariableProfileIcon($blindSliderProfile,  'Intensity');
        IPS_SetVariableProfileText($blindSliderProfile, '', '%');
        IPS_SetVariableProfileDigits($blindSliderProfile, 1);
        IPS_SetVariableProfileValues($blindSliderProfile, 0, 1, 0.05);

        //#################### Register variables

        $this->RegisterVariableBoolean('AutomaticMode', 'Automatik', '~Switch', 0);
        $this->EnableAction('AutomaticMode');
        IPS_SetIcon($this->GetIDForIdent('AutomaticMode'), 'Clock');

        $this->RegisterVariableFloat('BlindSlider', 'Rollladen', $blindSliderProfile, 1);
        $this->EnableAction('BlindSlider');
        IPS_SetIcon($this->GetIDForIdent('BlindSlider'), 'Jalousie');

        $this->RegisterVariableString('NextAction', 'Nächster Schaltvorgang', '~TextBox', 2);
        IPS_SetIcon($this->GetIDForIdent('NextAction'), 'Power');

        //#################### Register attributes

        $this->RegisterAttributeInteger('NextAction', 0);

        //#################### Register timer

        $this->RegisterTimer('ControlBlind', 0, 'RS_ControlBlind($_IPS[\'TARGET\']);');

    }

    /**
     * Does destroy the instance.
     *
     * @return bool|void
     */
    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    /**
     * Applies the changes.
     *
     * @return bool|void
     */
    public function ApplyChanges()
    {
        // Register messages
        // Base
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Unregister messages
        $this->UnregisterMessages();

        // Register weekly event plan
        $this->RegisterWeeklyEventPlan();

        // Register blind level state
        $this->RegisterBlindLevelState();

        // Update blind level
        $this->UpdateBlindLevel();

        // Check action
        $this->CheckAction();
    }

    /**
     * Checks the message sink.
     *
     * @param $TimeStamp
     * @param $SenderID
     * @param $Message
     * @param $Data
     * @return bool|void
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'SenderID: ' . $SenderID . ', Message: ' . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case VM_UPDATE:
                $this->UpdateBlindLevel();
                break;
            case EM_UPDATE:
                $this->CheckAction();
                break;
            default:
                break;
        }
    }

    /**
     * Applies changes when the kernel is ready.
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    //#################### Request action

    /**
     * Requests the action from WebFront.
     *
     * @param $Ident
     * @param $Value
     * @return bool|void
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AutomaticMode':
                $this->ToggleAutomaticMode($Value);
                break;
            case 'BlindSlider':
                $this->SetBlindLevel($Value);
                break;
            default:
                break;
        }
    }

    /**
     * Toggles the automatic mode.
     *
     * @param bool $State
     */
    public function ToggleAutomaticMode(bool $State)
    {
        $this->SetValue('AutomaticMode', $State);
        $this->CheckAction();
    }

    /**
     * Deletes the profiles.
     */
    private function DeleteProfiles()
    {
        $profiles = ['BlindSlider', 'BlindSlider.Reversed'];
        foreach ($profiles as $profile) {
            $profileName = 'RS.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }
}