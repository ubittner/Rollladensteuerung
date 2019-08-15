<?php

// Declare
declare(strict_types=1);

trait RS_backupRestore
{
    //#################### Backup

    /**
     * Creates a backup of the actual configuration into a script.
     */
    public function CreateBackup()
    {
        if (IPS_GetInstance($this->InstanceID)['InstanceStatus'] == 102) {
            $name = 'Konfiguration (' . IPS_GetName($this->InstanceID) . ' #' . $this->InstanceID . ') ' . date('d.m.Y H:i:s');
            $category = $this->ReadPropertyInteger('BackupCategory');
            $config = IPS_GetConfiguration($this->InstanceID);
            // Create backup
            $content = "<?php\n// Backup " . date('d.m.Y, H:i:s') . "\n// " . $this->InstanceID . "\n$" . "config = '" . $config . "';";
            $backupScript = IPS_CreateScript(0);
            IPS_SetParent($backupScript, $category);
            IPS_SetName($backupScript, $name);
            IPS_SetHidden($backupScript, true);
            IPS_SetScriptContent($backupScript, $content);
            echo 'Die Konfiguration wurde erfolgreich gesichert!';
        }
    }

    //#################### Restore

    /**
     * Restores a configuration form selected script.
     */
    public function RestoreConfiguration()
    {
        $backupScript = $this->ReadPropertyInteger('Configuration');
        if ($backupScript != 0 && IPS_ObjectExists($backupScript)) {
            $object = IPS_GetObject($backupScript);
            if ($object['ObjectType'] == 3) {
                $content = IPS_GetScriptContent($backupScript);
                preg_match_all('/\'([^\']+)\'/', $content, $matches);
                $config = $matches[1][0];
                IPS_SetConfiguration($this->InstanceID, $config);
                if (IPS_HasChanges($this->InstanceID)) {
                    IPS_ApplyChanges($this->InstanceID);
                }
            }
            echo 'Die Konfiguration wurde erfolgreich wiederhergestellt!';
        }
    }
}

