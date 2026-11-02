<?php
declare(strict_types=1);

namespace Admin\ClassPages\Users;

use User;

/**
 * Manager pour la gestion métier de l'ajout d'un utilisateur.
 */
class AddUserManager
{
    /**
     * @param User $userClass Injection de la dépendance User (PHP 8 Constructor Property Promotion)
     */
    public function __construct(private readonly User $userClass)
    {
    }

    /**
     * Ajoute un nouvel utilisateur en base de données.
     */
    public function addUser(string $firstName, string $lastName, string $email, string $password, string $role): bool
    {
        return $this->userClass->addUser($firstName, $lastName, $email, $password, $role);
    }
}