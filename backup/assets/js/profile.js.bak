/**
 * profile.js — obs³uga formularza profilu gracza
 * Funkcje: togglePass(), checkPassStrength()
 */
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.textContent = isText ? '' : '';
}

function checkPassStrength(val) {
    const el = document.getElementById('passStrength');
    if (!val) { el.textContent = ''; return; }
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const labels = ['', 'Bardzo s³abe', 'S³abe', 'Œrednie', 'Silne', 'Bardzo silne'];
    const colors = ['', '#e05555', '#e07b55', '#e0b44c', '#7ec97a', '#4ec97a'];
    el.textContent  = labels[score] || '';
    el.style.color  = colors[score] || '';
}

document.getElementById('confirmPass')?.addEventListener('input', function() {
    const match = document.getElementById('passMatch');
    const newVal = document.getElementById('newPass').value;
    if (!this.value) { match.textContent = ''; return; }
    if (this.value === newVal) {
        match.textContent = ' Has³a s¹ identyczne';
        match.style.color = '#4ec97a';
    } else {
        match.textContent = ' Has³a siê ró¿ni¹';
        match.style.color = '#e05555';
    }
});

function previewAndSubmitAvatar(input) {
    if (!input.files?.[0]) return;
    const file = input.files[0];
    // Podgl¹d
    const reader = new FileReader();
    reader.onload = e => {
        const prev = document.getElementById('avatarPreview');
        if (prev.tagName === 'IMG') {
            prev.src = e.target.result;
        } else {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'profile-avatar-img';
            img.id = 'avatarPreview';
            prev.replaceWith(img);
        }
    };
    reader.readAsDataURL(file);
    // Auto-submit
    document.getElementById('avatarForm').submit();
}
