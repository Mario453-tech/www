/**
 * RECRUITMENT SYSTEM - Frontend Logic
 * 
 * Obsuguje:
 * - Rozpoczynanie procesu rekrutacji
 * - Wywietlanie kandydatw
 * - Zatrudnianie wybranego kandydata
 * - Zwalnianie pracownikw
 */

var _RECL = window.REC_LANG || {};
function recl(k, p) {
    var s = _RECL[k] || k;
    if (p) Object.keys(p).forEach(function(pk) { s = s.replace(':' + pk, p[pk]); });
    return s;
}

class RecruitmentSystem {
    constructor() {
        this.apiUrl = 'src/RecruitmentAPI.php';
        this.currentRoleId = null;
        this.currentRequestId = null;
        this.selectedCandidateId = null;
        this.checkInterval = null;
        
        this.initModal();
    }
    
 /**
 * Inicjalizacja modalu rekrutacji
 */
    initModal() {
 // Sprawd czy modal ju istnieje
        if (document.getElementById('recruitment-modal')) return;
        
        const modal = document.createElement('div');
        modal.id = 'recruitment-modal';
        modal.className = 'recruitment-modal';
        modal.innerHTML = `
            <div class="recruitment-panel">
                <div class="recruitment-header">
                    <div>
                        <div class="recruitment-title">${recl('modal_title')}</div>
                        <div class="recruitment-subtitle" id="recruitment-role-name"></div>
                    </div>
                    <button class="recruitment-close" onclick="recruitment.closeModal()"></button>
                </div>
                <div class="recruitment-content" id="recruitment-content">
                    <!-- Dynamiczna zawarto -->
                </div>
                <div class="recruitment-actions" id="recruitment-actions">
                    <!-- Dynamiczne akcje -->
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
 /**
 * Rozpoczyna proces rekrutacji
 */
    async startRecruitment(roleId, roleName, waitMinutes = 1) {
        this.currentRoleId = roleId;
        
        try {
            const response = await this.apiCall('start_recruitment', {
                role_id: roleId,
                wait_minutes: waitMinutes
            });
            
            if (response.success) {
                this.currentRequestId = response.request_id;
                this.openModal(roleName);
                this.showWaitingState(response.ready_at);
                this.startStatusCheck();
            } else {
                alertError(recl('err_response') + response.error);
            }
        } catch (error) {
            console.error('startRecruitment error:', error);
            alertError(recl('err_start'));
        }
    }
    
 /**
 * Otwiera modal rekrutacji
 */
    openModal(roleName) {
        const modal = document.getElementById('recruitment-modal');
        const roleNameEl = document.getElementById('recruitment-role-name');
        
        roleNameEl.textContent = roleName;
        modal.classList.add('active');
    }
    
 /**
 * Zamyka modal rekrutacji
 */
    closeModal() {
        const modal = document.getElementById('recruitment-modal');
        modal.classList.remove('active');
        
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }
    
 /**
 * Wywietla stan oczekiwania
 */
    showWaitingState(readyAt) {
        const content = document.getElementById('recruitment-content');
        const actions = document.getElementById('recruitment-actions');
        
        content.innerHTML = `
            <div class="recruitment-waiting">
                <div class="waiting-icon">&#8987;</div>
                <div class="waiting-title">${recl('waiting_title')}</div>
                <div class="waiting-message">${recl('waiting_msg')}</div>
                <div class="waiting-timer" id="waiting-timer">--:--</div>
            </div>
        `;
        
        actions.innerHTML = `
            <button class="btn-recruitment" onclick="recruitment.closeModal()">${recl('close_btn')}</button>
        `;
        
        this.updateTimer(readyAt);
    }
    
 /**
 * Aktualizuje timer odliczania
 */
    updateTimer(readyAt) {
        const timerEl = document.getElementById('waiting-timer');
        if (!timerEl) return;
        
        const updateTime = () => {
            const now = new Date().getTime();
            const ready = new Date(readyAt.replace(' ', 'T')).getTime();
            const diff = ready - now;
            
            if (diff <= 0) {
                timerEl.textContent = '00:00';
                return;
            }
            
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            
            timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        };
        
        updateTime();
        setInterval(updateTime, 1000);
    }
    
 /**
 * Rozpoczyna cykliczne sprawdzanie statusu
 */
    startStatusCheck() {
        this.checkInterval = setInterval(async () => {
            await this.checkStatus();
        }, 5000); // Co 5 sekund
    }
    
 /**
 * Sprawdza status rekrutacji
 */
    async checkStatus() {
        if (!this.currentRequestId) return;
        
        try {
            const response = await this.apiCall('check_status', {
                request_id: this.currentRequestId
            });
            
            if (response.success && response.is_ready) {
                clearInterval(this.checkInterval);
                this.checkInterval = null;
                await this.loadCandidates();
            }
        } catch (error) {
            console.error('checkStatus error:', error);
        }
    }
    
 /**
 * aduje list kandydatw
 */
    async loadCandidates() {
        try {
            const response = await this.apiCall('get_candidates', {
                role_id: this.currentRoleId
            });
            
            if (response.success) {
                this.showCandidates(response.candidates);
            } else {
                alertError(recl('err_response') + response.error);
            }
        } catch (error) {
            console.error('loadCandidates error:', error);
            alertError(recl('err_response') + error.message);
        }
    }
    
 /**
 * Pokazuje kandydatw dla danej roli (bez rozpoczynania nowej rekrutacji)
 */
    async showCandidatesForRole(roleId, roleName) {
        this.currentRoleId = roleId;
        this.openModal(roleName);
        await this.loadCandidates();
    }
    
 /**
 * Wywietla list kandydatw
 */
    showCandidates(candidates) {
        const content = document.getElementById('recruitment-content');
        const actions = document.getElementById('recruitment-actions');
        
        if (candidates.length === 0) {
            content.innerHTML = `
                <div class="recruitment-empty">
                    <div class="empty-icon">&#128203;</div>
                    <p>${recl('no_candidates')}</p>
                </div>
            `;
            actions.innerHTML = `
                <button class="btn-recruitment" onclick="recruitment.closeModal()">${recl('close_btn')}</button>
            `;
            return;
        }

        content.innerHTML = candidates.map(c => this.renderCandidateCard(c)).join('');

        actions.innerHTML = `
            <button class="btn-recruitment" onclick="recruitment.closeModal()">${recl('cancel_btn')}</button>
            <button class="btn-recruitment primary" id="hire-btn" onclick="recruitment.hireSelected()" disabled>
                ${recl('hire_btn')}
            </button>
        `;

 // Bind card click events
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.addEventListener('click', () => {
                this.selectCandidate(card.dataset.candidateId);
            });
        });
    }
    
 /**
 * Renderuje kart kandydata
 */
    renderCandidateCard(candidate) {
        const avgSkill = (
            parseInt(candidate.skill_organization) +
            parseInt(candidate.skill_negotiation) +
            parseInt(candidate.skill_analysis) +
            parseInt(candidate.skill_stress) +
            parseInt(candidate.skill_ethics)
        ) / 5;
        
        return `
            <div class="candidate-card" data-candidate-id="${candidate.id}">
                <div class="candidate-header">
                    <div class="candidate-info">
                        <div class="candidate-name">${candidate.first_name} ${candidate.last_name}</div>
                        <div class="candidate-meta">
                            <div class="candidate-meta-item">
                                <span>&#127874;</span>
                                <span>${recl('age', { n: candidate.age })}</span>
                            </div>
                            <div class="candidate-meta-item">
                                <span>&#127757;</span>
                                <span>${this.getNationalityName(candidate.nationality)}</span>
                            </div>
                            <div class="candidate-meta-item">
                                <span>&#128188;</span>
                                <span>${recl('exp_years', { n: candidate.experience_years })}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="candidate-salary">${this.formatMoney(candidate.expected_salary)}</div>
                        <div class="candidate-salary-label">${recl('expected_salary')}</div>
                    </div>
                </div>
                
                <div class="candidate-skills">
                    ${this.renderSkill(recl('skill_org'), candidate.skill_organization)}
                    ${this.renderSkill(recl('skill_neg'), candidate.skill_negotiation)}
                    ${this.renderSkill(recl('skill_ana'), candidate.skill_analysis)}
                    ${this.renderSkill(recl('skill_str'), candidate.skill_stress)}
                    ${this.renderSkill(recl('skill_eth'), candidate.skill_ethics)}
                </div>
                
                <div class="candidate-experience">
                    <span class="experience-badge">${recl('avg_score', { avg: avgSkill.toFixed(1) })}</span>
                    <span>&middot;</span>
                    <span>${recl('expires_in', { h: candidate.hours_remaining })}</span>
                </div>
            </div>
        `;
    }
    
 /**
 * Renderuje pasek umiejtnoci
 */
    renderSkill(label, value) {
        return `
            <div class="skill-item">
                <div class="skill-label">${label}</div>
                <div class="skill-bar">
                    <div class="skill-fill" style="width: ${value * 10}%"></div>
                </div>
                <div class="skill-value">${value}/10</div>
            </div>
        `;
    }
    
 /**
 * Wybiera kandydata
 */
    selectCandidate(candidateId) {
 // Usu poprzednie zaznaczenie
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.classList.remove('selected');
        });
        
 // Zaznacz nowego kandydata
        const card = document.querySelector(`[data-candidate-id="${candidateId}"]`);
        if (card) {
            card.classList.add('selected');
            this.selectedCandidateId = candidateId;
            
 // Aktywuj przycisk zatrudnienia
            const hireBtn = document.getElementById('hire-btn');
            if (hireBtn) hireBtn.disabled = false;
        }
    }
    
 /**
 * Zatrudnia wybranego kandydata
 */
    async hireSelected() {
        if (!this.selectedCandidateId) {
            alertWarning(recl('alert_select'));
            return;
        }

        confirmAction(recl('confirm_hire'), async () => {
            try {
                const response = await this.apiCall('hire_candidate', {
                    candidate_id: this.selectedCandidateId
                });

                if (response.success) {
                    window.showGameToast(recl('modal_title'), recl('alert_hired'), 'success');
                    this.closeModal();

                    if (typeof updateBackground === 'function') {
                        updateBackground();
                    }
                    if (typeof render === 'function') {
                        render();
                    }

                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    alertError(recl('err_response') + response.error);
                }
            } catch (error) {
                console.error('hireSelected error:', error);
                alertError(recl('err_hire'));
            }
        }, { type: 'confirm', confirmLabel: recl('hire_btn') });
    }
    
 /**
 * Wywoanie API
 */
    async apiCall(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('_token', phpData.csrfToken);

        for (const key in data) {
            formData.append(key, data[key]);
        }

        const response = await fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }
    
 /**
 * Formatuje kwot pienidzy
 */
    formatMoney(amount) {
        return new Intl.NumberFormat(window.APP_LOCALE, {
            style: 'currency',
            currency: window.APP_CURRENCY,
            minimumFractionDigits: 0
        }).format(amount);
    }
    
 /**
 * Zwraca nazw narodowoci
 */
    getNationalityName(code) {
        const key = 'nat_' + code;
        const val = _RECL[key];
        return val || code;
    }
}

// Inicjalizacja systemu rekrutacji
const recruitment = new RecruitmentSystem();
