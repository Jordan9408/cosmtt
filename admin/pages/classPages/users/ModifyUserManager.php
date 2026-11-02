<?php
namespace Admin\ClassPages\Users;

use User;

/**
 * Manager pour la gestion métier de la modification d'un utilisateur.
 */
class ModifyUserManager
{
    /**
     * @param User $userClass Injection de la dépendance User
     */
    public function __construct(private readonly User $userClass)
    {
    }

    /**
     * Récupère les données d'un utilisateur depuis son ID.
     */
    public function getUserById(int $id): ?array
    {
        $user = $this->userClass->getUser($id);
        return $user ?: null;
    }

    /**
     * Met à jour les informations de l'utilisateur en base de données.
     */
    public function updateUser(int $id, string $firstName, string $lastName, string $email, ?string $password, string $role): bool
    {
        return $this->userClass->updateUser($id, $firstName, $lastName, $email, $password, $role);
    }
}