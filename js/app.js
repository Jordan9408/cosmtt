// loader
// ( éviter les collisions quand js/app.js et admin/js/app.js sont chargés sur la même page )
var loader = document.querySelector('.loader');


// Fonction unique pour masquer le loader et afficher les notifications flash/alertes
function hideLoader() {
    if (loader && !loader.classList.contains('fondu-out')) {
        loader.classList.add('fondu-out');
    }

    // Attendre la fin de la transition avant d'afficher l'alerte (une seule fois)
    if (!window.__alertHandled) {
        window.__alertHandled = true;
        setTimeout(function () {
            const main = document.querySelector('main') || document.querySelector('body');
            if (main) {
                const alertMsg = main.dataset.alert;
                const error = main.dataset.error;
                const success = main.dataset.success;
                const redirect = main.dataset.redirect;

                // Nettoyage immédiat des attributs pour éviter tout double affichage
                delete main.dataset.alert;
                delete main.dataset.error;
                delete main.dataset.success;
                delete main.dataset.redirect;

                const message = alertMsg || error || success;
                if (message) {
                    alert(message);
                    if (redirect) {
                        window.location.href = redirect;
                    }
                }
            }
        }, 300); // Délai pour la transition de fondu-out (0.2s + marge)
    }
}

// Attendre que TOUT soit chargé (images, styles, données PHP rendues)
// + petit délai pour éviter le flash si la page est très rapide.
var LOADER_MIN_DELAY_MS = 250;
var LOADER_START_TS = Date.now();


if (document.readyState === 'loading') {
    window.addEventListener('load', () => {
        const elapsed = Date.now() - LOADER_START_TS;
        const remaining = Math.max(0, LOADER_MIN_DELAY_MS - elapsed);
        setTimeout(hideLoader, remaining);
    });
} else {
    const elapsed = Date.now() - LOADER_START_TS;
    const remaining = Math.max(0, LOADER_MIN_DELAY_MS - elapsed);
    setTimeout(hideLoader, remaining);
}



// menu toggle

let toggle = document.querySelector(".toggle");
let menu = document.querySelector("#menu");

if (toggle && menu) {
    toggle.addEventListener("click", function () {
        menu.classList.toggle("open");
        document.body.classList.toggle("no-scroll");
    });
}

// page connexion

// Afficher et Masquer Password (toggle pour TOUS les champs .mdp)
document.addEventListener('DOMContentLoaded', function () {
    const mdpBlocks = document.querySelectorAll('.mdp');

    mdpBlocks.forEach(function (block) {
        const input = block.querySelector('input');
        const showBtn = block.querySelector('i');
        if (!input || !showBtn) return;

        // Évite d'empiler plusieurs handlers si le script est relancé
        showBtn.onclick = function () {
            if (input.type === "password") {
                input.type = "text";
                showBtn.classList.add('active');
            } else {
                input.type = "password";
                showBtn.classList.remove('active');
            }
        };
    });
});


// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function () {
    // Sélection des éléments HTML
    const forgotLink = document.getElementById('forgot');
    const mainConnect = document.querySelector('.main-connect');
    const mainChangePassword = document.querySelector('.main-change-password');
    const cancelButton = document.querySelector('[name="annuler"]');

    // Gestion du clic sur le lien "Mot de passe oublié"
    if (forgotLink) {
        forgotLink.addEventListener('click', function (e) {
            e.preventDefault();
            mainConnect.style.display = 'none';
            mainChangePassword.style.display = 'block';
        });
    }
    // Gestion du bouton "Annuler"
    if (cancelButton) {
        cancelButton.addEventListener('click', function (e) {
            e.preventDefault();
            mainChangePassword.style.display = 'none';
            mainConnect.style.display = 'block';
        });
    }
});

// fin page connexion

// Popup
document.addEventListener('DOMContentLoaded', function () {
    var showPopupLink = document.getElementById('showPopup');
    var teamPopup = document.getElementById('teamPopup');
    var closePopupButton = document.getElementById('closePopup');

    if (showPopupLink && teamPopup) {
        showPopupLink.addEventListener('click', function () {
            teamPopup.style.display = 'block';
        });
    }

    if (closePopupButton && teamPopup) {
        closePopupButton.addEventListener('click', function () {
            teamPopup.style.display = 'none';
        });
    }
});

// Lightbox pour les images d'articles (.article-image et #imageModal)
document.addEventListener('DOMContentLoaded', function () {
    const pouleSelect = document.getElementById('poule');
    if (pouleSelect && pouleSelect.form) {
        pouleSelect.addEventListener('change', function () {
            this.form.submit();
        });
    }

    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('lightboxImage');
    const closeBtn = document.getElementById('imageModalClose');

    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        modal.setAttribute('aria-hidden', 'true');
    }

    function openModal(src) {
        if (!modal || !modalImg) return;
        modalImg.src = src;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        modal.setAttribute('aria-hidden', 'false');
    }

    window.openImageModal = openModal;

    // Écouteur délégué sur les images d'article pour éviter les onclick inline
    document.addEventListener('click', function (e) {
        const target = e.target;
        if (target && target.classList.contains('article-image') && target.dataset.fullSrc) {
            openModal(target.dataset.fullSrc);
        } else if (target && target.classList.contains('article-image') && target.src) {
            openModal(target.src);
        }
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
});

