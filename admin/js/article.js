// article.js - Gestion complète popup + AJAX (inspiré galerieImg.js)
const CSRF_TOKEN = window.CSRF_TOKEN || '';

function openPopup(popupId) {
    document.getElementById(popupId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePopup(popupId = null) {
    if (popupId) document.getElementById(popupId).style.display = 'none';
    else document.querySelectorAll('.popup').forEach(p => p.style.display = 'none');
    document.body.style.overflow = 'auto';
}

document.addEventListener('DOMContentLoaded', function() {
    // Ouvrir popup
    document.getElementById('showArticlePopup')?.addEventListener('click', () => {
        openPopup('articlePopup');
        document.getElementById('popupTitle').textContent = 'Ajouter Article';
        document.getElementById('articleMode').value = 'add';
        document.getElementById('articleId').value = '';
        document.getElementById('articleForm').reset();
    });

    // Fermer popup
    document.querySelectorAll('.popup').forEach(popup => {
        popup.addEventListener('click', (e) => {
            if (e.target === popup) closePopup();
        });
    });

    // Validation image (1 seule)
    const imgInput = document.querySelector('input[name="img_article"]');
    if (imgInput) {
        imgInput.addEventListener('change', function() {
            if (this.files.length > 1) {
                alert('Une seule image autorisée !');
                this.value = '';
                return;
            }
            const file = this.files[0];
            if (file && !file.type.match('image/(jpeg|png)')) {
                alert('JPG ou PNG uniquement !');
                this.value = '';
            }
        });
    }

    // Soumission form
    document.getElementById('articleForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('csrf_token', CSRF_TOKEN);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Erreur');
            }
        })
        .catch(err => alert('Erreur réseau'));
    });
});

function editArticle(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_article&csrf_token=${CSRF_TOKEN}&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            openPopup('articlePopup');
            document.getElementById('popupTitle').textContent = 'Modifier Article';
            document.getElementById('articleMode').value = 'edit';
            document.getElementById('articleId').value = id;
            document.getElementById('titre_article').value = data.article.titre || '';
            document.getElementById('date_article').value = data.article.date_evenement || '';
            document.getElementById('contenu_article').value = data.article.content_text || '';
        }
    });
}

function deleteArticle(id) {
    if (confirm('Supprimer cet article ?')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_article&csrf_token=${CSRF_TOKEN}&id=${id}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur suppression');
            }
        });
    }
}

// ESC ferme popup
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePopup();
});
