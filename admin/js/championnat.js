// JavaScript pour la gestion des championnats

/** Liste des journées existantes chargée depuis le JSON PHP */
let journeesExistantes = [];
/** Liste des rencontres existantes chargée depuis le JSON PHP */
let rencontresExistantes = [];

/**
 * Vérifie les doublons d'équipes avant de soumettre le formulaire d'édition
 */
function checkAndSubmit() {
    const form = document.getElementById('editForm');
    if (form) {
        form.submit();
    }
}

/**
 * Demande confirmation avant la suppression d'une journée et soumet en POST sécurisé avec jeton CSRF
 */
function confirmerSuppression(journeeId, journeeNom, poule, csrfToken) {
    if (
        confirm(
            `Êtes-vous sûr de vouloir supprimer la ${journeeNom} ? \nCette action est irréversible et supprimera toutes les rencontres associées.`
        )
    ) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `?poule=${encodeURIComponent(poule)}&edit=1`;

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken || '';
        form.appendChild(csrfInput);

        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_journee_id';
        deleteInput.value = journeeId;
        form.appendChild(deleteInput);

        const pouleInput = document.createElement('input');
        pouleInput.type = 'hidden';
        pouleInput.name = 'poule';
        pouleInput.value = poule;
        form.appendChild(pouleInput);

        document.body.appendChild(form);
        form.submit();
    }
}

/**
 * Formate une date ISO (YYYY-MM-DD) en dd/mm/yyyy pour l'affichage.
 */
