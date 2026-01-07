document.addEventListener('DOMContentLoaded', function (e) {
    e.preventDefault();
    const popupOverlay = document.createElement('div');
    popupOverlay.id = 'popupOverlay';
    document.body.appendChild(popupOverlay);

    const popupForm = document.createElement('div');
    popupForm.id = 'popupForm';
    document.body.appendChild(popupForm);

    // Function to open popup with content
    function openPopup(content) {
        popupForm.innerHTML = content;
        popupOverlay.style.display = 'block';
        popupForm.style.display = 'block';

        // Add close button event
        const closeBtn = popupForm.querySelector('.close-popup');
        if (closeBtn) {
            closeBtn.addEventListener('click', closePopup);
        }
    }

    // Function to close popup
    function closePopup() {
        popupOverlay.style.display = 'none';
        popupForm.style.display = 'none';
        popupForm.innerHTML = '';
    }

    // Event listeners for buttons
    // const btnAjouter = document.getElementById('btnAjouter');
    // const btnModifier = document.getElementById('btnModifier');

    // btnAjouter.addEventListener('click', function (e) {
    //     e.preventDefault();
    //     const content = `
    //         <h3>Ajouter une journÃ©e / rencontre</h3>
    //         <button class="close-popup" style="float:right;">&times;</button>
    //         <form id="formAjouter" method="post">
    //             <label>Date dÃ©but (JJ/MM/AAAA):</label><br/>
    //             <input type="text" name="dateDebut" required pattern="\\d{2}/\\d{2}/\\d{4}" /><br/>
    //             <label>Date fin (JJ/MM/AAAA):</label><br/>
    //             <input type="text" name="dateFin" required pattern="\\d{2}/\\d{2}/\\d{4}" /><br/>
    //             <table>
    //                 <thead>
    //                     <tr>
    //                         <th>Ã‰quipe 1</th>
    //                         <th>Score 1</th>
    //                         <th>Score 2</th>
    //                         <th>Ã‰quipe 2</th>
    //                     </tr>
    //                 </thead>
    //                 <tbody>
    //                     <tr>
    //                         <td><input type="text" name="equipe1[]" required /></td>
    //                         <td><input type="number" name="score1[]" min="0" /></td>
    //                         <td><input type="number" name="score2[]" min="0" /></td>
    //                         <td><input type="text" name="equipe2[]" required /></td>
    //                     </tr>
    //                 </tbody>
    //             </table>
    //             <button type="submit" class="btn btn-success">Ajouter</button>
    //             <button type="button" class="btn close-popup">Annuler</button>
    //         </form>
    //     `;
    //     openPopup(content);
    //     attachFormEvents();
    // });

    // btnModifier.addEventListener('click', function (e) {
    //     e.preventDefault();
    //     const content = `
    //         <h3>Modifier une journÃ©e / rencontre</h3>
    //         <button class="close-popup" style="float:right;">&times;</button>
    //         <form id="formModifier" method="post">
    //             <label>Date dÃ©but (JJ/MM/AAAA):</label><br/>
    //             <input type="text" name="dateDebut" required pattern="\\d{2}/\\d{2}/\\d{4}" /><br/>
    //             <label>Date fin (JJ/MM/AAAA):</label><br/>
    //             <input type="text" name="dateFin" required pattern="\\d{2}/\\d{2}/\\d{4}" /><br/>
    //             <table>
    //                 <thead>
    //                     <tr>
    //                         <th>Ã‰quipe 1</th>
    //                         <th>Score 1</th>
    //                         <th>Score 2</th>
    //                         <th>Ã‰quipe 2</th>
    //                     </tr>
    //                 </thead>
    //                 <tbody>
    //                     <tr>
    //                         <td><input type="text" name="equipe1[]" required /></td>
    //                         <td><input type="number" name="score1[]" min="0" /></td>
    //                         <td><input type="number" name="score2[]" min="0" /></td>
    //                         <td><input type="text" name="equipe2[]" required /></td>
    //                     </tr>
    //                 </tbody>
    //             </table>
    //             <button type="submit" class="btn btn-primary">Modifier</button>
    //             <button type="button" class="btn close-popup">Annuler</button>
    //         </form>
    //     `;
    //     openPopup(content);
    //     attachFormEvents();
    // });

    // // Attach form submit and cancel events
    // function attachFormEvents() {
    //     const formAjouter = document.getElementById('formAjouter');
    //     if (formAjouter) {
    //         formAjouter.addEventListener('submit', function (e) {
    //             e.preventDefault();
    //             // TODO: Ajouter la logique d'envoi AJAX ou soumission classique
    //             alert('Ajout soumis (fonctionnalitÃ© Ã  implÃ©menter)');
    //             closePopup();
    //         });
    //     }
    //     const formModifier = document.getElementById('formModifier');
    //     if (formModifier) {
    //         formModifier.addEventListener('submit', function (e) {
    //             e.preventDefault();
    //             // TODO: Ajouter la logique d'envoi AJAX ou soumission classique
    //             alert('Modification soumise (fonctionnalitÃ© Ã  implÃ©menter)');
    //             closePopup();
    //         });
    //     }
    //     const cancelButtons = popupForm.querySelectorAll('.close-popup');
    //     cancelButtons.forEach(btn => btn.addEventListener('click', closePopup));
    // }
});
// Popup Ajouter JournÃ©e
document.addEventListener('DOMContentLoaded', function() {
    var showPopupLink = document.getElementById('showPopup');
    var teamPopup = document.getElementById('teamPopup');
    var closePopupButton = document.getElementById('closePopup');

    if (showPopupLink) {
        showPopupLink.addEventListener('click', function() {
            if (teamPopup) {
                teamPopup.style.display = 'block';
            }
        });
    }

    if (closePopupButton) {
        closePopupButton.addEventListener('click', function() {
            if (teamPopup) {
                teamPopup.style.display = 'none';
            }
        });
    }
});

// Popup Ajouter Ã‰quipe
document.addEventListener('DOMContentLoaded', function() {
    var showPopupLink = document.getElementById('showPopup');
    var teamPopup = document.getElementById('teamPopup');
    var closePopupButton = document.getElementById('closePopup');

    if (showPopupLink) {
        showPopupLink.addEventListener('click', function() {
            if (teamPopup) {
                teamPopup.style.display = 'block';
            }
        });
    }

    if (closePopupButton) {
        closePopupButton.addEventListener('click', function() {
            if (teamPopup) {
                teamPopup.style.display = 'none';
            }
        });
    }
});

