<?php

/**
 * Fonction utilitaire pour échapper les chaînes HTML et éviter les attaques XSS.
 * Utilise htmlspecialchars avec ENT_QUOTES | ENT_SUBSTITUTE et UTF-8.
 *
 * @param string $string La chaîne à échapper.
 * @return string La chaîne échappée.
 */
function escapeHtml(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}