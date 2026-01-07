// JavaScript pour la page de classement des joueurs

// Gestion des popups
const showPopup = document.getElementById('showPopup');
const teamPopup = document.getElementById('teamPopup');
const cancelPopup = document.getElementById('cancelPopup');

if (showPopup) {
    showPopup.addEventListener('click', () => {
        teamPopup.style.display = 'flex';
    });
}

// Fermer le popup en cliquant sur Annuler
if (cancelPopup) {
    cancelPopup.addEventListener('click', () => {
        const formAjout = document.querySelector('.form-ajout');
        if (formAjout) {
            formAjout.reset(); // Réinitialise tous les champs
        }
        teamPopup.style.display = 'none'; // Ferme le popup
    });
}

// Fermer le popup en cliquant en dehors
window.addEventListener('click', (e) => {
    if (e.target === teamPopup) {
        teamPopup.style.display = 'none';
    }
});

// Fonction de validation pour nom et prénom (lettres, espaces, tirets, apostrophes uniquement)
function validateNomPrenom(value) {
    const regex = /^[\p{L}\s'-]+$/u;
    return regex.test(value);
}

// Validation pour le formulaire d'ajout au moment de la soumission
const formAjout = document.querySelector('.form-ajout');
if (formAjout) {
    formAjout.addEventListener('submit', function(e) {
        const nomInput = this.querySelector('input[name="nom"]');
        const prenomInput = this.querySelector('input[name="prenom"]');
        const errors = [];

        // Validation nom + prénom
        if ( (nomInput.value && !validateNomPrenom(nomInput.value)) || (prenomInput.value && !validateNomPrenom(prenomInput.value)) ) {
            errors.push('Le nom et le prénom ne doivent pas contenir de chiffre.');
        }

        // Si des erreurs existent, afficher l'alerte et empêcher la soumission
        if (errors.length > 0) {
            e.preventDefault();
            alert(errors.join('\n'));
        }
    });
}

// Fonction de validation pour le mode édition
function validateEditForm() {
    const errors = [];
    const nomInputs = document.querySelectorAll('input[name^="nom["]');
    const prenomInputs = document.querySelectorAll('input[name^="prenom["]');
    const classementInputs = document.querySelectorAll('input[name^="classement["]');
    const ptsDebutInputs = document.querySelectorAll('input[name^="points_debut_saison["]');
    const ptsMensuelInputs = document.querySelectorAll('input[name^="points_mensuels["]');

    let invalidNomPrenomMessages = [];
    let missingNomPrenom = false; // empêche les doublons

    // Validation des noms
    nomInputs.forEach(function(input) {
        const value = input.value.trim();
        const joueurId = input.getAttribute('name').match(/\[(\d+)\]/);
        const prenomInput = document.querySelector(`input[name="prenom[${joueurId}]"]`);
        const prenom = prenomInput ? prenomInput.value.trim() : "";

        if (!value) {
            missingNomPrenom = true;
        } else if (!validateNomPrenom(value)) {
            invalidNomPrenomMessages.push(`Nom invalide pour : ${value} ${prenom}`);
        }
    });

    // Validation des prénoms
    prenomInputs.forEach(function(input) {
        const value = input.value.trim();
        const joueurId = input.getAttribute('name').match(/\[(\d+)\]/);
        const nomInput = document.querySelector(`input[name="nom[${joueurId}]"]`);
        const nom = nomInput ? nomInput.value.trim() : "";

        if (!value) {
            missingNomPrenom = true;
        } else if (!validateNomPrenom(value)) {
            invalidNomPrenomMessages.push(`Prénom invalide : ${nom} ${value}`);
        }
    });

    // Ajout d'un seul message global
    if (missingNomPrenom) {
        invalidNomPrenomMessages.unshift("Le nom et le prénom sont obligatoires.");
    }

    if (invalidNomPrenomMessages.length > 0) {
        errors.push("Erreurs sur noms/prénoms :\n" + invalidNomPrenomMessages.join("\n"));
    }


    // Validation des classements
    classementInputs.forEach(function(input) {
        const joueurId = input.getAttribute('name').match(/\[(\d+)\]/)[1];
        const nomInput = document.querySelector(`input[name="nom[${joueurId}]"]`);
        const prenomInput = document.querySelector(`input[name="prenom[${joueurId}]"]`);
        const joueurNom = nomInput ? nomInput.value.trim() : '';
        const joueurPrenom = prenomInput ? prenomInput.value.trim() : '';
        const joueurComplet = `${joueurNom} ${joueurPrenom}`.trim() || `(ID: ${joueurId})`;

        const classement = parseInt(input.value);
        if (isNaN(classement) || classement < 5 || classement > 99) {
            errors.push(`Le classement pour ${joueurComplet} doit être au minimum 5.`);
        }
    });

    // Validation des points début saison
    ptsDebutInputs.forEach(function(input) {
        const joueurId = input.getAttribute('name').match(/\[(\d+)\]/)[1];
        const nomInput = document.querySelector(`input[name="nom[${joueurId}]"]`);
        const prenomInput = document.querySelector(`input[name="prenom[${joueurId}]"]`);
        const joueurNom = nomInput ? nomInput.value.trim() : '';
        const joueurPrenom = prenomInput ? prenomInput.value.trim() : '';
        const joueurComplet = `${joueurNom} ${joueurPrenom}`.trim() || `(ID: ${joueurId})`;

        const points = parseInt(input.value);
        if (isNaN(points) || points < 500) {
            errors.push(`Les points début saison pour ${joueurComplet} doivent être au minimum 500.`);
        }
    });

    // Validation des points mensuels
    ptsMensuelInputs.forEach(function(input) {
        const joueurId = input.getAttribute('name').match(/\[(\d+)\]/)[1];
        const nomInput = document.querySelector(`input[name="nom[${joueurId}]"]`);
        const prenomInput = document.querySelector(`input[name="prenom[${joueurId}]"]`);
        const joueurNom = nomInput ? nomInput.value.trim() : '';
        const joueurPrenom = prenomInput ? prenomInput.value.trim() : '';
        const joueurComplet = `${joueurNom} ${joueurPrenom}`.trim() || `(ID: ${joueurId})`;

        const points = parseInt(input.value);
        if (isNaN(points) || points < 0) {
            errors.push(`Les points mensuels pour ${joueurComplet} doivent être positifs.`);
        }
    });

    if (errors.length > 0) {
        alert(errors.join('\n'));
        return false;
    }
    return true;
}
