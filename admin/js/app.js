document.addEventListener('DOMContentLoaded', function () {
    // Création des éléments de popup dynamiques
    const popupOverlay = document.createElement('div');
    popupOverlay.id = 'popupOverlay';
    document.body.appendChild(popupOverlay);

    const popupForm = document.createElement('div');
    popupForm.id = 'popupForm';
    document.body.appendChild(popupForm);

    // Fonction pour ouvrir une popup avec contenu
    function openPopup(content) {
        popupForm.innerHTML = content;
        popupOverlay.style.display = 'block';
        popupForm.style.display = 'block';

        // Ajouter l'événement de fermeture aux boutons
        const closeBtns = popupForm.querySelectorAll('.close-popup');
        closeBtns.forEach(btn => btn.addEventListener('click', closePopup));
    }

    // Fonction pour fermer la popup
    function closePopup() {
        popupOverlay.style.display = 'none';
        popupForm.style.display = 'none';
        popupForm.innerHTML = '';
    }

    // Fonction pour afficher un message dans une popup
    window.showMessage = function (message) {
        const content = `
            <h3>Message</h3>
            <button class="close-popup" style="float:right;">&times;</button>
            <p>${message}</p>
            <button class="close-popup">Fermer</button>
        `;
        openPopup(content);
    };

    // Gestion des popups statiques (équipe)
    const showPopupLink = document.getElementById('showPopup');
    const teamPopup = document.getElementById('teamPopup');
    const closePopupButton = document.getElementById('closePopup');
    const cancelPopupButton = document.getElementById('cancelPopup');

    if (showPopupLink && teamPopup) {
        showPopupLink.addEventListener('click', function () {
            teamPopup.style.display = 'block';
        });
    }

    // if (closePopupButton && teamPopup) {
    //     closePopupButton.addEventListener('click', function () {
    //         teamPopup.style.display = 'none';
    //     });
    // }

    if (cancelPopupButton && teamPopup) {
        cancelPopupButton.addEventListener('click', function () {
            teamPopup.style.display = 'none';
        });
    }
});

