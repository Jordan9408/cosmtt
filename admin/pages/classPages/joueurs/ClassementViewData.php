<?php

/**
 * Objet de transfert de données pour la vue
 */
class ClassementViewData
{
    public array $joueurs;
    public bool $isEditMode;
    public bool $canEdit;
    public string $role;
    public array $errors;
    public string $successMessage;

    public function __construct(
        array $joueurs,
        bool $isEditMode,
        bool $canEdit,
        string $role,
        array $errors = [],
        string $successMessage = ''
    ) {
        $this->joueurs = $joueurs;
        $this->isEditMode = $isEditMode;
        $this->canEdit = $canEdit;
        $this->role = $role;
        $this->errors = $errors;
        $this->successMessage = $successMessage;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasSuccess(): bool
    {
        return !empty($this->successMessage);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superAdmin';
    }
}