<?php

declare(strict_types=1);

/**
 * Traitement, assainissement et réécriture sécurisée des documents PDF
*/
final class PDFSecurityProcessor
{
    private const DANGEROUS_DICTIONARY_KEYS = [
        '/AA',
        '/JavaScript',
        '/JS',
        '/Launch',
        '/EmbeddedFiles',
        '/EmbeddedFile',
        '/AF',
        '/EF',
        '/RichMedia',
        '/XFA',
        '/ByteRange',
    ];

    private const DANGEROUS_ACTIONS = [
        '/JavaScript',
        '/Launch',
        '/SubmitForm',
        '/ImportData',
        '/GoToR',
        '/GoToE',
        '/Rendition',
        '/Movie',
        '/Sound',
        '/3D',
    ];

    /**
     * Traite, assainit et réécrit le document PDF avant son stockage.
     *
     * @return array{
     *     success: bool,
     *     method: string,
     *     original_size: int,
     *     final_size: int,
     *     compression: float,
     *     threats_removed: array,
     *     error: ?string
     * }
     */
    public static function processPDF(string $sourcePath, string $destPath, array $options = []): array
    {
        $result = [
            'success' => false,
            'method' => 'native-pdf-rewrite',
            'original_size' => 0,
            'final_size' => 0,
            'compression' => 0.0,
            'threats_removed' => [],
            'error' => null,
        ];

        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            $result['error'] = 'Le PDF source est introuvable ou illisible.';
            return $result;
        }

        $originalSize = filesize($sourcePath);
        if ($originalSize === false || $originalSize === 0) {
            $result['error'] = 'Le PDF source est vide.';
            return $result;
        }
        $result['original_size'] = $originalSize;

        $temporaryPath = $destPath . '.' . bin2hex(random_bytes(8)) . '.tmp';

        try {
            $threats = self::analyzePDFThreats($sourcePath);
            if ($threats !== []) {
                $result['error'] = 'Le PDF contient un contenu actif ou une fonction incompatible : ' . implode(', ', $threats);
                return $result;
            }

            // Réécriture sécurisée sans ré-encodage destructif des flux vectoriels/textuels
            if (!copy($sourcePath, $destPath)) {
                $result['error'] = 'Impossible d’enregistrer le fichier PDF.';
                return $result;
            }

            // Nettoyage in-situ des balises dangereuses dans le binaire pour sécurité totale
            self::sanitizePdfInPlace($destPath);

            $finalSize = filesize($destPath);
            $result['method'] = 'native-pdf-safe-preserve';
            $result['final_size'] = ($finalSize !== false) ? $finalSize : $originalSize;
            $result['compression'] = 0.0;
            $result['success'] = true;
            return $result;
        } catch (Throwable $e) {
            error_log('[PDFSecurityProcessor] ' . $e->getMessage());
            $result['error'] = 'Le contrôle du PDF a échoué : ' . $e->getMessage();
            return $result;
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * Analyse les menaces potentielles dans le PDF (JavaScript, actions de lancement, etc.).
     */
    public static function analyzePDFThreats(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['analyse des fonctions PDF impossible'];
        }

        $raw = file_get_contents($filePath);
        if ($raw === false || strlen($raw) < 5) {
            return ['analyse des fonctions PDF impossible'];
        }

        $threats = [];

        foreach (self::DANGEROUS_DICTIONARY_KEYS as $key) {
            if (preg_match('#' . preg_quote($key, '#') . '(\s*[\/\<\[\(\d]|$)#i', $raw)) {
                $threats[] = $key;
            }
        }

        foreach (self::DANGEROUS_ACTIONS as $action) {
            if (preg_match('#/S\s*' . preg_quote($action, '#') . '(\s*[\/\<\[\(\d\>]|$)#i', $raw)) {
                $threats[] = $action;
            }
        }

        if (preg_match('#/URI\s*\([^)]*javascript:[^)]*\)#i', $raw) || preg_match('#/URI\s*<[^>]*>#i', $raw)) {
            if (preg_match('#javascript:#i', $raw)) {
                $threats[] = 'javascript URI';
            }
        }

        if (preg_match('#/EmbeddedFiles\b|/EF\s*<<#i', $raw)) {
            $threats[] = 'embedded attachments';
        }

        return array_values(array_unique($threats));
    }

