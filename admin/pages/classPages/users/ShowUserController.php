<?php
declare(strict_types=1);

require_once 'ShowUserManager.php';
require_once 'ShowUserView.php';

class ShowUserController
{
    private ShowUserManager $userManager;
    private ShowUserView $userView;

    // Typage strict de l'injection de dépendance
    public function __construct(PDO $conn)
    {
        $this->userManager = new ShowUserManager($conn);
        $this->userView = new ShowUserView();
    }

    /**
     * Orchestre la récupération et l'affichage des utilisateurs
     */
    public function displayUsers(): void
    {
        // 1. Récupération des données via le Manager
        $users = $this->userManager->getUsers();
        
        // 2. Transmission des données à la Vue pour l'affichage
        $this->userView->renderUserRows($users);
    }
}
?>