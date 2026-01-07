// JavaScript pour la gestion des championnats
/**
 * Vérifie les doublons d'équipes avant de soumettre le formulaire d'édition
 */
function checkAndSubmit() {
    const journeeWrappers = document.querySelectorAll('.journee-wrapper');
    let hasDuplicate = false;
    let duplicateMessage = '';

    journeeWrappers.forEach(wrapper => {
        const equipe1Selects = wrapper.querySelectorAll('select[name^="equipe1["]');
        const equipe2Selects = wrapper.querySelectorAll('select[name^="equipe2["]');
        const teamsInJournee = [];

        equipe1Selects.forEach(select => {
            const value = select.value.trim();
            if (value && value !== 'Aucune équipe') {
                teamsInJournee.push(value);
            }
        });

        equipe2Selects.forEach(select => {
            const value = select.value.trim();
            if (value && value !== 'Aucune équipe') {
                teamsInJournee.push(value);
            }
        });

        const teamCounts = {};
        teamsInJournee.forEach(team => {
            teamCounts[team] = (teamCounts[team] || 0) + 1;
        });
        const duplicatedTeams = Object.keys(teamCounts).filter(team => teamCounts[team] > 1);
        if (duplicatedTeams.length > 0) {
            hasDuplicate = true;
            const journeeTitle = wrapper.querySelector('th[colspan="4"]').textContent.trim();
            const journeeId = journeeTitle.split(' (')[0];
            const teamsStr = duplicatedTeams.join(', ');
            const verb = duplicatedTeams.length === 1 ? 'joue' : 'jouent';
            duplicateMessage += `\nDans la ${journeeId}, ${teamsStr} ${verb} plusieurs matchs.\n`;
        }
    });

    if (hasDuplicate) {
        alert("Erreur : " + duplicateMessage + "Une équipe ne peut disputer qu'un seul match par journée.");
        return false;
    } else {
        const form = document.getElementById('editForm');
        if (form) form.submit();
    }
}

/**
 * Demande confirmation avant la suppression d'une journée
 */
function confirmerSuppression(journeeId, journeeNom, poule) {
    if (confirm(
            `Êtes-vous sûr de vouloir supprimer la ${journeeNom} ? \nCette action est irréversible et supprimera toutes les rencontres associées.`
        )) {
        // Redirection vers le script PHP pour la suppression
        window.location.href = `?poule=${encodeURIComponent(poule)}&delete_journee_id=${journeeId}&edit=1`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Récupération de la variable passée depuis PHP via l'objet window
    const isEditMode = window.isEditMode || false;

    function updateAllOptions() {
        // Ne désactiver les options que si on n'est PAS en mode édition
        if (isEditMode) {
            return; // Ne rien faire en mode édition
        }

        const allSelects = document.querySelectorAll('.popup .selectEquipes');
        allSelects.forEach(select => {
            Array.from(select.options).forEach(option => {
                option.disabled = false;
            });
        });

        const selectedValues = Array.from(allSelects)
            .map(select => select.value)
            .filter(value => value !== '' && value !== 'Aucune équipe');

        allSelects.forEach(select => {
            selectedValues.forEach(value => {
                if (select.value !== value) {
                    const optionToDisable = select.querySelector(
                        `option[value="${value}"]`);
                    if (optionToDisable) optionToDisable.disabled = true;
                }
            });
        });
    }

    function addEventListenersToSelects() {
        // Ajouter les écouteurs uniquement aux selects de la popup (mode ajout)
        document.querySelectorAll('.popup .selectEquipes').forEach(select => {
            select.addEventListener('change', updateAllOptions);
        });
    }

    // Initialisation
    addEventListenersToSelects();
    updateAllOptions();

    // Gestion de l'ajout et suppression de rencontres (DOM Formulaire Ajout)
    const formAjout = document.querySelector('.form-ajout');

    if (formAjout) {
        const buttonsRow = formAjout.querySelector('.buttons-row');
        if (buttonsRow) {
            const ajoutBtn = buttonsRow.querySelector('.ajoutRencontreBtn');
            const supBtn = buttonsRow.querySelector('.supRencontreBtn');
            const formGroup = formAjout.querySelector('.form-group');

            if (ajoutBtn && supBtn && formGroup) {
                ajoutBtn.addEventListener('click', function() {
                    const allMatchs = formGroup.querySelectorAll('.matchs');
                    const lastMatchs = allMatchs[allMatchs.length - 1];
                    const newMatchs = lastMatchs.cloneNode(true);

                    // RÃ©initialiser les valeurs
                    const selects = newMatchs.querySelectorAll('select');
                    selects.forEach(select => {
                        select.selectedIndex = 0;
                    });
                    const inputs = newMatchs.querySelectorAll('input[type="number"]');
                    inputs.forEach(input => input.value = '');

                    // Ajouter les écouteurs d'événements aux nouveaux selects
                    selects.forEach(select => {
                        select.addEventListener('change', updateAllOptions);
                    });

                    // Insérer avant la ligne des boutons
                    formGroup.insertBefore(newMatchs, buttonsRow);

                    // Mettre à jour les options
                    updateAllOptions();
                });

                supBtn.addEventListener('click', function() {
                    const allMatchs = formGroup.querySelectorAll('.matchs');
                    if (allMatchs.length > 1) {
                        allMatchs[allMatchs.length - 1].remove();
                        updateAllOptions();
                    }
                });

                // Gestion du bouton Annuler
                const cancelButton = document.getElementById('cancelPopup');
                const teamPopup = document.getElementById('teamPopup');

                if (cancelButton && teamPopup) {
                    cancelButton.addEventListener('click', function() {
                        teamPopup.style.display = 'none';
                        // Réinitialiser le formulaire
                        formAjout.reset();
                        // Supprimer les matchs supplémentaires au-delà du premier
                        const allMatchs = formGroup.querySelectorAll('.matchs');
                        while (allMatchs.length > 1) {
                            allMatchs[allMatchs.length - 1].remove();
                        }
                        // Mettre à jour les options des selects
                        updateAllOptions();
                    });
                }
            }
        }
    }

    // Gestion Boutons Aller / Retour
    const typeAllerBtn = document.getElementById('typeAllerBtn');
    const typeRetourBtn = document.getElementById('typeRetourBtn');
    const typeJourneeInput = document.getElementById('typeJourneeInput');

    if (typeAllerBtn && typeRetourBtn && typeJourneeInput) {
        function toggleTypeSelection(clickedBtn, otherBtn) {
            clickedBtn.classList.add('selected');
            otherBtn.classList.remove('selected');
        }

        typeAllerBtn.addEventListener('click', function() {
            toggleTypeSelection(typeAllerBtn, typeRetourBtn);
            typeJourneeInput.value = 'aller';
        });

        typeRetourBtn.addEventListener('click', function() {
            toggleTypeSelection(typeRetourBtn, typeAllerBtn);
            typeJourneeInput.value = 'retour';
        });
    }

    // Nettoyage de l'URL pour éviter la réaffichage du message au refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.pathname);
    }
});