// JavaScript pour la page de classement des joueurs

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
        
        if ( (nomInput.value && !validateNomPrenom(nomInput.value)) || (prenomInput.value && !validateNomPrenom(prenomInput.value)) ) {
            e.preventDefault();
            alert('Le nom et le prénom ne doivent pas contenir de chiffre.');
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
        const match = input.getAttribute('name').match(/\[(\d+)\]/);
        const joueurId = match ? match[1] : null;
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
        const match = input.getAttribute('name').match(/\[(\d+)\]/);
        const joueurId = match ? match[1] : null;
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
        const match = input.getAttribute('name').match(/\[(\d+)\]/);
        const joueurId = match ? match[1] : null;
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
        const match = input.getAttribute('name').match(/\[(\d+)\]/);
        const joueurId = match ? match[1] : null;
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
        const match = input.getAttribute('name').match(/\[(\d+)\]/);
        const joueurId = match ? match[1] : null;
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

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    // 1. Bouton "Enregistrer" en mode édition
    const btnEnregistrer = document.getElementById('btnSaveClassement');
    if (btnEnregistrer) {
        btnEnregistrer.addEventListener('click', function(e) {
            e.preventDefault();
            if (validateEditForm()) {
                const editForm = document.getElementById('editForm');
                if (editForm) {
                    editForm.submit();
                }
            }
        });
    }

    // 2. Suppression de joueur via délégation d'événements
    document.addEventListener('click', function(e) {
        const deleteLink = e.target.closest('.btn-delete-joueur');
        if (!deleteLink) return;

        e.preventDefault();
        const joueurId = deleteLink.dataset.id;
        const csrfToken = deleteLink.dataset.csrf || (document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '');
        if (joueurId && csrfToken) {
            supprimerJoueur(joueurId, csrfToken);
        }
    });

    // 3. Affichage des notifications d'erreur si présentes
    const errorsScript = document.getElementById('classementErrorsData');
    if (errorsScript) {
        try {
            const errorMessages = JSON.parse(errorsScript.textContent);
            if (Array.isArray(errorMessages)) {
                errorMessages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = 'error-notification';
                    div.textContent = msg;
                    document.body.prepend(div);
                });
            }
        } catch (err) {
            console.error('Erreur lecture données erreurs classement:', err);
        }
    }
});

/**
 * Demande confirmation et soumet la suppression d'un joueur en méthode POST avec jeton CSRF
 */
function supprimerJoueur(id, csrfToken) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce joueur ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'showClassement.php?edit=1';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_id';
        deleteInput.value = id;
        form.appendChild(deleteInput);

        document.body.appendChild(form);
        form.submit();
    }
}

