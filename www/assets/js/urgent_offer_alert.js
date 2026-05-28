/**
 * urgent_offer_alert.js - urgent offer alert handling
 * urgent_offer_alert.js - obsluga alertu pilnej oferty
 */
function closeUrgentAlert() {
        const alert = document.getElementById('urgentAlert');
        if (alert) {
            alert.style.animation = 'urgentFadeIn 0.3s ease-out reverse';
            setTimeout(() => alert.remove(), 300);
        }
    }
    
    setTimeout(() => closeUrgentAlert(), 15000);
