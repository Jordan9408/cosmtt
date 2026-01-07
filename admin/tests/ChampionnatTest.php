<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les classes du championnat.
 * Couvre validation, calcul de classement, etc.
 */
class ChampionnatTest extends TestCase
{
    private PDO $conn;
    private JourneeValidator $validator;
    private ChampionnatManager $championnatManager;

    protected function setUp(): void
    {
        // Mock ou connexion test DB si nécessaire
        // Pour l'exemple, on suppose une connexion mock
        $this->conn = new PDO('sqlite::memory:'); // Exemple pour tests
        $this->validator = new JourneeValidator();
        $this->championnatManager = new ChampionnatManager($this->conn);
    }

    public function testValiderJourneeValide()
    {
        $postData = [
            'journée' => 'Journée 1',
            'dateDebut' => '2023-10-01',
            'dateFin' => '2023-10-07'
        ];
        $errors = $this->validator->validerJournee($postData);
        $this->assertEmpty($errors);
    }

    public function testValiderJourneeInvalide()
    {
        $postData = [
            'journée' => '',
            'dateDebut' => '2023-10-07',
            'dateFin' => '2023-10-01'
        ];
        $errors = $this->validator->validerJournee($postData);
        $this->assertNotEmpty($errors);
        $this->assertContains("Le numéro de la journée est obligatoire.", $errors);
        $this->assertContains("La date de début doit être antérieure à la date de fin.", $errors);
    }

    public function testValiderRencontresValide()
    {
        $postData = [
            'equipe1' => ['Equipe A', 'Equipe B'],
            'score1' => ['10', '12'],
            'score2' => ['8', '12'],
            'equipe2' => ['Equipe C', 'Equipe D']
        ];
        $errors = $this->validator->validerRencontres($postData);
        $this->assertEmpty($errors);
    }

    public function testValiderRencontresDoublonEquipe()
    {
        $postData = [
            'equipe1' => ['Equipe A', 'Equipe A'],
            'score1' => ['10', '12'],
            'score2' => ['8', '12'],
            'equipe2' => ['Equipe B', 'Equipe C']
        ];
        $errors = $this->validator->validerRencontres($postData);
        $this->assertNotEmpty($errors);
        $this->assertContains("Une équipe ne peut jouer qu'un seul match par journée (match 2).", $errors);
    }

    // Test pour calculerClassement - nécessite setup DB mock
    // public function testCalculerClassement()
    // {
    //     // Setup mock data
    //     $classement = $this->championnatManager->calculerClassement('pouleA');
    //     $this->assertIsArray($classement);
    //     // Assertions supplémentaires selon données mock
    // }
}