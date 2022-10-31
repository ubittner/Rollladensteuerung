<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class RollladensteuerungValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Rollladensteuerung(): void
    {
        $this->validateModule(__DIR__ . '/../Rollladensteuerung');
    }
}