function formatDateFR(isoDate) {
    if (!isoDate) return '';
    const parts = isoDate.split('-');
    if (parts.length !== 3) return isoDate;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

/**
 * Cherche si une journée existe déjà avec le même nom dans la même poule.
 * La comparaison ne porte PAS sur les dates : si la journée existe (même nom, même poule),
 * on alerte l'utilisateur et on lui montre les dates existantes.
 * @param {string} nom   - Nom de la journée (ex: "Journée 1")
 * @param {string} poule - Identifiant de la poule (ex: "pouleA")
 * @returns {object|null}
 */
function scanJourneeExistante(nom, poule) {
    const nomNorm = nom.trim().toLowerCase();
    return journeesExistantes.find(
        (j) => j.nom.trim().toLowerCase() === nomNorm
            && j.poule === poule
    ) || null;
}

/**
 * Valide le champ "journée" - n'accepte que lettres, espaces et chiffres
 */
function validateJourneeInput(value) {
    const validPattern = /^[a-zA-ZÀ-ÿ0-9\s]*$/;
    return validPattern.test(value);
}

/**
 * Filtre les caractères invalides du champ "journée" lors de la saisie
 */
function filterJourneeInput(event) {
    const input = event.target;
    let currentValue = input.value;
    let filteredValue = '';

    for (let char of currentValue) {
        if (validateJourneeInput(filteredValue + char)) {
            filteredValue += char;
        }
    }

    if (filteredValue !== currentValue) {
        input.value = filteredValue;
    }
}

/**
 * Valide le formulaire d'ajout de journée avant soumission
 */
function validateJourneeForm(form) {
    const journeeInput = form.querySelector('input[name="journée"]');

    if (!journeeInput) {
        return true; // Le champ n'existe pas, continuer normalement
    }

    const journeeValue = journeeInput.value.trim();

    if (!journeeValue) {
        alert('Erreur : Le champ "journée" est obligatoire.');
        return false;
    }

    if (!validateJourneeInput(journeeValue)) {
        alert('Erreur : Le champ "journée" ne doit contenir que des lettres, des espaces et des chiffres.');
        return false;
    }

    return true;
}

// Initialisation

document.addEventListener('DOMContentLoaded', function () {
    // Récupération des données passées depuis PHP via le bloc JSON
    let isEditMode = false;
    const champDataElInit = document.getElementById('championnatData');
    if (champDataElInit) {
        try {
            const parsedInit = JSON.parse(champDataElInit.textContent);
            if (typeof parsedInit.isEditMode === 'boolean') {
                isEditMode = parsedInit.isEditMode;
            }
            if (Array.isArray(parsedInit.journeesExistantes)) {
                journeesExistantes = parsedInit.journeesExistantes;
            }
            if (Array.isArray(parsedInit.rencontresExistantes)) {
                rencontresExistantes = parsedInit.rencontresExistantes;
            }
        } catch (e) { }
    } else if (typeof window.isEditMode === 'boolean') {
        isEditMode = window.isEditMode;
    }
    window.isEditMode = isEditMode;

    // Configuration des événements pour le champ "journée"
    const journeeInput = document.querySelector('input[name="journée"]');
    if (journeeInput) {
        journeeInput.addEventListener('input', filterJourneeInput);
        journeeInput.addEventListener('change', function (event) {
            filterJourneeInput(event);
        });
        journeeInput.addEventListener('blur', function () {
            const value = this.value.trim();
            if (value && !validateJourneeInput(value)) {
                this.value = value.replace(/[^a-zA-ZÀ-ÿ0-9\s]/g, '');
            }
        });
    }

    function updateAllOptions() {
        // Ne désactiver les options que si on n'est PAS en mode édition
        if (isEditMode) {
            return;
        }

        const allSelects = document.querySelectorAll('.popup .selectEquipes');
        allSelects.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.disabled = false;
            });
        });

        const selectedValues = Array.from(allSelects)
            .map((select) => select.value)
            .filter((value) => value !== '' && value !== 'Aucune équipe');

        allSelects.forEach((select) => {
            selectedValues.forEach((value) => {
                if (select.value !== value) {
                    const optionToDisable = select.querySelector(`option[value="${value}"]`);
                    if (optionToDisable) optionToDisable.disabled = true;
                }
            });
        });
    }

    // Rendre la fonction accessible globalement pour la restauration après erreur
    window.updateAllOptions = updateAllOptions;

    function addEventListenersToSelects() {
        document.querySelectorAll('.popup .selectEquipes').forEach((select) => {
            select.addEventListener('change', updateAllOptions);
        });
    }

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
                ajoutBtn.addEventListener('click', function () {
                    const allMatchs = formGroup.querySelectorAll('.matchs');
                    const lastMatchs = allMatchs[allMatchs.length - 1];
                    const newMatchs = lastMatchs.cloneNode(true);

                    // Réinitialiser les valeurs
                    newMatchs.querySelectorAll('select').forEach((select) => {
                        select.selectedIndex = 0;
                    });
                    newMatchs.querySelectorAll('input[type="number"]').forEach((input) => (input.value = ''));

                    // Ajouter les écouteurs d'événements aux nouveaux selects
                    newMatchs.querySelectorAll('select').forEach((select) => {
                        select.addEventListener('change', updateAllOptions);
                    });

                    // Insérer avant la ligne des boutons
                    formGroup.insertBefore(newMatchs, buttonsRow);

                    updateAllOptions();
                });

                supBtn.addEventListener('click', function () {
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
                    cancelButton.addEventListener('click', function () {
                        teamPopup.style.display = 'none';
                        formAjout.reset();

                        // Supprimer les matchs supplémentaires au-delà du premier
                        const allMatchs = formGroup.querySelectorAll('.matchs');
                        while (allMatchs.length > 1) {
                            allMatchs[allMatchs.length - 1].remove();
                        }

                        updateAllOptions();
                    });
                }

                // Ajouter la validation + scan doublon au formulaire d'ajout de journée
                // Note : le bouton submit est dans .form-group, pas dans .buttons-row
                formAjout.addEventListener('submit', function (e) {
                    // 1. Validation de la saisie (champs requis et caractères autorisés)
                    if (!validateJourneeForm(this)) {
                        e.preventDefault();
                        return;
                    }

                    // 2. Récupération des valeurs du formulaire
                    const journeeInput = this.querySelector('input[name="journée"]');
                    const pouleInput = this.querySelector('input[name="poule"]');
                    const dateDebutInput = this.querySelector('input[name="dateDebut"]');
                    const dateFinInput = this.querySelector('input[name="dateFin"]');

                    const journeeVal = journeeInput ? journeeInput.value.trim() : '';
                    const pouleVal = pouleInput ? pouleInput.value.trim() : '';
                    const dateDebutVal = dateDebutInput ? dateDebutInput.value.trim() : '';
                    const dateFinVal = dateFinInput ? dateFinInput.value.trim() : '';

                    if (journeeVal && pouleVal) {
                        // Scan si une journée avec le même nom existe déjà dans cette poule
                        const doublon = scanJourneeExistante(journeeVal, pouleVal);

                        if (doublon) {
                            const dateDebutFR = formatDateFR(doublon.dateDebut);
                            const dateFinFR = formatDateFR(doublon.dateFin);

                            // Cas 1 : Même journée, mais les dates saisies sont DIFFÉRENTES de celles de la journée existante
                            if (doublon.dateDebut !== dateDebutVal || doublon.dateFin !== dateFinVal) {
                                e.preventDefault();
                                alert(`La journée "${doublon.nom}" existe déjà avec des dates différentes (${dateDebutFR} au ${dateFinFR}).\nVeuillez vérifier les dates pour la journée "${doublon.nom}".`);
                                return;
                            }

                            // Cas 2 : Vérification si une des nouvelles équipes est déjà engagée dans cette journée
                            const rencontresJournee = rencontresExistantes.filter(r => r.journee_id === doublon.id);
                            const equipesEngagees = new Set();
                            rencontresJournee.forEach(r => {
                                if (r.equipe1 && r.equipe1 !== 'Aucune équipe') equipesEngagees.add(r.equipe1);
                                if (r.equipe2 && r.equipe2 !== 'Aucune équipe') equipesEngagees.add(r.equipe2);
                            });

                            // Équipes sélectionnées dans le formulaire
                            const selectsEquipe1 = Array.from(this.querySelectorAll('select[name="equipe1[]"]')).map(s => s.value).filter(v => v && v !== 'Aucune équipe');
                            const selectsEquipe2 = Array.from(this.querySelectorAll('select[name="equipe2[]"]')).map(s => s.value).filter(v => v && v !== 'Aucune équipe');
                            const nouvellesEquipes = [...selectsEquipe1, ...selectsEquipe2];

                            const collisions = nouvellesEquipes.filter(eq => equipesEngagees.has(eq));
                            const collisionsUniques = Array.from(new Set(collisions));

                            if (collisionsUniques.length > 0) {
                                e.preventDefault();
                                const listeEquipes = collisionsUniques.join(', ');
                                if (collisionsUniques.length === 1) {
                                    alert(`${listeEquipes} est déjà engagée dans un match lors de la ${doublon.nom} (${dateDebutFR} - ${dateFinFR}).\nUne équipe ne peut disputer qu’une seule rencontre par journée.`);
                                } else {
                                    alert(`${listeEquipes} sont déjà engagées dans un match lors de la ${doublon.nom} (${dateDebutFR} - ${dateFinFR}).\nUne équipe ne peut disputer qu’une seule rencontre par journée.`);
                                }
                                return;
                            }

                            // Cas 3 : Même journée, mêmes dates, aucune collision -> demande de confirmation
                            e.preventDefault();
                            const message = `La ${doublon.nom} existe déjà dans cette poule à ces dates (${dateDebutFR} au ${dateFinFR}).\nÊtes-vous sûr de vouloir insérer ces nouvelles rencontres dans cette journée ?`;

                            if (confirm(message)) {
                                // S'assurer que le champ name="send" est présent lors du submit programmatique
                                if (!this.querySelector('input[name="send"]')) {
                                    const hiddenSend = document.createElement('input');
                                    hiddenSend.type = 'hidden';
                                    hiddenSend.name = 'send';
                                    hiddenSend.value = 'Ajouter';
                                    this.appendChild(hiddenSend);
                                }
                                // Si l'utilisateur clique sur "OK", on soumet le formulaire
                                this.submit();
                            }
                            // Si l'utilisateur clique sur "Annuler" / "Non", e.preventDefault() empêche la soumission.
                            // Le popup reste affiché avec tous les champs et rencontres saisis intacts.
                            return;
                        }
                    }
                });
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

        typeAllerBtn.addEventListener('click', function () {
            toggleTypeSelection(typeAllerBtn, typeRetourBtn);
            typeJourneeInput.value = 'aller';
        });

        typeRetourBtn.addEventListener('click', function () {
            toggleTypeSelection(typeRetourBtn, typeAllerBtn);
            typeJourneeInput.value = 'retour';
        });
    }

    // Gestion du bouton Enregistrer en mode édition
    const btnSaveChampionnat = document.getElementById('btnSaveChampionnat');
    if (btnSaveChampionnat) {
        btnSaveChampionnat.addEventListener('click', function (e) {
            e.preventDefault();
            checkAndSubmit();
        });
    }

    // Gestion du changement de poule sans inline onchange
    const pouleSelect = document.getElementById('poule');
    if (pouleSelect) {
        pouleSelect.addEventListener('change', function () {
            if (this.form) this.form.submit();
        });
    }

    // Gestion de la suppression de journée par délégation d'événements
    document.addEventListener('click', function (e) {
        const deleteLink = e.target.closest('.delete-link-journee');
        if (!deleteLink) return;

        e.preventDefault();
        const journeeId = deleteLink.dataset.journeeId;
        const journeeNom = deleteLink.dataset.journeeNom;
        const poule = deleteLink.dataset.poule;
        const csrfToken = deleteLink.dataset.csrf;

        if (journeeId && journeeNom && poule) {
            confirmerSuppression(journeeId, journeeNom, poule, csrfToken);
        }
    });

    // Restauration des données et affichage des erreurs depuis le JSON si présent
    const champDataEl = document.getElementById('championnatData');
    if (champDataEl) {
        try {
            const data = JSON.parse(champDataEl.textContent);
            const errors = data.errors || null;
            const posted = data.posted || null;
            const successMessage = data.successMessage || null;
            const teamPopup = document.getElementById('teamPopup');

            if (errors && Object.keys(errors).length > 0) {
                let alertMessage = "ERREUR(S) DE VALIDATION :\n";

                for (const groupName in errors) {
                    const list = Array.isArray(errors[groupName]) ? errors[groupName] : [errors[groupName]];
                    const isNumeric = !isNaN(groupName);
                    const isGeneric = (groupName === 'Général' || groupName === 'Journée');

                    if (!isNumeric && !isGeneric) {
                        alertMessage += `- ${groupName} :\n`;
                    }

                    list.forEach(error => {
                        const cleanError = error.replace(/\\n/g, '\n').trim();
                        alertMessage += (!isNumeric && !isGeneric) ? `   • ${cleanError}\n` : ` ${cleanError}`;
                    });
                    alertMessage += '\n';
                }

                if (teamPopup && !isEditMode) {
                    teamPopup.style.display = 'block';
                }

                if (posted) {
                    const form = teamPopup ? teamPopup.querySelector('form.form-ajout') : null;
                    if (form) {
                        if (posted.journée !== undefined) form.querySelector('input[name="journée"]')?.setAttribute('value', posted.journée);
                        if (posted.dateDebut !== undefined) form.querySelector('input[name="dateDebut"]')?.setAttribute('value', posted.dateDebut);
                        if (posted.dateFin !== undefined) form.querySelector('input[name="dateFin"]')?.setAttribute('value', posted.dateFin);

                        const typeVal = posted.type_journee ?? form.querySelector('#typeJourneeInput')?.value;
                        if (typeVal !== undefined) {
                            const input = form.querySelector('#typeJourneeInput');
                            if (input) input.value = typeVal;
                            const btnAller = document.getElementById('typeAllerBtn');
                            const btnRetour = document.getElementById('typeRetourBtn');
                            if (btnAller && btnRetour) {
                                if (typeVal === 'aller') {
                                    btnAller.classList.add('selected');
                                    btnRetour.classList.remove('selected');
                                } else {
                                    btnRetour.classList.add('selected');
                                    btnAller.classList.remove('selected');
                                }
                            }
                        }

                        const formGroup = form.querySelector('.form-group');
                        if (formGroup) {
                            const getTargetCount = (d) => {
                                let max = 1;
                                ['equipe1', 'equipe2', 'score1', 'score2'].forEach(key => {
                                    const val = d[key];
                                    if (val && Array.isArray(val)) max = Math.max(max, val.length);
                                });
                                return max;
                            };

                            const targetCount = getTargetCount(posted);
                            let allMatchs = formGroup.querySelectorAll('.matchs');
                            let guard = 0;

                            while (allMatchs.length < targetCount && allMatchs.length >= 1) {
                                guard++;
                                if (guard > 50) break;

                                const buttonsRow = formGroup.querySelector('.buttons-row');
                                const lastMatchs = allMatchs[allMatchs.length - 1];
                                if (!lastMatchs) break;

                                const clone = lastMatchs.cloneNode(true);
                                clone.querySelectorAll('input[type="number"]').forEach(inp => inp.value = '');
                                if (buttonsRow) formGroup.insertBefore(clone, buttonsRow);
                                allMatchs = formGroup.querySelectorAll('.matchs');
                            }

                            if (posted.equipe1) {
                                form.querySelectorAll('select[name="equipe1[]"]').forEach((sel, idx) => {
                                    if (posted.equipe1[idx] !== undefined) sel.value = posted.equipe1[idx];
                                });
                            }
                            if (posted.equipe2) {
                                form.querySelectorAll('select[name="equipe2[]"]').forEach((sel, idx) => {
                                    if (posted.equipe2[idx] !== undefined) sel.value = posted.equipe2[idx];
                                });
                            }
                            if (posted.score1) {
                                form.querySelectorAll('input[name="score1[]"]').forEach((inp, idx) => {
                                    if (posted.score1[idx] !== undefined) inp.value = posted.score1[idx];
                                });
                            }
                            if (posted.score2) {
                                form.querySelectorAll('input[name="score2[]"]').forEach((inp, idx) => {
                                    if (posted.score2[idx] !== undefined) inp.value = posted.score2[idx];
                                });
                            }

                            updateAllOptions();
                        }
                    }
                }

                setTimeout(() => {
                    alert(alertMessage);
                    if (isEditMode) {
                        document.querySelectorAll('tr.tabJourn').forEach(row => {
                            if (errors.hasOwnProperty(row.dataset.journeeName)) {
                                row.classList.add('row-error');
                            }
                        });
                    }
                }, 200);
            } else if (successMessage) {
                setTimeout(() => {
                    alert(successMessage.replace(/\\n/g, '\n'));
                }, 300);
            }
        } catch (e) {
            console.error('Erreur traitement données championnat:', e);
        }
    }

    // Nettoyage de l'URL pour éviter le réaffichage du message au refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.pathname);
    }
});

