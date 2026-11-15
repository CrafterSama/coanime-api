<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DeployMigrate;
use PHPUnit\Framework\TestCase;

class DeployMigrateTest extends TestCase
{
    public function test_detects_already_applied_errors(): void
    {
        $this->assertTrue(DeployMigrate::isAlreadyApplied('SQLSTATE[42S01]: Base table or view already exists: 1050 Table \'posts\' already exists'));
        $this->assertTrue(DeployMigrate::isAlreadyApplied('SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name \'mal_news_id\''));
        $this->assertTrue(DeployMigrate::isAlreadyApplied('Duplicate key name \'posts_mal_news_id_unique\''));
        $this->assertTrue(DeployMigrate::isAlreadyApplied('Can\'t DROP \'foo\'; check that column/key exists'));
        $this->assertFalse(DeployMigrate::isAlreadyApplied('SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect datetime value'));
        $this->assertFalse(DeployMigrate::isAlreadyApplied('Connection refused'));
    }
}
