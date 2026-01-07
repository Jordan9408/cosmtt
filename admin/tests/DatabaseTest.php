<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../classes/class/Database.php';

class DatabaseTest extends TestCase
{
    public function testGetInstanceReturnsPDO()
    {
        $pdo = Database::getInstance();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testGetInstanceIsSingleton()
    {
        $pdo1 = Database::getInstance();
        $pdo2 = Database::getInstance();
        $this->assertSame($pdo1, $pdo2);
    }
}
?>
