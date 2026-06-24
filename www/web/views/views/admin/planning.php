<?php
/**
 * views/admin/planning.php
 * Inclus dans admin/dashboard.php — onglet Planning
 * Gestion des jours de travail et congés des médecins et gestionnaires
 */
$joursLabels = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
?>
<style>
.plan-section { margin-bottom: 32px; }
.plan-section-title {
    font-size: 1rem; font-weight: 700; color: var(--slate-800,#1e293b);
    margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
}
.plan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.plan-card {
    background: white;
    border: 1.5px solid var(--slate-200,#e2e8f0);
    border-radius: 14px;
    padding: 18px;
    transition: box-shadow .2s;
}
.plan-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.plan-card-header {
    display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
}
.plan-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .95rem; flex-shrink: 0;
}
.plan-avatar.med  { background: #dbeafe; color: #1e40af; }
.plan-avatar.gest { background: #d1fae5; color: #065f46; }
.plan-card-name { font-weight: 600; font-size: .88rem; color: #1e293b; }
.plan-card-role { font-size: .72rem; color: #64748b; }
.plan-btn {
    width: 100%; padding: 8px 0;
    background: #f8fafc; border: 1.5px solid #e2e8f0;
    border-radius: 8px; cursor: pointer; font-family: inherit;
    font-size: .82rem; font-weight: 600; color: #1e40af;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: all .2s;
}
.plan-btn:hover { background: #dbeafe; border-color: #93c5fd; }

/* ── Modal planning ── */
.plan-modal-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 1100;
    align-items: center; justify-content: center; padding: 16px;
}
.plan-modal-backdrop.open { display: flex; }
.plan-modal {
    background: white; border-radius: 20px;
    width: 100%; max-width: 560px;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.plan-modal-header {
    padding: 20px 24px 0;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; background: white; z-index: 1;
    border-bottom: 1px solid #e2e8f0; padding-bottom: 14px;
}
.plan-modal-title { font-size: 1rem; font-weight: 700; color: #1e293b; }
.plan-modal-close {
    background: none; border: none; cursor: pointer;
    color: #64748b; font-size: 1.2rem; padding: 4px;
}
.plan-modal-body { padding: 20px 24px; }
.plan-modal-footer {
    padding: 14px 24px; border-top: 1px solid #e2e8f0;
    display: flex; gap: 10px; justify-content: flex-end;
    position: sticky; bottom: 0; background: white;
}

/* Jours */
.jours-grid {
    display: grid; grid-template-columns: repeat(4,1fr); gap: 8px;
    margin-bottom: 20px;
}
@media(max-width:480px){ .jours-grid { grid-template-columns: repeat(2,1fr); } }
.jour-toggle {
    display: flex; flex-direction: column; align-items: center;
    padding: 10px 6px; border: 2px solid #e2e8f0; border-radius: 10px;
    cursor: pointer; user-select: none; transition: all .2s; font-size: .78rem;
    font-weight: 600; color: #64748b; gap: 4px;
}
.jour-toggle i { font-size: 1rem; }
.jour-toggle.actif {
    border-color: #2563eb; background: #dbeafe; color: #1e40af;
}

/* Congés */
.conge-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
.conge-row {
    display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 8px; align-items: center;
}
@media(max-width:480px){ .conge-row { grid-template-columns: 1fr 1fr; gap: 6px; } }
.conge-row input {
    padding: 7px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px;
    font-family: inherit; font-size: .8rem; width: 100%;
}
.conge-del {
    background: #fee2e2; border: none; color: #dc2626; border-radius: 8px;
    width: 32px; height: 32px; cursor: pointer; display: flex;
    align-items: center; justify-content: center; flex-shrink: 0;
}
.plan-add-conge {
    background: none; border: 1.5px dashed #93c5fd; color: #2563eb;
    border-radius: 8px; padding: 7px; cursor: pointer; width: 100%;
    font-family: inherit; font-size: .8rem; font-weight: 600;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.plan-save-btn {
    background: #2563eb; color: white; border: none; border-radius: 10px;
    padding: 9px 20px; cursor: pointer; font-family: inherit;
    font-weight: 600; font-size: .875rem; display: flex; align-items: center; gap: 6px;
}
.plan-cancel-btn {
    background: #f1f5f9; color: #1e293b; border: none; border-radius: 10px;
    padding: 9px 20px; cursor: pointer; font-family: inherit; font-weight: 600; font-size: .875rem;
}
.plan-success { color: #059669; font-size: .82rem; font-weight: 600; display: none; }
.plan-label { font-size: .78rem; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .5px; }
</style>

<!-- ── Section Médecins ── -->
<div class="plan-section">
    <div class="plan-section-title">
        <i class="fas fa-user-md" style="color:#2563eb"></i> Médecins
    </div>
    <div class="plan-grid">
        <?php foreach ($medecins as $m):
            $nomAffiche = htmlspecialchars($m['nom']);
            $initiale   = mb_strtoupper(mb_substr($m['nom'], 0, 1));
            // Récupérer la spécialité depuis la table medecins si medecin_id disponible
            $specialite = '';
            if (!empty($m['medecin_id'])) {
                static $dbPlan = null;
                if (!$dbPlan) $dbPlan = \Database::getInstance()->getConnection();
                $stmtSpec = $dbPlan->prepare("SELECT specialite FROM medecins WHERE id = :id");
                $stmtSpec->execute([':id' => $m['medecin_id']]);
                $specialite = $stmtSpec->fetchColumn() ?: '';
            }
            $medecinIdPlan = $m['medecin_id'] ?? $m['id'];
        ?>
        <div class="plan-card">
            <div class="plan-card-header">
                <div class="plan-avatar med"><?= $initiale ?></div>
                <div>
                    <div class="plan-card-name"><?= $nomAffiche ?></div>
                    <div class="plan-card-role"><?= htmlspecialchars($specialite) ?></div>
                </div>
            </div>
            <button class="plan-btn" onclick="ouvrirPlanningMedecin(<?= (int)$medecinIdPlan ?>, '<?= addslashes($nomAffiche) ?>')">
                <i class="fas fa-calendar-edit"></i> Gérer le planning
            </button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($medecins)): ?>
        <p style="color:#64748b;font-size:.875rem">Aucun médecin enregistré.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ── Section Gestionnaires ── -->
<div class="plan-section">
    <div class="plan-section-title">
        <i class="fas fa-user-tie" style="color:#059669"></i> Gestionnaires
    </div>
    <div class="plan-grid">
        <?php foreach ($gestionnaires as $g):
            $nomGest  = htmlspecialchars($g['nom']);
            $initGest = mb_strtoupper(mb_substr($g['nom'], 0, 1));
            $gestIdPlan = $g['gestionnaire_id'] ?? $g['id'];
        ?>
        <div class="plan-card">
            <div class="plan-card-header">
                <div class="plan-avatar gest"><?= $initGest ?></div>
                <div>
                    <div class="plan-card-name"><?= $nomGest ?></div>
                    <div class="plan-card-role">Gestionnaire</div>
                </div>
            </div>
            <button class="plan-btn" style="color:#059669" onclick="ouvrirPlanningGestionnaire(<?= (int)$gestIdPlan ?>, '<?= addslashes($nomGest) ?>')">
                <i class="fas fa-calendar-edit"></i> Gérer le planning
            </button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($gestionnaires)): ?>
        <p style="color:#64748b;font-size:.875rem">Aucun gestionnaire enregistré.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ MODAL PLANNING ═══ -->
<div class="plan-modal-backdrop" id="planModal">
    <div class="plan-modal">
        <div class="plan-modal-header">
            <div class="plan-modal-title" id="planModalTitle">Planning</div>
            <button class="plan-modal-close" onclick="fermerPlanModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="plan-modal-body">

            <!-- Jours de travail -->
            <div class="plan-label"><i class="fas fa-calendar-check"></i> Jours de travail</div>
            <div class="jours-grid" id="joursGrid">
                <?php for ($j=1; $j<=7; $j++): ?>
                <div class="jour-toggle" data-jour="<?= $j ?>" onclick="toggleJour(this)">
                    <i class="fas fa-<?= $j===6||$j===7 ? 'umbrella-beach' : 'briefcase' ?>"></i>
                    <?= $joursLabels[$j] ?>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Congés -->
            <div class="plan-label" style="margin-top:8px"><i class="fas fa-plane-departure"></i> Congés / Absences</div>
            <div class="conge-list" id="congeList"></div>
            <button class="plan-add-conge" onclick="ajouterConge()">
                <i class="fas fa-plus"></i> Ajouter une période de congé
            </button>

            <div class="plan-success" id="planSuccess">
                <i class="fas fa-check-circle"></i> Planning sauvegardé avec succès !
            </div>
        </div>
        <div class="plan-modal-footer">
            <button class="plan-cancel-btn" onclick="fermerPlanModal()">Annuler</button>
            <button class="plan-save-btn" onclick="sauvegarderPlanning()">
                <i class="fas fa-save"></i> Sauvegarder
            </button>
        </div>
    </div>
</div>

<script>
let _planType = ''; // 'medecin' ou 'gestionnaire'
let _planId   = 0;

function ouvrirPlanningMedecin(id, nom) {
    _planType = 'medecin';
    _planId   = id;
    document.getElementById('planModalTitle').textContent = '📅 Planning — ' + nom;
    document.getElementById('planSuccess').style.display = 'none';
    // Reset jours
    document.querySelectorAll('#joursGrid .jour-toggle').forEach(el => el.classList.remove('actif'));
    document.getElementById('congeList').innerHTML = '';
    document.getElementById('planModal').classList.add('open');
    // Charger données
    fetch('admin.php?action=get_planning_medecin&medecin_id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            d.jours_travail.forEach(j => {
                const el = document.querySelector('#joursGrid .jour-toggle[data-jour="'+j+'"]');
                if (el) el.classList.add('actif');
            });
            (d.conges || []).forEach(c => ajouterConge(c.date_debut, c.date_fin, c.motif));
        });
}

function ouvrirPlanningGestionnaire(id, nom) {
    _planType = 'gestionnaire';
    _planId   = id;
    document.getElementById('planModalTitle').textContent = '📅 Planning — ' + nom;
    document.getElementById('planSuccess').style.display = 'none';
    document.querySelectorAll('#joursGrid .jour-toggle').forEach(el => el.classList.remove('actif'));
    document.getElementById('congeList').innerHTML = '';
    document.getElementById('planModal').classList.add('open');
    fetch('admin.php?action=get_planning_gestionnaire&gestionnaire_id=' + id)
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            d.jours_travail.forEach(j => {
                const el = document.querySelector('#joursGrid .jour-toggle[data-jour="'+j+'"]');
                if (el) el.classList.add('actif');
            });
            (d.conges || []).forEach(c => ajouterConge(c.date_debut, c.date_fin, c.motif));
        });
}

function fermerPlanModal() {
    document.getElementById('planModal').classList.remove('open');
}

function toggleJour(el) { el.classList.toggle('actif'); }

function ajouterConge(dd='', df='', motif='') {
    const list = document.getElementById('congeList');
    const row  = document.createElement('div');
    row.className = 'conge-row';
    row.innerHTML = `
        <input type="date" placeholder="Début" value="${dd}" class="conge-dd">
        <input type="date" placeholder="Fin"   value="${df}" class="conge-df">
        <input type="text" placeholder="Motif (optionnel)" value="${motif}" class="conge-motif">
        <button class="conge-del" onclick="this.parentElement.remove()" type="button">
            <i class="fas fa-trash"></i>
        </button>`;
    list.appendChild(row);
}

function sauvegarderPlanning() {
    const jours = [...document.querySelectorAll('#joursGrid .jour-toggle.actif')]
                    .map(el => el.dataset.jour);
    const conges = [...document.querySelectorAll('#congeList .conge-row')].map(row => ({
        date_debut: row.querySelector('.conge-dd').value,
        date_fin:   row.querySelector('.conge-df').value,
        motif:      row.querySelector('.conge-motif').value,
    }));

    const fd = new FormData();
    if (_planType === 'medecin')      fd.append('medecin_id',      _planId);
    else                              fd.append('gestionnaire_id', _planId);
    jours.forEach(j  => fd.append('jours[]', j));
    conges.forEach((c,i) => {
        fd.append(`conges[${i}][date_debut]`, c.date_debut);
        fd.append(`conges[${i}][date_fin]`,   c.date_fin);
        fd.append(`conges[${i}][motif]`,      c.motif);
    });

    const action = _planType === 'medecin'
        ? 'admin.php?action=sauvegarder_planning_medecin'
        : 'admin.php?action=sauvegarder_planning_gestionnaire';

    fetch(action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const s = document.getElementById('planSuccess');
                s.style.display = 'flex';
                setTimeout(() => { s.style.display='none'; fermerPlanModal(); }, 1500);
            }
        });
}

// Fermer modal sur clic backdrop
document.getElementById('planModal').addEventListener('click', function(e) {
    if (e.target === this) fermerPlanModal();
});
</script>