<?php
require_once 'admin/pages/classPages/competition/JourneeValidator.php';

$validator = new JourneeValidator();

// Test valide
$errors = $validator->validerJournee(['journée' => 'Journée 1', 'dateDebut' => '2023-01-01', 'dateFin' => '2023-01-02']);
echo empty($errors) ? 'Validation journée valide passed' : 'Validation journée valide failed: ' . implode(', ', $errors);
echo "\n";

// Test invalide
$errors = $validator->validerJournee(['journée' => '', 'dateDebut' => '2023-01-02', 'dateFin' => '2023-01-01']);
echo !empty($errors) ? 'Validation journée invalide passed' : 'Validation journée invalide failed';
echo "\n";

// Test rencontres valide
$errors = $validator->validerRencontres(['equipe1' => ['Equipe A', 'Equipe B'], 'score1' => ['10', '12'], 'score2' => ['8', '11'], 'equipe2' => ['Equipe C', 'Equipe D']]);
echo empty($errors) ? 'Validation rencontres valide passed' : 'Validation rencontres valide failed: ' . implode(', ', $errors);
echo "\n";

// Test rencontres doublon équipe
$errors = $validator->validerRencontres(['equipe1' => ['Equipe A', 'Equipe A'], 'score1' => ['10', '12'], 'score2' => ['8', '11'], 'equipe2' => ['Equipe C', 'Equipe D']]);
echo !empty($errors) ? 'Validation rencontres doublon passed' : 'Validation rencontres doublon failed';
echo "\n";
