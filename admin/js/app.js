// loader
// ( éviter les collisions quand js/app.js et admin/js/app.js sont chargés sur la même page )
var loader = document.querySelector('.loader');

// Ne redéfinir hideLoader que si js/app.js ne l'a pas déjà fait (elle contient la logique data-alert)
if (typeof hideLoader !== 'function') {
    function hideLoader() {
        if (loader && !loader.classList.contains('fondu-out')) {
            loader.classList.add('fondu-out');
        }
    }
}
// Petit délai pour éviter un flash si la page est très rapide.
var LOADER_MIN_DELAY_MS = 250;
var LOADER_START_TS = Date.now();


document.addEventListener('DOMContentLoaded', function () {
    const elapsed = Date.now() - LOADER_START_TS;
    const remaining = Math.max(0, LOADER_MIN_DELAY_MS - elapsed);
    setTimeout(hideLoader, remaining);

    // === Popup (équipe) ===
    const popupOverlay = document.createElement('div');
    popupOverlay.id = 'popupOverlay';
    document.body.appendChild(popupOverlay);

    const popupForm = document.createElement('div');
    popupForm.id = 'popupForm';
    document.body.appendChild(popupForm);

    function openPopup(content) {
        popupForm.innerHTML = content;
        popupOverlay.style.display = 'block';
        popupForm.style.display = 'block';

        // Ajouter l'événement de fermeture aux boutons
        const closeBtns = popupForm.querySelectorAll('.close-popup');
        closeBtns.forEach(btn => btn.addEventListener('click', closePopup));
    }

    function closePopup() {
        popupOverlay.style.display = 'none';
        popupForm.style.display = 'none';
        popupForm.innerHTML = '';
    }

    window.showMessage = function (message) {
        const content = `
            <h3>Message</h3>
            <button class="close-popup" style="float:right;">&times;</button>
            <p>${message}</p>
            <button class="close-popup">Fermer</button>
        `;
        openPopup(content);
    };

    const showPopupLink = document.getElementById('showPopup');
    const teamPopup = document.getElementById('teamPopup');
    const cancelPopupButton = document.getElementById('cancelPopup');
    const cancelModifyUser = document.getElementById('cancelModifyUser');

    if (cancelModifyUser) {
        cancelModifyUser.addEventListener('click', function () {
            window.location.href = 'showUser.php';
        });
    }

    if (window.location.search.includes('unauthorized') && window.history.replaceState) {
        window.history.replaceState(null, '', window.location.pathname);
    }

    if (showPopupLink && teamPopup) {
        showPopupLink.addEventListener('click', function () {
            teamPopup.style.display = 'block';
        });
    }

    if (cancelPopupButton && teamPopup) {
        cancelPopupButton.addEventListener('click', function () {
            teamPopup.style.display = 'none';
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.js-confirm-delete-team');
        if (form && !confirm('Êtes-vous sûr de vouloir supprimer cette équipe ?\nCette action mettra à jour toutes les rencontres associées.')) {
            event.preventDefault();
        }
    });

        // Toggle visibilité mot de passe (délégation d'événements : marche même si le DOM est injecté/actualisé après)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.toggle-password');
        if (!btn) return;

        const targetId = btn.getAttribute('data-target');
        if (!targetId) return;

        const input = document.getElementById(targetId);
        if (!input) return;

        const isCurrentlyPassword = input.type === 'password';
        input.type = isCurrentlyPassword ? 'text' : 'password';

        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-eye', input.type === 'text');
            icon.classList.toggle('fa-eye-slash', input.type === 'password');
        }
    });

});