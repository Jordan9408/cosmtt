<?php

declare(strict_types=1);

class Img
{
    /*Crée une miniature (crop) en JPG.
     *
     * @param string $img Chemin du fichier source
     * @param string $chemin Dossier de sortie
     * @param string $nom Nom du fichier source (avec extension attendue)
     * @param int $mlargeur Largeur miniature
     * @param int $mhauteur Hauteur miniature
     */
    public static function createMin(string $img, string $chemin, string $nom, int $mlargeur = 100, int $mhauteur = 100): bool
    {
        // On supprime l'extension du nom (comportement inchangé : on retire les 4 derniers caractères)
        // Exemple historique : "xxx.png" -> "xxx".
        $nom = substr($nom, 0, -4);

        $dimension = @getimagesize($img);
        if (!is_array($dimension) || count($dimension) < 2) {
            return false;
        }

        $srcWidth = (int)$dimension[0];
        $srcHeight = (int)$dimension[1];
        if ($srcWidth <= 0 || $srcHeight <= 0 || $mlargeur <= 0 || $mhauteur <= 0) {
            return false;
        }

        $lowerPath = strtolower($img);
        $ext4 = substr($lowerPath, -4);

        $image = null;
        if ($ext4 === '.jpg' || $ext4 === 'jpeg') {
            $image = @imagecreatefromjpeg($img);
        } elseif ($ext4 === '.png') {
            $image = @imagecreatefrompng($img);
        }

        if (!$image) {
            return false;
        }

        $ratioDest = $mlargeur / $mhauteur;
        $ratioSrc = $srcWidth / $srcHeight;

        // Calque l'ancienne logique par comparaison avec (mlargeur/mhauteur)*srcHeight
        if ($srcWidth > $ratioDest * $srcHeight) {
            $dimY = $mhauteur;
            $dimX = (int)round($mhauteur * $srcWidth / $srcHeight);
            $decalX = -($dimX - $mlargeur) / 2;
            $decalY = 0;
        } elseif ($srcWidth < $ratioDest * $srcHeight) {
            $dimX = $mlargeur;
            $dimY = (int)round($mlargeur * $srcHeight / $srcWidth);
            $decalY = -($dimY - $mhauteur) / 2;
            $decalX = 0;
        } else {
            $dimX = $mlargeur;
            $dimY = $mhauteur;
            $decalX = 0;
            $decalY = 0;
        }

        $miniature = @imagecreatetruecolor($mlargeur, $mhauteur);
        if (!$miniature) {
            return false;
        }

        imagecopyresampled(
            $miniature,
            $image,
            (int)round($decalX),
            (int)round($decalY),
            0,
            0,
            (int)$dimX,
            (int)$dimY,
            $srcWidth,
            $srcHeight
        );

        $outputPath = $chemin . '/' . $nom . '.jpg';
        $ok = @imagejpeg($miniature, $outputPath, 90);

        // libération mémoire (utile pour performance)
        @imagedestroy($miniature);
        @imagedestroy($image);

        return (bool)$ok;
    }

    /** Convertit un JPG/JPEG en JPG (et supprime l'original).*/
    public static function convertJPG(string $img): bool
    {
        $lowerPath = strtolower($img);
        $ext4 = substr($lowerPath, -4);

        $image = null;
        if ($ext4 === '.jpg' || $ext4 === 'jpeg') {
            $image = @imagecreatefromjpeg($img);
        } else {
            return false;
        }

        if (!$image) {
            return false;
        }

        // Nouvelle sortie : on remplace l'extension par 'jpg' (conserve l'intention de l'ancien code).
        $outputPath = preg_replace('/\.(jpe?g)$/i', '.jpg', $img);
        if (!is_string($outputPath) || $outputPath === '') {
            return false;
        }

        // Conserve le comportement : on supprime l'original.
        @unlink($img);

        $ok = @imagejpeg($image, $outputPath, 120);
        @imagedestroy($image);

        return (bool)$ok;
    }
}
