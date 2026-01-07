<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../classes/class/Auth.php';

class AuthTest extends TestCase
{
    private $auth;
    private $pdoMock;

    protected function setUp(): void
    {
        // Mock de PDO
        $this->pdoMock = $this->createMock(PDO::class);

        // Mock de la méthode prepare pour retourner un statement mock
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        // Injection du mock PDO dans Auth via reflection (car propriété privée)
        $this->auth = $this->getMockBuilder(Auth::class)
            ->onlyMethods(['getDb'])
            ->getMock();

        $this->auth->method('getDb')->willReturn($this->pdoMock);

        // Remplacer la propriété privée db par le mock PDO
        $reflection = new ReflectionClass(Auth::class);
        $property = $reflection->getProperty('db');
        $property->setAccessible(true);
        $property->setValue($this->auth, $this->pdoMock);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testLoginReturnsFalseWithInvalidUser()
    {
        $result = $this->auth->login('invalid@example.com', 'password');
        $this->assertFalse($result);
    }

    public function testIsLoggedInReturnsFalseWhenNoSession()
    {
        $_SESSION = [];
        $this->assertFalse($this->auth->isLoggedIn());
    }

    public function testGenerateResetTokenReturnsString()
    {
        $token = $this->auth->generateResetToken();
        $this->assertIsString($token);
        $this->assertEquals(100, strlen($token));
    }

    public function testFindUserByEmailReturnsNullWhenNotFound()
    {
        $user = $this->auth->findUserByEmail('notfound@example.com');
        $this->assertNull($user);
    }
}
?>