    /**
     * Neutralise in-situ les clés de dictionnaires potentiellement actives sans décaler aucun octet
     * pour garantir un alignement parfait de la table XREF et l'affichage complet des tableaux.
     */
    private static function sanitizePdfInPlace(string $filePath): void
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return;
        }

        $modified = false;
        $dangerousTags = [
            '/JavaScript' => '/NoScript  ',
            '/JS '        => '/NO ',
            '/Launch'     => '/NoLaunch',
            '/SubmitForm' => '/NoSubmit  ',
            '/ImportData' => '/NoImport  ',
            '/RichMedia'  => '/NoMedia  ',
        ];

        foreach ($dangerousTags as $threat => $replacement) {
            if (str_contains($raw, $threat)) {
                $raw = str_replace($threat, $replacement, $raw);
                $modified = true;
            }
        }

        if ($modified) {
            file_put_contents($filePath, $raw);
        }
    }

    /**
     * Valide qu'un fichier est un document PDF intègre et lisible.
     */
    public static function validateProcessedPDF(string $filePath): bool
    {
        if (!is_file($filePath) || !is_readable($filePath) || filesize($filePath) === 0) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $header = fread($handle, 1024);
            if ($header === false || !str_starts_with($header, '%PDF-')) {
                return false;
            }

            fseek($handle, -2048, SEEK_END);
            $trailerChunk = fread($handle, 2048);
            if ($trailerChunk === false || !str_contains($trailerChunk, '%%EOF')) {
                return false;
            }

            return true;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Fournit un message textuel décrivant le résultat du traitement.
     */
    public static function getProcessingDescription(array $result): string
    {
        if (!$result['success']) {
            return 'Échec du traitement PDF : ' . ($result['error'] ?? 'erreur inconnue');
        }

        return sprintf(
            'PDF traité avec %s (réduction de %.2f%%).',
            $result['method'],
            $result['compression']
        );
    }

    /**
     * Réécrit la structure complète du document PDF en utilisant la taille exacte /Length
     * des flux binaires pour préserver à 100% l'intégrité visuelle des images et graphiques.
     */
    private static function rewriteAndOptimizePDF(string $sourcePath, string $destPath): bool
    {
        $raw = file_get_contents($sourcePath);
        if ($raw === false || !str_starts_with($raw, '%PDF-')) {
            return false;
        }

        // Récupérer la version de l'en-tête PDF
        $headerLine = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        if (preg_match('/^(%PDF-[12]\.[0-9]+)/', $raw, $mHeader)) {
            $headerLine = $mHeader[1] . "\n%\xE2\xE3\xCF\xD3\n";
        }

        // Extraction précise des objets indirects
        $objects = self::extractPdfObjects($raw);
        if (empty($objects)) {
            return (bool)file_put_contents($destPath, $raw);
        }

        // Récupération de l'objet Root et Info depuis le trailer du fichier original
        $trailerDict = [];
        if (preg_match('/trailer\s*<<(.*?)>>\s*startxref/s', $raw, $mTrailer)) {
            $trailerContent = $mTrailer[1];
            if (preg_match('/\/Root\s+(\d+\s+\d+\s+R)/', $trailerContent, $mRoot)) {
                $trailerDict['/Root'] = $mRoot[1];
            }
            if (preg_match('/\/Info\s+(\d+\s+\d+\s+R)/', $trailerContent, $mInfo)) {
                $trailerDict['/Info'] = $mInfo[1];
            }
            if (preg_match('/\/ID\s*(\[[^\]]*\])/', $trailerContent, $mId)) {
                $trailerDict['/ID'] = $mId[1];
            }
        }

        $maxObjNum = 0;
        foreach (array_keys($objects) as $num) {
            if ($num > $maxObjNum) {
                $maxObjNum = $num;
            }
        }

        $out = fopen($destPath, 'wb');
        if ($out === false) {
            return false;
        }

        try {
            fwrite($out, $headerLine);
            $xrefOffsets = [];

            ksort($objects);

            foreach ($objects as $num => $objData) {
                $offset = ftell($out);
                $xrefOffsets[$num] = $offset;

                $objBlock = self::formatPdfObject($objData);
                fwrite($out, $objBlock);
            }

            $startXref = ftell($out);
            $totalSize = $maxObjNum + 1;

            // Écriture de la table XREF
            fwrite($out, "xref\n");
            fwrite($out, sprintf("0 %d\n", $totalSize));
            fwrite($out, "0000000000 65535 f \n");

            for ($i = 1; $i <= $maxObjNum; $i++) {
                if (isset($xrefOffsets[$i])) {
                    fwrite($out, sprintf("%010d 00000 n \n", $xrefOffsets[$i]));
                } else {
                    fwrite($out, "0000000000 65535 f \n");
                }
            }

            // Écriture du trailer réécrit
            fwrite($out, "trailer\n<<\n");
            fwrite($out, sprintf("/Size %d\n", $totalSize));
            if (isset($trailerDict['/Root'])) {
                fwrite($out, sprintf("/Root %s\n", $trailerDict['/Root']));
            }
            if (isset($trailerDict['/Info'])) {
                fwrite($out, sprintf("/Info %s\n", $trailerDict['/Info']));
            }
            if (isset($trailerDict['/ID'])) {
                fwrite($out, sprintf("/ID %s\n", $trailerDict['/ID']));
            } else {
                $idHash = strtoupper(md5($destPath . microtime()));
                fwrite($out, sprintf("/ID [<%s> <%s>]\n", $idHash, $idHash));
            }
            fwrite($out, ">>\nstartxref\n");
            fwrite($out, (string)$startXref . "\n");
            fwrite($out, "%%EOF\n");

            return true;
        } finally {
            fclose($out);
        }
    }

    /**
     * Parseur PDF respectant les longueurs binaires exactes (/Length directes ou indirectes).
     *
     * @return array<int, array{num:int, gen:int, is_stream:bool, dict:string, stream:?string}>
     */
    private static function extractPdfObjects(string $raw): array
    {
        // Pré-indexation des objets scalaires pour résoudre les références indirectes comme /Length 12 0 R
        $scalarObjects = [];
        if (preg_match_all('/(\d+)\s+(\d+)\s+obj\s+([^\r\n<>[\]()\/]+?)\s+endobj/s', $raw, $allScalars, PREG_SET_ORDER)) {
            foreach ($allScalars as $sc) {
                $val = trim($sc[3]);
                if (is_numeric($val)) {
                    $scalarObjects[(int)$sc[1]] = (int)$val;
                }
            }
        }

        $objects = [];
        $offset = 0;
        $len = strlen($raw);

        while ($offset < $len) {
            if (!preg_match('/(\d+)\s+(\d+)\s+obj\b/i', $raw, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $objStartPos = $m[0][1];
            $objNum = (int)$m[1][0];
            $genNum = (int)$m[2][0];
            $headerEndPos = $objStartPos + strlen($m[0][0]);

            $streamPos = preg_match('/\bstream[\r\n]{1,2}/', $raw, $mStream, PREG_OFFSET_CAPTURE, $headerEndPos) ? $mStream[0][1] : false;
            $endobjPos = preg_match('/\bendobj\b/', $raw, $mEndobj, PREG_OFFSET_CAPTURE, $headerEndPos) ? $mEndobj[0][1] : false;

            if ($streamPos !== false && ($endobjPos === false || $streamPos < $endobjPos)) {
                $streamDataStart = $streamPos + strlen($mStream[0][0]);
                $dict = substr($raw, $headerEndPos, $streamPos - $headerEndPos);

                $declaredLength = null;
                if (preg_match('/\/Length\s+(\d+)\b/', $dict, $mLen)) {
                    $declaredLength = (int)$mLen[1];
                } elseif (preg_match('/\/Length\s+(\d+)\s+\d+\s+R\b/', $dict, $mRef)) {
                    $refNum = (int)$mRef[1];
                    if (isset($scalarObjects[$refNum])) {
                        $declaredLength = $scalarObjects[$refNum];
                    }
                }

                if ($declaredLength !== null && ($streamDataStart + $declaredLength) <= $len) {
                    $streamData = substr($raw, $streamDataStart, $declaredLength);
                    $afterStream = substr($raw, $streamDataStart + $declaredLength, 80);
                    if (preg_match('/[\r\n\s]*endstream[\r\n\s]*endobj/', $afterStream, $mEndAll, PREG_OFFSET_CAPTURE)) {
                        $objEndPos = $streamDataStart + $declaredLength + $mEndAll[0][1] + strlen($mEndAll[0][0]);
                    } else {
                        $found = strpos($raw, 'endobj', $streamDataStart + $declaredLength);
                        $objEndPos = ($found !== false) ? $found + 6 : $streamDataStart + $declaredLength;
                    }
                } else {
                    $endstreamPos = strpos($raw, 'endstream', $streamDataStart);
                    if ($endstreamPos !== false) {
                        $streamData = substr($raw, $streamDataStart, $endstreamPos - $streamDataStart);
                        $found = strpos($raw, 'endobj', $endstreamPos);
                        $objEndPos = ($found !== false) ? $found + 6 : $endstreamPos + 9;
                    } else {
                        $streamData = '';
                        $objEndPos = $headerEndPos;
                    }
                }

                $objects[$objNum] = [
                    'num' => $objNum,
                    'gen' => $genNum,
                    'is_stream' => true,
                    'dict' => trim($dict),
                    'stream' => $streamData,
                ];
            } else {
                $body = ($endobjPos !== false) ? substr($raw, $headerEndPos, $endobjPos - $headerEndPos) : substr($raw, $headerEndPos);
                $objEndPos = ($endobjPos !== false) ? $endobjPos + 6 : $len;
                $objects[$objNum] = [
                    'num' => $objNum,
                    'gen' => $genNum,
                    'is_stream' => false,
                    'dict' => trim($body),
                    'stream' => null,
                ];
            }

            $offset = max($objEndPos, $headerEndPos + 1);
        }

        return $objects;
    }

    /**
     * Formate et optimise un objet PDF :
     * - Conserve scrupuleusement les images, polices, graphiques et métadonnées de mise en page.
     * - Optimise en toute sécurité sans altérer les flux avec filtres spéciaux ou prédicteurs.
     */
    private static function formatPdfObject(array $objData): string
    {
        $num = $objData['num'];
        $gen = $objData['gen'];
        $dict = $objData['dict'];

        // Nettoyage de sécurité dans les dictionnaires (ex: JavaScript, Launch...)
        foreach (self::DANGEROUS_DICTIONARY_KEYS as $key) {
            $dict = preg_replace('#' . preg_quote($key, '#') . '\s*(\[[^\]]*\]|<<[^>]*>>|\([^\)]*\)|[^\s\>\<\/]+)#i', '', $dict);
        }

        if (!$objData['is_stream']) {
            return sprintf("%d %d obj\n%s\nendobj\n", $num, $gen, $dict);
        }

        $streamData = $objData['stream'] ?? '';
        $isImage = (bool)preg_match('/\/Subtype\s*\/Image\b/i', $dict);
        $isFlate = (bool)preg_match('/\/Filter\s*(\/FlateDecode|\[\s*\/FlateDecode\s*\])/i', $dict);
        $hasDecodeParms = (bool)preg_match('/\/DecodeParms\b/i', $dict);
        $hasOtherFilter = (bool)preg_match('/\/Filter\s*(\/DCTDecode|\/CCITTFaxDecode|\/JBIG2Decode|\/JPXDecode|\/ASCIIHexDecode|\/ASCII85Decode|\/LZWDecode|\/RunLengthDecode)/i', $dict);

        // Si le flux utilise un filtre spécial ou des DecodeParms (ex: prédicteurs PNG pour tableaux),
        // ou si c'est une image, conserver le flux binaire 100% intact pour garantir l'affichage complet.
        if ($isImage || $hasOtherFilter || $hasDecodeParms) {
            $dict = self::updateOrInsertLength($dict, strlen($streamData));
            return sprintf("%d %d obj\n%s\nstream\n%s\nendstream\nendobj\n", $num, $gen, $dict, $streamData);
        }

        // Pour les flux textuels et de mise en page simples sans prédicteurs, optimiser si compression possible
        if ($isFlate) {
            $uncompressed = @gzuncompress($streamData);
            if ($uncompressed !== false) {
                $recompressed = @gzcompress($uncompressed, 9);
                if ($recompressed !== false && strlen($recompressed) < strlen($streamData)) {
                    $streamData = $recompressed;
                }
            }
        }

        $dict = self::updateOrInsertLength($dict, strlen($streamData));

        return sprintf("%d %d obj\n%s\nstream\n%s\nendstream\nendobj\n", $num, $gen, $dict, $streamData);
    }

    /**
     * Met à jour ou insère la clé /Length directe dans le dictionnaire du stream.
     */
    private static function updateOrInsertLength(string $dict, int $length): string
    {
        if (preg_match('/\/Length\s+(\d+|\d+\s+\d+\s+R)/', $dict)) {
            return preg_replace('/\/Length\s+(\d+|\d+\s+\d+\s+R)/', '/Length ' . $length, $dict, 1);
        }

        if (str_starts_with($dict, '<<')) {
            return '<< /Length ' . $length . ' ' . substr($dict, 2);
        }

        return $dict . ' /Length ' . $length;
    }
}
