<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class RollladensteuerungValidationTest extends TestCaseSymconValidation
{
    public function testValidateRollladensteuerung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateRollladensteuerungModule(): void
    {
        $this->validateModule(__DIR__ . '/../Rollladensteuerung');
    }
}