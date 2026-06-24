<?php
/**
 * views/medecin/consultation_embed.php
 * Vue embarquée dans le dashboard admin pour les directeurs-médecins.
 * Ne contient PAS de layout HTML global (pas de <html>/<head>/<body>).
 * Les variables $medecin, $affectation, $stats, $consultations sont
 * préparées par AdminController::afficherDashboard() avant inclusion.
 */

// Sécurité : accessible uniquement via include (pas en accès direct)
if (!defined('QUEUECARE_EMBED')) {
    http_response_code(403);
    exit('Accès interdit.');
}

// Valeurs par défaut (même logique que dashboard.php)
if (!isset($medecin)      || !$medecin)      $medecin      = [];
if (!isset($affectation)  || !$affectation)  $affectation  = null;
if (!isset($stats)        || !$stats)        $stats        = ['total'=>0,'traitees'=>0,'en_attente'=>0,'absentes'=>0,'annulees'=>0,'duree_moy_sec'=>0];
if (!isset($consultations)|| !$consultations)$consultations= [];

$medecinNom = ($medecin['prenom'] ?? '') . ' ' . ($medecin['nom'] ?? '');
$medecinNom = trim($medecinNom) ?: ($_SESSION['medecin_nom'] ?? 'Directeur');
$ssNom      = $affectation['ss_nom']      ?? '—';
$serviceNom = $affectation['service_nom'] ?? '—';
$hasService = !empty($affectation['ss_id']);
?>

