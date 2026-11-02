(function () {
  'use strict';

  // Patterns dangereux à détecter côté client
  const DANGEROUS_PATTERNS = [
    /(<\?php|<\?|<script|javascript:)/i,
    /(eval|system|exec|shell_exec|passthru|proc_open|pcntl_exec)/i,
    /(const|let|var|function)\s*[=\(]/i,
    /(`{3}|```)/i,
    /(on\w+\s*=|onclick|onerror|onload|onmouseover)/i,
    /(<iframe|<embed|<object|<img\s+on)/i
  ];

  // Fonction de validation du contenu (détection de code malveillant)
  function validateCleanContent(content, fieldName) {
    for (let pattern of DANGEROUS_PATTERNS) {
      if (pattern.test(content)) {
        return fieldName + " : Contenu non autorisé détecté. Veuillez supprimer tout code ou balise HTML.";
      }
    }
    return null;
  }

  // Fonction de validation des tarifs
  function validateTarifsBeforeSubmit() {
    const tarifAdulteInput = document.querySelector('input[name="tarif_adulte"]');
    const tarifMineursInput = document.querySelector('input[name="tarif_mineurs"]');

    if (!tarifAdulteInput || !tarifMineursInput) {
      return true;
    }

    const tarifAdulteRaw = tarifAdulteInput.value.trim();
    const tarifMineursRaw = tarifMineursInput.value.trim();
    let errors = [];

    function validateTarif(tarifRaw, fieldName) {
      const validPattern = /^[0-9\s€]*$/;
      if (tarifRaw && !validPattern.test(tarifRaw)) {
        return fieldName + " : Le prix de la licence doit être un entier.";
      }

      let tarifClean = tarifRaw.replace(/[\s€]/g, '');

      if (!tarifClean) {
        return null;
      }

      if (!/^\d+$/.test(tarifClean)) {
        return fieldName + " : Le prix doit être un nombre entier.\n   ✗ Entrée actuelle : " + tarifRaw;
      }

      const tarifInt = parseInt(tarifClean, 10);

      if (tarifInt < 0 || tarifInt > 200) {
        return fieldName + " : Le prix des licences doit être entre 0 et 200 €";
      }

      return null;
    }

    const errorAdulte = validateTarif(tarifAdulteRaw, 'Tarif Adultes');
    const errorMineurs = validateTarif(tarifMineursRaw, 'Tarif Mineurs');

    if (errorAdulte) errors.push(errorAdulte);
    if (errorMineurs) errors.push(errorMineurs);

    if (errors.length > 0) {
      const fullMessage = errors.join("\n\n");
      alert(fullMessage);
      return false;
    }

    return true;
  }

  // Fonction de validation globale avant soumission
  function checkAndSubmit() {
    const horaireInput = document.querySelector('textarea[name="horaires"]');
    const nomClubInput = document.querySelector('textarea[name="nom_club"]');
    const adresseInput = document.querySelector('textarea[name="adresse"]');

    let errors = [];

    // Validation des horaires
    if (horaireInput && horaireInput.value.trim()) {
      const horaireError = validateCleanContent(
        horaireInput.value.trim(),
        "Horaires d'entraînements"
      );
      if (horaireError) {
        errors.push(horaireError);
      }
    }

    // Validation du nom du club
    if (nomClubInput && nomClubInput.value.trim()) {
      const nomClubError = validateCleanContent(nomClubInput.value.trim(), 'Nom du club');
      if (nomClubError) {
        errors.push(nomClubError);
      }
    }

    // Validation de l'adresse
    if (adresseInput && adresseInput.value.trim()) {
      const adresseError = validateCleanContent(adresseInput.value.trim(), 'Adresse');
      if (adresseError) {
        errors.push(adresseError);
      }
    }

    // Validation des tarifs
    if (validateTarifsBeforeSubmit() === false) {
      return;
    }

    // Si des erreurs sont détectées
    if (errors.length > 0) {
      const fullMessage = errors.join("\n\n");
      alert(fullMessage);
      return false;
    }

    // Si tout est valide, soumettre le formulaire
    const form = document.querySelector('form');
    if (form) form.submit();
  }

  // Exposer les fonctions nécessaires au besoin (CSP/inline handler)
  window.checkAndSubmit = checkAndSubmit;

  document.addEventListener('DOMContentLoaded', function () {
    const saveBtn = document.getElementById('save-club-btn');
    if (saveBtn) {
      saveBtn.addEventListener('click', function (e) {
        e.preventDefault();
        checkAndSubmit();
      });
    }
  });

})();

