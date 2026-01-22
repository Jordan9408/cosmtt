// loader
const loader = document.querySelector('.loader');

// Fonction pour masquer le loader
function hideLoader() {
    if (loader) {
        loader.classList.add('fondu-out');
        // Attendre la fin de la transition avant d'afficher l'alerte
        setTimeout(function() {
            if (window.errorToAlert) {
                alert(window.errorToAlert);
                window.errorToAlert = null; // Réinitialiser pour éviter de répéter
            }
            if (window.successToAlert) {
                alert(window.successToAlert);
                window.successToAlert = null; // Réinitialiser pour éviter de répéter
            }
        }, 300); // Délai pour la transition de fondu-out (0.2s + marge)
    }
}

// Attendre que TOUT soit chargé (images, styles, données PHP rendues)
if (document.readyState === 'loading') {
    // Si le document est encore en cours de chargement
    window.addEventListener('load', hideLoader);
} else {
    // Si le document est déjà chargé
    hideLoader();
}

// Aussi ajouter un délai de sécurité au cas où
setTimeout(hideLoader, 2000);
// menu toggle
let toggle = document.querySelector(".toggle");
let menu = document.querySelector("#menu");

if (toggle && menu) {
    toggle.addEventListener("click", function () {
        menu.classList.toggle("open");
    });
}

// page connexion

// Afficher et Masquer Password
let input = document.querySelector('.mdp input');
let showBtn = document.querySelector('.mdp i');
if (showBtn) {
    showBtn.onclick = function () {
        if (input.type === "password") {
            input.type = "text";
            showBtn.classList.add('active');
        } else {
            input.type = "password";
            showBtn.classList.remove('active');
        }
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Sélection des éléments HTML
    const forgotLink = document.getElementById('forgot');
    const mainConnect = document.querySelector('.main-connect');
    const mainChangePassword = document.querySelector('.main-change-password');
    const cancelButton = document.querySelector('[name="annuler"]');
    
    // Gestion du clic sur le lien "Mot de passe oublié"
    if (forgotLink) { 
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            mainConnect.style.display = 'none'; 
            mainChangePassword.style.display = 'block'; 
        });
    }
    // Gestion du bouton "Annuler"
    if (cancelButton) {
        cancelButton.addEventListener('click', function(e) {
            e.preventDefault();
            mainChangePassword.style.display = 'none';
            mainConnect.style.display = 'block';
        });
    }
});

// fin page connexion

// Popup
document.addEventListener('DOMContentLoaded', function() {
    var showPopupLink = document.getElementById('showPopup');
    var teamPopup = document.getElementById('teamPopup');
    var closePopupButton = document.getElementById('closePopup');

    if (showPopupLink && teamPopup) {
        showPopupLink.addEventListener('click', function() {
            teamPopup.style.display = 'block';
        });
    }

    if (closePopupButton && teamPopup) {
        closePopupButton.addEventListener('click', function() {
            teamPopup.style.display = 'none';
        });
    }
});