<style>
/* ── Styles embed consultation (préfixés emb- pour éviter conflits admin) ── */
.emb-wrap          { padding: 0; }
.emb-info-banner   { background: linear-gradient(135deg,#0052a0,#1a73c8); color:#fff; border-radius:14px; padding:18px 22px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.emb-info-banner h4{ margin:0; font-size:1.05rem; font-weight:700; }
.emb-info-banner p { margin:4px 0 0; font-size:.82rem; opacity:.85; }
.emb-badge-ss      { background:rgba(255,255,255,.18); border-radius:8px; padding:6px 14px; font-size:.8rem; font-weight:600; }
.emb-stats-grid    { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:12px; margin-bottom:22px; }
.emb-stat          { background:#fff; border-radius:12px; padding:14px 10px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.emb-stat-icon     { font-size:1.3rem; margin-bottom:4px; }
.emb-stat-val      { font-size:1.6rem; font-weight:800; color:#0052a0; line-height:1; }
.emb-stat-lbl      { font-size:.7rem; color:#64748b; margin-top:4px; }
.emb-section-hdr   { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
.emb-section-title { font-size:1rem; font-weight:700; color:#0052a0; }
.emb-search        { display:flex; align-items:center; gap:8px; background:#1e293b; border:1.5px solid #334155; border-radius:12px; padding:4px 4px 4px 14px; margin-bottom:14px; }
.emb-search input  { border:none; background:transparent; outline:none; flex:1; font-size:.85rem; color:#e2e8f0; }
.emb-search input::placeholder { color:#64748b; }
.emb-search-filter-btn { display:flex; align-items:center; gap:8px; background:linear-gradient(135deg,#6d5ce7,#5b4bd4); color:#fff; border:none; border-radius:9px; padding:10px 18px; font-size:.85rem; font-weight:600; cursor:pointer; flex-shrink:0; white-space:nowrap; }
.emb-table         { width:100%; border-collapse:collapse; font-size:.82rem; }
.emb-table th      { background:#f8fafc; padding:9px 10px; text-align:left; font-weight:700; color:#475569; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
.emb-table td      { padding:9px 10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.emb-table tr:hover td { background:#f8fafc; }
.emb-rang          { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:#e2e8f0; font-weight:700; font-size:.8rem; }
.emb-rang.current  { background:#0052a0; color:#fff; }
.emb-badge         { display:inline-block; padding:3px 10px; border-radius:12px; font-size:.72rem; font-weight:700; }
.emb-badge-success { background:#d1fae5; color:#065f46; }
.emb-badge-info    { background:#dbeafe; color:#1e40af; }
.emb-badge-warning { background:#fef3c7; color:#92400e; }
.emb-badge-danger  { background:#fee2e2; color:#991b1b; }
.emb-badge-secondary { background:#f1f5f9; color:#475569; }
.emb-badge-pause   { background:#ede9fe; color:#5b21b6; }
.emb-btn           { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border:none; border-radius:8px; cursor:pointer; font-size:.76rem; font-weight:600; transition:.15s; }
.emb-btn-start     { background:#0052a0; color:#fff; }
.emb-btn-start:hover{ background:#003f7f; }
.emb-btn-end       { background:#059669; color:#fff; }
.emb-btn-end:hover { background:#047857; }
.emb-btn-absent    { background:#f59e0b; color:#fff; }
.emb-btn-absent:hover{ background:#d97706; }
.emb-btn-pause     { background:#7c3aed; color:#fff; }
.emb-btn-pause:hover{ background:#6d28d9; }
.emb-btn-resume    { background:#2563eb; color:#fff; }
.emb-btn-resume:hover{ background:#1d4ed8; }
.emb-btn-rdv       { background:#475569; color:#fff; }
.emb-btn-rdv:hover { background:#334155; }
.emb-btn-refresh   { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
.emb-btn-refresh:hover{ background:#e2e8f0; }
.emb-empty         { text-align:center; padding:32px; color:#94a3b8; font-size:.9rem; }
.emb-msg           { padding:10px 16px; border-radius:8px; margin-bottom:14px; font-size:.84rem; font-weight:600; display:none; }
.emb-msg-success   { background:#d1fae5; color:#065f46; }
.emb-msg-error     { background:#fee2e2; color:#991b1b; }
/* embMsg doit rester visible même quand une modale embed (z-index 9999)
   est ouverte, sinon le message reste caché derrière elle. */
#embMsg.emb-msg {
    position:fixed; top:16px; left:16px; right:16px; z-index:10000;
    max-width:520px; margin:0 auto;
    box-shadow:0 8px 24px rgba(0,0,0,.18);
}
/* modal pause */
.emb-modal-bg      { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:9999; align-items:center; justify-content:center; }
.emb-modal-bg.open { display:flex; }
.emb-modal         { background:#fff; border-radius:16px; padding:28px; width:100%; max-width:440px; box-shadow:0 8px 32px rgba(0,0,0,.15); }
.emb-modal h5      { margin:0 0 14px; font-size:1rem; color:#0052a0; }
.emb-modal textarea{ width:100%; min-height:80px; border:1px solid #e2e8f0; border-radius:8px; padding:10px; font-size:.85rem; resize:vertical; box-sizing:border-box; }
.emb-modal-btns    { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; }
/* Erreur affichée DIRECTEMENT dans une modale embed : pas besoin de la
   fermer pour lire pourquoi l'action a échoué. */
.emb-modal-error {
    display:none; align-items:flex-start; gap:8px;
    background:#fee2e2; color:#991b1b;
    border-radius:8px; padding:10px 14px; font-size:.82rem; margin-bottom:14px;
}
.emb-modal-error.show { display:flex; }
.emb-pagination    { display:flex; gap:6px; flex-wrap:wrap; margin-top:12px; }
.emb-page-btn      { padding:5px 11px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; cursor:pointer; font-size:.78rem; }
.emb-page-btn.active{ background:#0052a0; color:#fff; border-color:#0052a0; }
@media(max-width:600px){ .emb-table{ font-size:.74rem; } .emb-table th,.emb-table td{ padding:7px 6px; } .emb-btn{ padding:4px 7px; font-size:.7rem; } }
</style>

<div class="emb-wrap">

    <!-- Bannière info médecin -->
    <div class="emb-info-banner">
        <div>
            <h4><i class="fas fa-user-doctor"></i> Dr <?= htmlspecialchars($medecinNom) ?> — Mode consultation</h4>
            <p>Vous consultez en tant que directeur-médecin. Toutes vos actions sont enregistrées normalement.</p>
        </div>
        <?php if ($hasService): ?>
        <div class="emb-badge-ss">
            <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($serviceNom) ?> › <?= htmlspecialchars($ssNom) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Message feedback -->
    <div id="embMsg" class="emb-msg"></div>

    <!-- Stats du jour -->
    <div class="emb-stats-grid">
        <div class="emb-stat">
            <div class="emb-stat-icon" style="color:#0052a0;"><i class="fas fa-users"></i></div>
            <div class="emb-stat-val" id="embStatTotal"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="emb-stat-lbl">Total</div>
        </div>
        <div class="emb-stat">
            <div class="emb-stat-icon" style="color:#059669;"><i class="fas fa-circle-check"></i></div>
            <div class="emb-stat-val" id="embStatTraitees"><?= (int)($stats['traitees'] ?? 0) ?></div>
            <div class="emb-stat-lbl">Traitées</div>
        </div>
        <div class="emb-stat">
            <div class="emb-stat-icon" style="color:#f59e0b;"><i class="fas fa-hourglass-half"></i></div>
            <div class="emb-stat-val" id="embStatAttente"><?= (int)($stats['en_attente'] ?? 0) ?></div>
            <div class="emb-stat-lbl">En attente</div>
        </div>
        <div class="emb-stat">
            <div class="emb-stat-icon" style="color:#ef4444;"><i class="fas fa-user-slash"></i></div>
            <div class="emb-stat-val" id="embStatAbsents"><?= (int)($stats['absentes'] ?? 0) ?></div>
            <div class="emb-stat-lbl">Absents</div>
        </div>
        <div class="emb-stat">
            <div class="emb-stat-icon" style="color:#6366f1;"><i class="fas fa-ban"></i></div>
            <div class="emb-stat-val" id="embStatAnnulees"><?= (int)($stats['annulees'] ?? 0) ?></div>
            <div class="emb-stat-lbl">Annulées</div>
        </div>
        <div class="emb-stat">
            <div class="emb-stat-icon" style="color:#0891b2;"><i class="fas fa-clock"></i></div>
            <div class="emb-stat-val" id="embStatDuree"><?php $dm=(int)($stats['duree_moy_sec']??0); echo $dm>0?round($dm/60).'min':'—'; ?></div>
            <div class="emb-stat-lbl">Durée moy.</div>
        </div>
    </div>

    <!-- En-tête section consultations -->
    <div class="emb-section-hdr">
        <span class="emb-section-title"><i class="fas fa-calendar-day"></i> Consultations du jour — <span id="embConsultCount"><?= count($consultations) ?></span> patient(s)</span>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if (!empty($consultations)): ?>
            <button class="emb-btn emb-btn-absent" onclick="embAnnulerToutes()" title="Reporter toutes les consultations au lendemain en priorité">
                <i class="fas fa-calendar-plus"></i> Reporter au lendemain
            </button>
            <?php endif; ?>
            <button class="emb-btn emb-btn-refresh" onclick="embRafraichir()">
                <i class="fas fa-rotate-right"></i> Actualiser
            </button>
        </div>
    </div>

    <!-- Barre de recherche -->
    <div class="emb-search">
        <i class="fas fa-magnifying-glass" style="color:#94a3b8;"></i>
        <input type="text" id="embSearch" placeholder="Rechercher un patient (nom, prénom, téléphone)…" oninput="embFiltrer(this.value)">
        <button style="border:none;background:transparent;cursor:pointer;color:#94a3b8;" onclick="document.getElementById('embSearch').value='';embFiltrer('');" title="Effacer"><i class="fas fa-xmark"></i></button>
        <button class="emb-search-filter-btn" onclick="embFiltrer(document.getElementById('embSearch').value)"><i class="fas fa-filter"></i> Filtrer</button>
    </div>

    <!-- Tableau consultations -->
    <div style="overflow-x:auto;">
        <table class="emb-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Téléphone</th>
                    <th>Heure prévue</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="embTableBody">
                <?php if (empty($consultations)): ?>
                <tr><td colspan="8" class="emb-empty"><i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>Aucune consultation programmée pour aujourd'hui.</td></tr>
                <?php else: ?>
                <?php foreach ($consultations as $c): ?>
                <?php
                    $statutClass = match($c['statut']) {
                        'traite'    => 'emb-badge-success',
                        'en_cours'  => 'emb-badge-info',
                        'en_pause'  => 'emb-badge-pause',
                        'absent'    => 'emb-badge-warning',
                        'annule'    => 'emb-badge-danger',
                        default     => 'emb-badge-secondary',
                    };
                    $statutLabel = match($c['statut']) {
                        'traite'    => 'Traitée',
                        'en_cours'  => 'En cours',
                        'en_pause'  => 'En pause',
                        'absent'    => 'Absent',
                        'annule'    => 'Annulée',
                        'en_attente'=> 'En attente',
                        'confirme'  => 'Confirmé',
                        default     => $c['statut'],
                    };
                ?>
                <tr data-id="<?= $c['id'] ?>">
                    <td><span class="emb-rang <?= $c['statut']==='en_cours'?'current':'' ?>"><?= (int)$c['rang'] ?></span></td>
                    <td><strong><?= htmlspecialchars(($c['patient_nom']??'') . ' ' . ($c['patient_prenom']??'')) ?></strong></td>
                    <td><?= htmlspecialchars($c['telephone'] ?? $c['patient_telephone'] ?? '—') ?></td>
                    <td><?= !empty($c['heure_passage_estimee']) ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td>
                    <td><?= !empty($c['heure_debut_reelle'])    ? date('H:i', strtotime($c['heure_debut_reelle']))    : '—' ?></td>
                    <td><?= !empty($c['heure_fin_reelle'])      ? date('H:i', strtotime($c['heure_fin_reelle']))      : '—' ?></td>
                    <td><span class="emb-badge <?= $statutClass ?>"><?= $statutLabel ?></span></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <?php if ($c['statut'] === 'en_cours'): ?>
                            <button class="emb-btn emb-btn-pause"  onclick="embPause(<?= $c['id'] ?>)"><i class="fas fa-pause"></i> Pause</button>
                            <button class="emb-btn emb-btn-end"    onclick="embTerminer(<?= $c['id'] ?>)"><i class="fas fa-check"></i> Terminer</button>
                            <button class="emb-btn emb-btn-rdv"    onclick="embOuvrirRdv(<?= $c['id'] ?>, '<?= htmlspecialchars($c['patient_nom'].' '.$c['patient_prenom']) ?>')"><i class="fas fa-calendar-plus"></i></button>
                        <?php elseif ($c['statut'] === 'en_pause'): ?>
                            <button class="emb-btn emb-btn-resume" onclick="embReprendre(<?= $c['id'] ?>)"><i class="fas fa-play"></i> Reprendre</button>
                            <button class="emb-btn emb-btn-end"    onclick="embTerminer(<?= $c['id'] ?>)"><i class="fas fa-check"></i> Terminer</button>
                        <?php elseif (in_array($c['statut'], ['en_attente','confirme'])): ?>
                            <button class="emb-btn emb-btn-start"  onclick="embDemarrer(<?= $c['id'] ?>)"><i class="fas fa-play"></i> Démarrer</button>
                        <?php endif; ?>
                        <?php if (!in_array($c['statut'], ['traite','annule','absent'])): ?>
                            <button class="emb-btn emb-btn-absent" onclick="embAbsent(<?= $c['id'] ?>)"><i class="fas fa-user-slash"></i> Absent</button>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div id="embPagination" class="emb-pagination"></div>
</div>

<!-- Modal Pause -->
<div class="emb-modal-bg" id="embModalPause">
    <div class="emb-modal">
        <h5><i class="fas fa-pause-circle" style="color:#7c3aed;"></i> Motif de la pause</h5>
        <div id="embPauseErreur" class="emb-modal-error">
            <i class="fas fa-circle-exclamation" style="margin-top:1px;"></i>
            <span id="embPauseErreurTexte"></span>
        </div>
        <textarea id="embPauseMotif" placeholder="Examen externe, urgence, pause déjeuner…"></textarea>
        <div class="emb-modal-btns">
            <button class="emb-btn emb-btn-refresh" onclick="embFermerPause()">Annuler</button>
            <button class="emb-btn emb-btn-pause" id="embPauseBtnConfirmer" onclick="embConfirmerPause()"><i class="fas fa-pause"></i> Confirmer</button>
        </div>
    </div>
</div>

<!-- Modal Prochain RDV -->
<div class="emb-modal-bg" id="embModalRdv">
    <div class="emb-modal">
        <h5><i class="fas fa-calendar-plus" style="color:#0052a0;"></i> Prochain rendez-vous — <span id="embRdvPatientNom"></span></h5>
        <div id="embRdvErreur" class="emb-modal-error">
            <i class="fas fa-circle-exclamation" style="margin-top:1px;"></i>
            <span id="embRdvErreurTexte"></span>
        </div>
        <div style="display:grid;gap:12px;margin-top:4px;">
            <div>
                <label style="font-size:.8rem;font-weight:600;color:#475569;">Date souhaitée</label>
                <input type="date" id="embRdvDate" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;box-sizing:border-box;margin-top:4px;">
            </div>
            <div>
                <label style="font-size:.8rem;font-weight:600;color:#475569;">Notes (optionnel)</label>
                <textarea id="embRdvNotes" rows="2" placeholder="Raison du prochain rendez-vous…" style="width:100%;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;resize:vertical;box-sizing:border-box;margin-top:4px;"></textarea>
            </div>
        </div>
        <div class="emb-modal-btns">
            <button class="emb-btn emb-btn-refresh" onclick="embFermerRdv()">Annuler</button>
            <button class="emb-btn emb-btn-start" id="embRdvBtnConfirmer" onclick="embConfirmerRdv()"><i class="fas fa-calendar-check"></i> Fixer le RDV</button>
        </div>
    </div>
</div>

<script>
/* ══ Variables embed ══ */
const _EMB_IS_ADMIN = <?= defined('QUEUECARE_EMBED_ADMIN') && QUEUECARE_EMBED_ADMIN ? 'true' : 'false' ?>;
let _embData       = <?= json_encode(array_values($consultations)) ?>;
let _embPage       = 1;
const _EMB_PER_PAGE = 10;
let _embPauseId    = null;
let _embRdvId      = null;
let _embRefreshing = false;
let _embInterval   = null;

/* ══ Affichage message ══ */
function embMsg(txt, type) {
    const el = document.getElementById('embMsg');
    if (!el) return;
    el.textContent = txt;
    el.className = 'emb-msg emb-msg-' + (type === 'success' ? 'success' : 'error');
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.display = 'none'; }, 4000);
}

/* ══ Refresh consultations ══ */
function embRafraichir() {
    if (_embRefreshing) return;
    _embRefreshing = true;
    const refreshUrl = typeof _EMB_IS_ADMIN !== 'undefined' && _EMB_IS_ADMIN
        ? 'admin.php?action=consultations_medecin&fragment=data'
        : 'medecin.php?action=get_consultations_data';
    fetch(refreshUrl)
        .then(r => r.json())
        .then(d => {
            if (d.success && Array.isArray(d.consultations)) {
                _embData = d.consultations;
                embRendreTableau(_embData, document.getElementById('embSearch')?.value || '');
                embMajStats(d.stats || {});
            }
        })
        .catch(e => console.error('embRafraichir:', e))
        .finally(() => { _embRefreshing = false; });
}

/* ══ Statistiques ══ */
function embMajStats(s) {
    const set = (id, v) => { const el=document.getElementById(id); if(el) el.textContent=v; };
    set('embStatTotal',    s.total    || 0);
    set('embStatTraitees', s.traitees || 0);
    set('embStatAttente',  s.en_attente || 0);
    set('embStatAbsents',  s.absentes || 0);
    set('embStatAnnulees', s.annulees || 0);
    const dm = parseInt(s.duree_moy_sec || 0);
    set('embStatDuree', dm > 0 ? Math.round(dm/60) + 'min' : '—');
}

/* ══ Rendu tableau ══ */
function embRendreTableau(data, filtre) {
    filtre = (filtre || '').toLowerCase();
    const filtered = filtre
        ? data.filter(c =>
            (c.patient_nom     || '').toLowerCase().includes(filtre) ||
            (c.patient_prenom  || '').toLowerCase().includes(filtre) ||
            (c.telephone       || c.patient_telephone || '').toLowerCase().includes(filtre) ||
            (c.statut          || '').toLowerCase().includes(filtre)
          )
        : data;

    const nb = document.getElementById('embConsultCount');
    if (nb) nb.textContent = filtered.length;

    const total_pages = Math.max(1, Math.ceil(filtered.length / _EMB_PER_PAGE));
    if (_embPage > total_pages) _embPage = 1;
    const slice = filtered.slice((_embPage-1)*_EMB_PER_PAGE, _embPage*_EMB_PER_PAGE);

    const tbody = document.getElementById('embTableBody');
    if (!tbody) return;

    if (!slice.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="emb-empty"><i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>${filtre ? 'Aucun résultat pour "'+filtre+'".' : 'Aucune consultation programmée pour aujourd\'hui.'}</td></tr>`;
        document.getElementById('embPagination').innerHTML = '';
        return;
    }

    const statBadge = s => ({
        traite:'emb-badge-success',en_cours:'emb-badge-info',en_pause:'emb-badge-pause',
        absent:'emb-badge-warning',annule:'emb-badge-danger'
    }[s]||'emb-badge-secondary');
    const statLabel = s => ({
        traite:'Traitée',en_cours:'En cours',en_pause:'En pause',
        absent:'Absent',annule:'Annulée',en_attente:'En attente',confirme:'Confirmé'
    }[s]||s);

    let h = '';
    slice.forEach(c => {
        const nom = ((c.patient_nom||'') + ' ' + (c.patient_prenom||'')).trim();
        const tel = c.telephone || c.patient_telephone || '—';
        const hPrev = c.heure_passage_estimee ? c.heure_passage_estimee.slice(11,16) : '—';
        const hDeb  = c.heure_debut_reelle    ? c.heure_debut_reelle.slice(11,16)    : '—';
        const hFin  = c.heure_fin_reelle      ? c.heure_fin_reelle.slice(11,16)      : '—';
        const isPauseRetour = c.priorite_retour == 1;

        let btns = '';
        if (c.statut === 'en_cours') {
            btns += `<button class="emb-btn emb-btn-pause" onclick="embPause(${c.id})"><i class="fas fa-pause"></i> Pause</button>`;
            btns += `<button class="emb-btn emb-btn-end" onclick="embTerminer(${c.id})"><i class="fas fa-check"></i> Terminer</button>`;
            btns += `<button class="emb-btn emb-btn-rdv" onclick="embOuvrirRdv(${c.id},'${nom.replace(/'/g,"\\'")}')"><i class="fas fa-calendar-plus"></i></button>`;
        } else if (c.statut === 'en_pause') {
            const priorityStyle = isPauseRetour ? 'font-weight:800;border:2px solid #1d4ed8;' : '';
            btns += `<button class="emb-btn emb-btn-resume" style="${priorityStyle}" onclick="embReprendre(${c.id})">${isPauseRetour?'⭐ ':''}<i class="fas fa-play"></i> Reprendre</button>`;
            btns += `<button class="emb-btn emb-btn-end" onclick="embTerminer(${c.id})"><i class="fas fa-check"></i> Terminer</button>`;
        } else if (['en_attente','confirme'].includes(c.statut)) {
            btns += `<button class="emb-btn emb-btn-start" onclick="embDemarrer(${c.id})"><i class="fas fa-play"></i> Démarrer</button>`;
        }
        if (!['traite','annule','absent'].includes(c.statut)) {
            btns += `<button class="emb-btn emb-btn-absent" onclick="embAbsent(${c.id})"><i class="fas fa-user-slash"></i> Absent</button>`;
        }

        h += `<tr data-id="${c.id}">
            <td><span class="emb-rang ${c.statut==='en_cours'?'current':''}">${c.rang}</span></td>
            <td><strong>${nom}</strong></td>
            <td>${tel}</td>
            <td>${hPrev}</td>
            <td>${hDeb}</td>
            <td>${hFin}</td>
            <td><span class="emb-badge ${statBadge(c.statut)}">${statLabel(c.statut)}</span></td>
            <td><div style="display:flex;gap:5px;flex-wrap:wrap;">${btns}</div></td>
        </tr>`;
    });
    tbody.innerHTML = h;

    // Pagination
    const pag = document.getElementById('embPagination');
    if (total_pages <= 1) { pag.innerHTML=''; return; }
    let ph = '';
    for (let i=1; i<=total_pages; i++) {
        ph += `<button class="emb-page-btn ${i===_embPage?'active':''}" onclick="_embPage=${i};embRendreTableau(_embData,document.getElementById('embSearch')?.value||'')">${i}</button>`;
    }
    pag.innerHTML = ph;
}

/* ══ Filtrage ══ */
function embFiltrer(val) { _embPage=1; embRendreTableau(_embData, val); }

/* ══ AJAX actions ══ */
function embPost(action, body, successMsg, onError) {
    // En mode embed admin, rerouter vers admin.php
    const baseUrl = typeof _EMB_IS_ADMIN !== 'undefined' && _EMB_IS_ADMIN
        ? 'admin.php?action=action_consultation_admin&sous_action=' + action
        : 'medecin.php?action=' + action;
    return fetch(baseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            embMsg(successMsg || 'Succès', 'success');
            embRafraichir();
        } else if (onError) {
            // Erreur affichée DANS la modale d'origine plutôt que dans le
            // toast global (qui peut être masqué derrière la modale ouverte).
            onError(d.message || 'Une erreur est survenue.');
        } else {
            embMsg(d.message || 'Une erreur est survenue.', 'error');
        }
        return d;
    })
    .catch(() => {
        const d = { success: false, message: 'Erreur réseau. Veuillez réessayer.' };
        if (onError) onError(d.message);
        else embMsg(d.message, 'error');
        return d;
    });
}

/* ══ Erreur affichée directement dans une modale embed ══ */
function embAfficherErreurModale(erreurId, texteId, message) {
    const box  = document.getElementById(erreurId);
    const span = document.getElementById(texteId);
    if (!box || !span) return;
    span.textContent = message;
    box.classList.add('show');
}
function embCacherErreurModale(erreurId) {
    const box = document.getElementById(erreurId);
    if (box) box.classList.remove('show');
}

function embDemarrer(id)  { embPost('demarrer_consultation_ajax',  'consultation_id='+id, 'Consultation démarrée.'); }
function embTerminer(id)  { embPost('terminer_consultation_ajax',  'consultation_id='+id, 'Consultation terminée.'); }
function embAbsent(id)    { embPost('marquer_absent_ajax',         'consultation_id='+id, 'Patient marqué absent.'); }
function embReprendre(id) {
    if (!confirm('Le patient est revenu ? La consultation va reprendre.')) return;
    embPost('reprendre_consultation_ajax', 'consultation_id='+id, 'Consultation reprise.');
}
function embAnnulerToutes() {
    if (!confirm('⚠️ Reporter toutes vos consultations du jour au lendemain ?\n\nElles seront programmées demain en priorité, dans le même ordre.')) return;
    fetch(
        typeof _EMB_IS_ADMIN !== 'undefined' && _EMB_IS_ADMIN
            ? 'admin.php?action=action_consultation_admin&sous_action=annuler_toutes'
            : 'medecin.php?action=annuler_toutes_ajax',
        { method: 'POST' }
    )
        .then(r => r.json())
        .then(d => { if(d.success) { embMsg('Consultations reportées à demain.', 'success'); embRafraichir(); } else embMsg(d.message||'Erreur','error'); })
        .catch(() => embMsg('Erreur réseau.', 'error'));
}

/* ══ Modal Pause ══ */
function embPause(id) {
    _embPauseId = id;
    const motif = document.getElementById('embPauseMotif');
    if (motif) motif.value = '';
    embCacherErreurModale('embPauseErreur');
    document.getElementById('embModalPause').classList.add('open');
    setTimeout(() => motif && motif.focus(), 100);
}
function embFermerPause() {
    document.getElementById('embModalPause').classList.remove('open');
    embCacherErreurModale('embPauseErreur');
    _embPauseId = null;
}
function embConfirmerPause() {
    const motif = document.getElementById('embPauseMotif')?.value.trim() || 'Examen externe';
    embCacherErreurModale('embPauseErreur');

    const btn = document.getElementById('embPauseBtnConfirmer');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…'; }

    embPost(
        'mettre_en_pause_ajax',
        `consultation_id=${_embPauseId}&motif=${encodeURIComponent(motif)}`,
        'Consultation mise en pause.',
        (message) => embAfficherErreurModale('embPauseErreur', 'embPauseErreurTexte', message)
    ).then((d) => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-pause"></i> Confirmer'; }
        if (d.success) embFermerPause();
    });
}

/* ══ Modal RDV ══ */
function embOuvrirRdv(id, nom) {
    _embRdvId = id;
    const nomEl = document.getElementById('embRdvPatientNom');
    const dateEl = document.getElementById('embRdvDate');
    const notesEl = document.getElementById('embRdvNotes');
    if (nomEl) nomEl.textContent = nom;
    if (dateEl) dateEl.value = '';
    if (notesEl) notesEl.value = '';
    embCacherErreurModale('embRdvErreur');
    document.getElementById('embModalRdv').classList.add('open');
}
function embFermerRdv() {
    document.getElementById('embModalRdv').classList.remove('open');
    embCacherErreurModale('embRdvErreur');
}
function embConfirmerRdv() {
    const date  = document.getElementById('embRdvDate')?.value;
    const notes = document.getElementById('embRdvNotes')?.value || '';

    embCacherErreurModale('embRdvErreur');

    if (!date) {
        embAfficherErreurModale('embRdvErreur', 'embRdvErreurTexte', 'Veuillez choisir une date.');
        return;
    }

    const btn = document.getElementById('embRdvBtnConfirmer');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…'; }

    embPost(
        'fixer_prochain_rdv_ajax',
        `consultation_id=${_embRdvId}&date_rdv=${date}&notes=${encodeURIComponent(notes)}`,
        'Prochain RDV fixé.',
        (message) => embAfficherErreurModale('embRdvErreur', 'embRdvErreurTexte', message)
    ).then((d) => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-calendar-check"></i> Fixer le RDV'; }
        if (d.success) embFermerRdv();
    });
}

/* ══ Fermer modaux avec Échap ══ */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        embFermerPause();
        embFermerRdv();
    }
});

/* ══ Auto-refresh toutes les 30s quand l'onglet consultations est actif ══ */
function embDemarrerInterval() {
    if (_embInterval) return;
    _embInterval = setInterval(embRafraichir, 30000);
}
function embArreterInterval() {
    if (_embInterval) { clearInterval(_embInterval); _embInterval = null; }
}

// Démarrer le polling automatiquement
embDemarrerInterval();
</script>
