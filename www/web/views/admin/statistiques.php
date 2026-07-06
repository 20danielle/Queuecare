<?php
/**
 * views/admin/statistiques.php
 * Inclus depuis views/admin/dashboard.php quand tab=statistiques
 * Variables disponibles : $hopital, $sousServices
 */
$nomHopitalStat = $hopital['nom_hopital'] ?? 'Hôpital';
?>
<style>
/* ── Statistiques admin ── */
.stat-adm-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
.stat-adm-card { background:white; border-radius:14px; padding:18px 16px; text-align:center;
    box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid #e2e8f0; }
.stat-adm-val { font-size:1.8rem; font-weight:800; color:var(--blue-600,#2563eb); }
.stat-adm-lbl { font-size:.72rem; color:#64748b; margin-top:4px; }
.stat-adm-icon { font-size:1.4rem; margin-bottom:6px; }
.chart-card { background:white; border-radius:14px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06);
    border:1px solid #e2e8f0; margin-bottom:20px; }
.chart-card-header { display:flex; justify-content:space-between; align-items:center;
    margin-bottom:16px; padding-bottom:10px; border-bottom:1.5px solid #e2e8f0; flex-wrap:wrap; gap:10px; }
.chart-card-title { font-size:.95rem; font-weight:700; color:#1e293b; }
.charts-2col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
@media (max-width:720px) { .charts-2col { grid-template-columns:1fr; } }
.ss-tab-nav { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.ss-tab-btn { padding:6px 14px; border:1.5px solid #e2e8f0; border-radius:20px;
    background:white; cursor:pointer; font-size:.78rem; font-weight:600; color:#64748b;
    font-family:inherit; transition:all .15s; }
.ss-tab-btn:hover { border-color:#2563eb; color:#2563eb; }
.ss-tab-btn.active { background:#2563eb; border-color:#2563eb; color:white; }
.periode-nav { display:flex; gap:8px; flex-wrap:wrap; }
.btn-per-adm { padding:5px 12px; border:1.5px solid #e2e8f0; border-radius:16px;
    background:white; cursor:pointer; font-size:.75rem; font-weight:600; color:#64748b;
    font-family:inherit; transition:all .15s; }
.btn-per-adm:hover { border-color:#2563eb; color:#2563eb; }
.btn-per-adm.active { background:#2563eb; border-color:#2563eb; color:white; }
.stats-loading { text-align:center; padding:40px; color:#64748b; font-size:.9rem; }
.export-btns { display:flex; gap:8px; flex-wrap:wrap; }
.btn-export { display:inline-flex; align-items:center; gap:6px; padding:7px 14px;
    border-radius:8px; border:none; cursor:pointer; font-size:.78rem; font-weight:600;
    font-family:inherit; transition:all .15s; }
.btn-export-print { background:#0052a0; color:white; }
.btn-export-print:hover { background:#003d7a; }
.btn-export-pdf { background:#ef4444; color:white; }
.btn-export-pdf:hover { background:#dc2626; }
.btn-export-csv { background:#059669; color:white; }
.btn-export-csv:hover { background:#047857; }
.section-tab-title { font-size:1.05rem; font-weight:700; color:#0052a0;
    margin:0 0 16px; padding-bottom:10px; border-bottom:2px solid #e2e8f0; }
/* Tableau temps attente */
.ta-table { width:100%; border-collapse:collapse; }
.ta-table th { background:#f8fafc; padding:10px 12px; text-align:left; font-size:.78rem; font-weight:700; color:#475569; border-bottom:2px solid #e2e8f0; }
.ta-table td { padding:10px 12px; font-size:.83rem; border-bottom:1px solid #f1f5f9; }
.ta-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:.72rem; font-weight:700; }
.ta-badge-good { background:#d1fae5; color:#065f46; }
.ta-badge-warn { background:#fef3c7; color:#92400e; }
.ta-badge-bad  { background:#fee2e2; color:#991b1b; }
.ta-badge-neu  { background:#f1f5f9; color:#475569; }
/* Print */
@media print {
    .adm-sidebar, .adm-topbar, .adm-tabs-nav, .adm-stats, .periode-nav,
    .export-btns, .ss-tab-nav, #statsLoading, #attenteLoadingAdm { display:none !important; }
    .chart-card { break-inside:avoid; box-shadow:none; border:1px solid #ccc; }
    body { font-size:11pt; }
    .adm-tab-pane { display:block !important; }
    #statsContent, #attenteContent { display:block !important; }
    .print-header { display:block !important; }
}
.print-header { display:none; text-align:center; margin-bottom:24px; }
.print-header h2 { font-size:1.3rem; color:#0052a0; margin:0 0 4px; }
.print-header p { font-size:.85rem; color:#64748b; margin:0; }

/* Print styles */
@media print {
    .adm-sidebar, .adm-topbar, .adm-tabs-nav, .adm-stats, .periode-nav,
    .export-btns, .ss-tab-nav, #statsLoading { display:none !important; }
    .chart-card { break-inside:avoid; box-shadow:none; border:1px solid #ccc; }
    body { font-size:11pt; }
    .adm-tab-pane { display:block !important; }
}
</style>

<div id="statsLoading" class="stats-loading">
    <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;margin-bottom:10px;display:block;"></i>
    Chargement des statistiques…
</div>

<div id="statsContent" style="display:none;">

    <!-- ── En-tête section ── -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <h4 style="margin:0;font-size:1.1rem;font-weight:700;color:#1e293b;">
            <i class="fas fa-chart-line" style="color:#2563eb;margin-right:8px;"></i>
            Statistiques — <?= htmlspecialchars($nomHopitalStat) ?>
        </h4>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <!-- Sélecteur de période -->
            <div class="periode-nav">
                <button class="btn-per-adm" data-j="0" onclick="admChangerPeriode(this,0)">Aujourd'hui</button>
                <button class="btn-per-adm active" data-j="7"  onclick="admChangerPeriode(this,7)">7 j</button>
                <button class="btn-per-adm" data-j="30" onclick="admChangerPeriode(this,30)">30 j</button>
                <button class="btn-per-adm" data-j="90" onclick="admChangerPeriode(this,90)">3 mois</button>
                <button class="btn-per-adm" data-j="365" onclick="admChangerPeriode(this,365)">1 an</button>
            </div>
            <!-- Boutons export -->
            <div class="export-btns">
                <button class="btn-export btn-export-print" onclick="admImprimer()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <button class="btn-export btn-export-pdf" onclick="admExporterPDF()">
                    <i class="fas fa-file-pdf"></i> Exporter PDF
                </button>
                <button class="btn-export btn-export-csv" onclick="admExporterCSV()">
                    <i class="fas fa-file-csv"></i> Exporter CSV
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION HÔPITAL (global)
    ══════════════════════════════════════ -->
    <h5 class="section-tab-title"><i class="fas fa-hospital-alt"></i> Vue globale de l'hôpital</h5>

    <!-- Cards résumé hôpital -->
    <div class="stat-adm-grid" id="admStatsCards">
        <div class="stat-adm-card">
            <div class="stat-adm-icon" style="color:#2563eb;">🏥</div>
            <div class="stat-adm-val" id="admTotal">—</div>
            <div class="stat-adm-lbl">Total consultations</div>
        </div>
        <div class="stat-adm-card">
            <div class="stat-adm-icon" style="color:#10b981;">✅</div>
            <div class="stat-adm-val" id="admTraitees">—</div>
            <div class="stat-adm-lbl">Traitées</div>
        </div>
        <div class="stat-adm-card">
            <div class="stat-adm-icon" style="color:#f59e0b;">🚶</div>
            <div class="stat-adm-val" id="admAbsentes">—</div>
            <div class="stat-adm-lbl">Absents</div>
        </div>
        <div class="stat-adm-card">
            <div class="stat-adm-icon" style="color:#ef4444;">❌</div>
            <div class="stat-adm-val" id="admAnnulees">—</div>
            <div class="stat-adm-lbl">Annulées</div>
        </div>
        <div class="stat-adm-card">
            <div class="stat-adm-icon" style="color:#6366f1;">⏱️</div>
            <div class="stat-adm-val" id="admDureeMoy">—</div>
            <div class="stat-adm-lbl">Durée moy. consult.</div>
        </div>
        <div class="stat-adm-card">
            <div class="stat-adm-icon" style="color:#0891b2;">📈</div>
            <div class="stat-adm-val" id="admTauxTraite">—</div>
            <div class="stat-adm-lbl">Taux de traitement</div>
        </div>
    </div>

    <!-- Évolution globale -->
    <div class="chart-card">
        <div class="chart-card-header">
            <span class="chart-card-title"><i class="fas fa-chart-line" style="color:#2563eb;margin-right:6px;"></i>Évolution globale des consultations</span>
        </div>
        <canvas id="admChartGlobal" style="max-height:300px;"></canvas>
    </div>

    <!-- 2 colonnes : donut + bar semaine -->
    <div class="charts-2col" style="margin-bottom:24px;">
        <div class="chart-card">
            <div class="chart-card-header">
                <span class="chart-card-title"><i class="fas fa-chart-pie" style="color:#6366f1;margin-right:6px;"></i>Répartition par statut</span>
            </div>
            <canvas id="admChartDonut" style="max-height:250px;"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <span class="chart-card-title"><i class="fas fa-chart-bar" style="color:#0891b2;margin-right:6px;"></i>Consultations par jour de semaine</span>
            </div>
            <canvas id="admChartBarJour" style="max-height:250px;"></canvas>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         SECTION PAR SOUS-SERVICE
    ══════════════════════════════════════ -->
    <h5 class="section-tab-title" style="margin-top:32px;"><i class="fas fa-stethoscope"></i> Par sous-service</h5>

    <!-- Comparaison entre sous-services -->
    <div class="chart-card" style="margin-bottom:20px;">
        <div class="chart-card-header">
            <span class="chart-card-title"><i class="fas fa-chart-bar" style="color:#2563eb;margin-right:6px;"></i>Comparaison des consultations par sous-service</span>
        </div>
        <canvas id="admChartSSBar" style="max-height:320px;"></canvas>
    </div>

    <!-- Onglets par sous-service (détail) -->
    <div class="chart-card">
        <div class="chart-card-header">
            <span class="chart-card-title"><i class="fas fa-chart-line" style="color:#059669;margin-right:6px;"></i>Évolution détaillée par sous-service</span>
        </div>
        <div class="ss-tab-nav" id="admSSTabs">
            <span style="font-size:.8rem;color:#94a3b8;">Chargement…</span>
        </div>
        <canvas id="admChartSSDetail" style="max-height:280px;"></canvas>
    </div>

    <!-- ══════════════════════════════════════
         SECTION TEMPS D'ATTENTE MOYEN
    ══════════════════════════════════════ -->
    <div style="margin-top:32px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h5 class="section-tab-title" style="margin:0;"><i class="fas fa-hourglass-half" style="color:#f59e0b;"></i> Temps d'attente moyen des patients</h5>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="font-size:.75rem;font-weight:600;color:#64748b;">Période :</span>
                <button class="btn-per-adm btn-att-adm active" data-jatt="30"  onclick="admChangerPeriodeAtt(this,30)">30 j</button>
                <button class="btn-per-adm btn-att-adm" data-jatt="90"  onclick="admChangerPeriodeAtt(this,90)">3 mois</button>
                <button class="btn-per-adm btn-att-adm" data-jatt="180" onclick="admChangerPeriodeAtt(this,180)">6 mois</button>
                <button class="btn-per-adm btn-att-adm" data-jatt="365" onclick="admChangerPeriodeAtt(this,365)">1 an</button>
                <button class="btn-per-adm btn-att-adm" data-jatt="9999" onclick="admChangerPeriodeAtt(this,9999)">Depuis le début</button>
                <span id="attenteLoadingAdm" style="font-size:.75rem;color:#94a3b8;display:none;"><i class="fas fa-spinner fa-spin"></i></span>
            </div>
        </div>

        <div id="attenteContent">
            <!-- Cards globales hôpital -->
            <h6 style="font-size:.85rem;font-weight:700;color:#475569;margin:0 0 12px;"><i class="fas fa-hospital-alt"></i> Vue globale — Hôpital entier</h6>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;" id="attenteCardsAdm">
                <div class="stat-adm-card" style="border-top:3px solid #f59e0b;">
                    <div class="stat-adm-icon" style="color:#f59e0b;">⏱️</div>
                    <div class="stat-adm-val" id="admAttenteGlobal">—</div>
                    <div class="stat-adm-lbl">Attente moy. globale (tout temps)</div>
                </div>
                <div class="stat-adm-card" style="border-top:3px solid #3b82f6;">
                    <div class="stat-adm-icon" style="color:#3b82f6;">📅</div>
                    <div class="stat-adm-val" id="admAttente7j">—</div>
                    <div class="stat-adm-lbl">Attente moy. 7 derniers jours</div>
                </div>
                <div class="stat-adm-card" style="border-top:3px solid #10b981;">
                    <div class="stat-adm-icon" style="color:#10b981;">📆</div>
                    <div class="stat-adm-val" id="admAttente30j">—</div>
                    <div class="stat-adm-lbl">Attente moy. 30 derniers jours</div>
                </div>
                <div class="stat-adm-card" style="border-top:3px solid #6366f1;">
                    <div class="stat-adm-icon" style="color:#6366f1;">📊</div>
                    <div class="stat-adm-val" id="admAttenteNb">—</div>
                    <div class="stat-adm-lbl">Consultations mesurées (total)</div>
                </div>
            </div>

            <!-- Tendance hôpital -->
            <div id="admAttenteTendance" style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.85rem;color:#475569;display:none;align-items:flex-start;gap:10px;"></div>

            <!-- Graphique évolution globale attente -->
            <div class="chart-card" style="margin-bottom:20px;">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-chart-area" style="color:#f59e0b;margin-right:6px;"></i>Évolution du temps d'attente moyen — Hôpital entier</span>
                </div>
                <canvas id="admChartAttenteGlobal" style="max-height:280px;"></canvas>
            </div>

            <!-- Tableau par sous-service -->
            <h6 style="font-size:.85rem;font-weight:700;color:#475569;margin:0 0 12px;"><i class="fas fa-stethoscope"></i> Détail par sous-service</h6>
            <div class="chart-card" style="margin-bottom:20px;">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-table" style="color:#059669;margin-right:6px;"></i>Tableau comparatif des temps d'attente</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ta-table" id="admAttenteTableSS">
                        <thead>
                            <tr>
                                <th>Sous-service</th>
                                <th>Attente moy. globale</th>
                                <th>7 derniers jours</th>
                                <th>30 derniers jours</th>
                                <th>Tendance (7j vs 30j)</th>
                                <th>Consultations mesurées</th>
                            </tr>
                        </thead>
                        <tbody id="admAttenteTableBody">
                            <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">Chargement…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Graphique comparaison bar par sous-service -->
            <div class="chart-card" style="margin-bottom:20px;">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-chart-bar" style="color:#0052a0;margin-right:6px;"></i>Comparaison du temps d'attente par sous-service</span>
                </div>
                <canvas id="admChartAttenteBar" style="max-height:300px;"></canvas>
            </div>

            <!-- Onglets évolution par SS -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-card-title"><i class="fas fa-chart-line" style="color:#059669;margin-right:6px;"></i>Évolution du temps d'attente par sous-service</span>
                </div>
                <div class="ss-tab-nav" id="admAttenteSSTabs">
                    <span style="font-size:.8rem;color:#94a3b8;">Chargement…</span>
                </div>
                <canvas id="admChartAttenteSSDetail" style="max-height:280px;"></canvas>
            </div>
        </div>
    </div>

</div><!-- /statsContent -->

<script>
/* ══════════════════════════════════════════════════
   STATISTIQUES ADMIN — Charts
══════════════════════════════════════════════════ */
(function() {
    let _jours = 7;
    let _data  = null;
    let _ssActif = null;
    let _charts = {};

    // Initialiser quand l'onglet devient visible
    window.admInitStats = function() {
        if (!_data) chargerStats();
    };

    function chargerStats() {
        document.getElementById('statsLoading').style.display = 'block';
        document.getElementById('statsContent').style.display = 'none';

        fetch('admin.php?action=get_stats_admin&jours=' + _jours)
            .then(r => r.json())
            .then(d => {
                document.getElementById('statsLoading').style.display = 'none';
                document.getElementById('statsContent').style.display = 'block';
                if (!d.success) return;
                _data = d;
                mettreAJourCards(d.totaux_globaux);
                dessinerGlobal(d.evolution_globale);
                dessinerDonut(d.totaux_globaux);
                dessinerBarJour(d.evolution_globale);
                dessinerSSBar(d.totaux_ss);
                construireOngletsSS(d.par_sous_service);
                // Données pour CSV
                window._admStatsData = d;
            })
            .catch(() => {
                document.getElementById('statsLoading').innerHTML =
                    '<i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Erreur de chargement.';
            });
    }

    window.admChangerPeriode = function(btn, jours) {
        document.querySelectorAll('.btn-per-adm').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        _jours = jours;
        _data = null;
        chargerStats();
    };

    function mettreAJourCards(t) {
        if (!t) return;
        const tot = parseInt(t.total) || 0;
        const tr  = parseInt(t.traitees) || 0;
        document.getElementById('admTotal').textContent     = tot;
        document.getElementById('admTraitees').textContent  = tr;
        document.getElementById('admAbsentes').textContent  = parseInt(t.absentes) || 0;
        document.getElementById('admAnnulees').textContent  = parseInt(t.annulees) || 0;
        const dm = parseInt(t.duree_moy_sec) || 0;
        document.getElementById('admDureeMoy').textContent  = dm > 0 ? Math.round(dm/60) + ' min' : '—';
        document.getElementById('admTauxTraite').textContent = tot > 0 ? Math.round(tr*100/tot) + ' %' : '—';
    }

    function fmtJour(s) {
        const d = new Date(s + 'T00:00:00');
        return d.toLocaleDateString('fr-FR', { day:'2-digit', month:'2-digit' });
    }

    function detruireChart(id) {
        if (_charts[id]) { _charts[id].destroy(); delete _charts[id]; }
    }

    function dessinerGlobal(ev) {
        detruireChart('global');
        const ctx = document.getElementById('admChartGlobal')?.getContext('2d');
        if (!ctx) return;
        const labels  = ev.map(r => fmtJour(r.jour));
        const totaux  = ev.map(r => parseInt(r.total) || 0);
        const traites = ev.map(r => parseInt(r.traitees) || 0);
        const abs     = ev.map(r => parseInt(r.absentes) || 0);
        const ann     = ev.map(r => parseInt(r.annulees) || 0);
        _charts['global'] = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [
                { label:'Total', data:totaux, borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.08)', fill:true, tension:.4, pointRadius:3 },
                { label:'Traitées', data:traites, borderColor:'#10b981', backgroundColor:'transparent', tension:.4, pointRadius:3 },
                { label:'Absents', data:abs, borderColor:'#f59e0b', backgroundColor:'transparent', tension:.4, pointRadius:3 },
                { label:'Annulées', data:ann, borderColor:'#ef4444', backgroundColor:'transparent', tension:.4, pointRadius:3 },
            ]},
            options: { responsive:true, maintainAspectRatio:true,
                plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:14 } }, tooltip:{ mode:'index', intersect:false } },
                scales:{ y:{ beginAtZero:true, ticks:{stepSize:1}, grid:{color:'rgba(0,0,0,.04)'} }, x:{ grid:{display:false} } }
            }
        });
    }

    function dessinerDonut(t) {
        detruireChart('donut');
        const ctx = document.getElementById('admChartDonut')?.getContext('2d');
        if (!ctx || !t) return;
        const tr = parseInt(t.traitees)||0, ab = parseInt(t.absentes)||0, an = parseInt(t.annulees)||0;
        _charts['donut'] = new Chart(ctx, {
            type:'doughnut',
            data:{ labels:['Traitées','Absents','Annulées'],
                datasets:[{ data:[tr,ab,an], backgroundColor:['#10b981','#f59e0b','#ef4444'], borderWidth:2 }] },
            options:{ responsive:true, maintainAspectRatio:true, cutout:'62%',
                plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } },
                    tooltip:{ callbacks:{ label: c => ` ${c.label}: ${c.raw}` } } }
            }
        });
    }

    function dessinerBarJour(ev) {
        detruireChart('barjour');
        const ctx = document.getElementById('admChartBarJour')?.getContext('2d');
        if (!ctx) return;
        const parJour = [0,0,0,0,0,0,0];
        ev.forEach(r => {
            const d = new Date(r.jour+'T00:00:00'); let dn=d.getDay(); dn=dn===0?6:dn-1;
            parJour[dn] += parseInt(r.total)||0;
        });
        _charts['barjour'] = new Chart(ctx, {
            type:'bar',
            data:{ labels:['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'],
                datasets:[{ label:'Consultations', data:parJour, backgroundColor:'#2563eb', borderRadius:6, hoverBackgroundColor:'#1d4ed8' }] },
            options:{ responsive:true, maintainAspectRatio:true,
                plugins:{ legend:{display:false} },
                scales:{ y:{ beginAtZero:true, ticks:{stepSize:1}, grid:{color:'rgba(0,0,0,.04)'} }, x:{grid:{display:false}} }
            }
        });
    }

    function dessinerSSBar(totauxSS) {
        detruireChart('ssbar');
        const ctx = document.getElementById('admChartSSBar')?.getContext('2d');
        if (!ctx || !totauxSS || totauxSS.length === 0) {
            if (ctx) ctx.canvas.parentNode.querySelector('.chart-card-header').insertAdjacentHTML('afterend',
                '<p style="text-align:center;color:#94a3b8;padding:20px;">Aucune donnée disponible.</p>');
            return;
        }
        const labels  = totauxSS.map(s => s.ss_nom);
        const traites = totauxSS.map(s => parseInt(s.traitees)||0);
        const absents = totauxSS.map(s => parseInt(s.absentes)||0);
        const annules = totauxSS.map(s => parseInt(s.annulees)||0);
        _charts['ssbar'] = new Chart(ctx, {
            type:'bar',
            data:{ labels, datasets:[
                { label:'Traitées', data:traites, backgroundColor:'#10b981', borderRadius:5, stack:'s' },
                { label:'Absents',  data:absents, backgroundColor:'#f59e0b', borderRadius:0, stack:'s' },
                { label:'Annulées', data:annules, backgroundColor:'#ef4444', borderRadius:0, stack:'s' },
            ]},
            options:{ responsive:true, maintainAspectRatio:true,
                plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:14 } } },
                scales:{ x:{ stacked:true, grid:{display:false} }, y:{ stacked:true, beginAtZero:true, grid:{color:'rgba(0,0,0,.04)'} } }
            }
        });
    }

    function construireOngletsSS(parSS) {
        const nav = document.getElementById('admSSTabs');
        if (!nav) return;
        if (!parSS || parSS.length === 0) {
            nav.innerHTML = '<span style="font-size:.8rem;color:#94a3b8;">Aucun sous-service avec données.</span>';
            return;
        }
        nav.innerHTML = parSS.map((ss, i) =>
            `<button class="ss-tab-btn ${i===0?'active':''}" onclick="admSelSS(this,${i})">${ss.ss_nom}</button>`
        ).join('');
        _ssActif = 0;
        dessinerSSDetail(parSS[0]);
    }

    window.admSelSS = function(btn, idx) {
        document.querySelectorAll('.ss-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        _ssActif = idx;
        if (_data && _data.par_sous_service && _data.par_sous_service[idx]) {
            dessinerSSDetail(_data.par_sous_service[idx]);
        }
    };

    function dessinerSSDetail(ss) {
        detruireChart('ssdetail');
        const ctx = document.getElementById('admChartSSDetail')?.getContext('2d');
        if (!ctx) return;
        const ev = ss.evolution || [];
        const labels  = ev.map(r => fmtJour(r.jour));
        const totaux  = ev.map(r => parseInt(r.total)||0);
        const traites = ev.map(r => parseInt(r.traitees)||0);
        const abs     = ev.map(r => parseInt(r.absentes)||0);
        _charts['ssdetail'] = new Chart(ctx, {
            type:'line',
            data:{ labels, datasets:[
                { label:'Total', data:totaux, borderColor:'#059669', backgroundColor:'rgba(5,150,105,.08)', fill:true, tension:.4, pointRadius:3 },
                { label:'Traitées', data:traites, borderColor:'#10b981', backgroundColor:'transparent', tension:.4, borderDash:[4,3], pointRadius:3 },
                { label:'Absents', data:abs, borderColor:'#f59e0b', backgroundColor:'transparent', tension:.4, borderDash:[4,3], pointRadius:3 },
            ]},
            options:{ responsive:true, maintainAspectRatio:true,
                plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:14 } }, tooltip:{ mode:'index', intersect:false } },
                scales:{ y:{ beginAtZero:true, ticks:{stepSize:1}, grid:{color:'rgba(0,0,0,.04)'} }, x:{grid:{display:false}} }
            }
        });
    }

    // ── Impression ──
    window.admImprimer = function() { window.print(); };

    // ── Export PDF ──
    window.admExporterPDF = function() {
        const hopital = <?= json_encode($nomHopitalStat) ?>;
        const date = new Date().toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' });
        const periode = document.querySelector('.btn-per-adm.active')?.textContent || '';
        const tot  = document.getElementById('admTotal')?.textContent || '—';
        const tr   = document.getElementById('admTraitees')?.textContent || '—';
        const ab   = document.getElementById('admAbsentes')?.textContent || '—';
        const an   = document.getElementById('admAnnulees')?.textContent || '—';
        const dm   = document.getElementById('admDureeMoy')?.textContent || '—';
        const tx   = document.getElementById('admTauxTraite')?.textContent || '—';
        const ag   = document.getElementById('admAttenteGlobal')?.textContent || '—';
        const a7   = document.getElementById('admAttente7j')?.textContent || '—';
        const a30  = document.getElementById('admAttente30j')?.textContent || '—';
        const nb   = document.getElementById('admAttenteNb')?.textContent || '—';
        let taSS = '';
        if (window._admDataAtt && window._admDataAtt.par_sous_service) {
            window._admDataAtt.par_sous_service.forEach(ss => {
                const diff = (ss.attente_7j_min != null && ss.attente_30j_min != null)
                    ? parseFloat(ss.attente_7j_min) - parseFloat(ss.attente_30j_min) : null;
                const tendTxt   = diff === null ? '—' : (diff < -2 ? '↓ Baisse' : (diff > 2 ? '↑ Hausse' : '→ Stable'));
                const tendColor = diff === null ? '#475569' : (diff < -2 ? '#065f46' : (diff > 2 ? '#991b1b' : '#1e40af'));
                taSS += `<tr><td>${ss.ss_nom}</td><td>${ss.attente_moy_min!=null?ss.attente_moy_min+' min':'—'}</td><td>${ss.attente_7j_min!=null?ss.attente_7j_min+' min':'—'}</td><td>${ss.attente_30j_min!=null?ss.attente_30j_min+' min':'—'}</td><td style="color:${tendColor};font-weight:600;">${tendTxt}</td><td>${ss.nb_mesures||0}</td></tr>`;
            });
        }
        let evTable = '';
        if (_data && _data.evolution_globale) {
            _data.evolution_globale.slice(-30).forEach(r => {
                evTable += `<tr><td>${r.jour}</td><td>${r.total||0}</td><td>${r.traitees||0}</td><td>${r.absentes||0}</td><td>${r.annulees||0}</td></tr>`;
            });
        }
        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Rapport — ${hopital}</title>
<style>body{font-family:Arial,sans-serif;color:#1e293b;padding:32px;font-size:11pt;}.report-header{display:flex;align-items:center;gap:14px;margin-bottom:18px;}.report-header img{height:48px;}h1{color:#0052a0;font-size:18pt;margin-bottom:4px;}h2{color:#0052a0;font-size:13pt;margin:24px 0 10px;border-bottom:2px solid #e2e8f0;padding-bottom:6px;}.meta{color:#64748b;font-size:9pt;margin-bottom:24px;}.cards{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;}.card{border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;min-width:110px;text-align:center;}.card-val{font-size:18pt;font-weight:800;color:#0052a0;}.card-lbl{font-size:8pt;color:#64748b;margin-top:3px;}table{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:9pt;}th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:700;border-bottom:2px solid #e2e8f0;}td{padding:7px 10px;border-bottom:1px solid #f1f5f9;}tr:nth-child(even) td{background:#f8fafc;}.footer{margin-top:32px;font-size:8pt;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:12px;}@media print{@page{margin:1.5cm;}}</style>
</head><body>
<div class="report-header"><img src="${location.origin}${location.pathname.replace(/\\/[^/]*$/, '')}/public/img/logo-queuecare-icon.png" alt="QueueCare"><div><h1 style="margin:0;">Rapport de statistiques — ${hopital}</h1></div></div>
<div class="meta">Généré le ${date} · Période : ${periode}</div>
<h2>📊 Consultations globales</h2>
<div class="cards">
  <div class="card"><div class="card-val">${tot}</div><div class="card-lbl">Total</div></div>
  <div class="card"><div class="card-val">${tr}</div><div class="card-lbl">Traitées</div></div>
  <div class="card"><div class="card-val">${ab}</div><div class="card-lbl">Absents</div></div>
  <div class="card"><div class="card-val">${an}</div><div class="card-lbl">Annulées</div></div>
  <div class="card"><div class="card-val">${dm}</div><div class="card-lbl">Durée moy.</div></div>
  <div class="card"><div class="card-val">${tx}</div><div class="card-lbl">Taux traitement</div></div>
</div>
<h2>⏱️ Temps d'attente moyen</h2>
<div class="cards">
  <div class="card"><div class="card-val">${ag}</div><div class="card-lbl">Global</div></div>
  <div class="card"><div class="card-val">${a7}</div><div class="card-lbl">7 derniers jours</div></div>
  <div class="card"><div class="card-val">${a30}</div><div class="card-lbl">30 derniers jours</div></div>
  <div class="card"><div class="card-val">${nb}</div><div class="card-lbl">Consultations mesurées</div></div>
</div>
<table><thead><tr><th>Sous-service</th><th>Attente globale</th><th>7 jours</th><th>30 jours</th><th>Tendance</th><th>Mesures</th></tr></thead>
<tbody>${taSS||'<tr><td colspan="6" style="text-align:center;color:#94a3b8;">Aucune donnée.</td></tr>'}</tbody></table>
<h2>📅 Évolution des consultations (30 derniers jours)</h2>
<table><thead><tr><th>Date</th><th>Total</th><th>Traitées</th><th>Absents</th><th>Annulées</th></tr></thead>
<tbody>${evTable||'<tr><td colspan="5" style="text-align:center;color:#94a3b8;">Aucune donnée.</td></tr>'}</tbody></table>
<div class="footer">QueueCare — Rapport généré le ${date}</div>
<script>window.onload=function(){window.print();}<\/script></body></html>`);
        win.document.close();
    };

    // ── Export CSV enrichi ──
    window.admExporterCSV = function() {
        if (!_data) return;
        const hopital = <?= json_encode($nomHopitalStat) ?>;
        const ev = _data.evolution_globale || [];
        let csv = 'Date,Total,Traitées,Absents,Annulées\r\n';
        ev.forEach(r => { csv += `${r.jour},${r.total||0},${r.traitees||0},${r.absentes||0},${r.annulees||0}\r\n`; });
        if (_data.par_sous_service && _data.par_sous_service.length > 0) {
            csv += '\r\nSous-service,Date,Total,Traitées,Absents,Annulées\r\n';
            _data.par_sous_service.forEach(ss => {
                (ss.evolution||[]).forEach(r => {
                    csv += `"${ss.ss_nom}",${r.jour},${r.total||0},${r.traitees||0},${r.absentes||0},${r.annulees||0}\r\n`;
                });
            });
        }
        if (window._admDataAtt && window._admDataAtt.par_sous_service) {
            csv += '\r\nSous-service,Attente globale (min),Attente 7j (min),Attente 30j (min),Nb mesures\r\n';
            window._admDataAtt.par_sous_service.forEach(ss => {
                csv += `"${ss.ss_nom}",${ss.attente_moy_min||''},${ss.attente_7j_min||''},${ss.attente_30j_min||''},${ss.nb_mesures||0}\r\n`;
            });
        }
        const blob = new Blob([csv], { type:'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `stats_${hopital}_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    // Démarrer le chargement si l'onglet est déjà actif
    if (document.getElementById('tab-statistiques')?.classList.contains('active')) {
        chargerStats();
    }
})();

/* ══════════════════════════════════════════════════════
   TEMPS D'ATTENTE ADMIN — Module indépendant
══════════════════════════════════════════════════════ */
(function() {
    let _joursAtt   = 30;
    let _dataAtt    = null;
    let _chartsAtt  = {};
    let _ssActifAtt = 0;

    window._admDataAtt = null; // référence globale pour export

    function chargerTempsAttenteAdmin() {
        const loading = document.getElementById('attenteLoadingAdm');
        if (loading) loading.style.display = 'inline';
        fetch('admin.php?action=get_temps_attente_admin&jours=' + _joursAtt)
            .then(r => r.json())
            .then(d => {
                if (loading) loading.style.display = 'none';
                if (!d.success) return;
                _dataAtt = d; window._admDataAtt = d;
                mettreAJourCardsAtt(d.global_hopital || {});
                afficherTendanceAtt(d.global_hopital || {});
                dessinerAttenteGlobal(d.evolution_globale || []);
                dessinerAttenteBar(d.par_sous_service || []);
                remplirTableauSS(d.par_sous_service || []);
                construireOngletsSS(d.evolution_ss || []);
            })
            .catch(() => { if (loading) loading.style.display = 'none'; });
    }

    window.admChangerPeriodeAtt = function(btn, jours) {
        document.querySelectorAll('.btn-att-adm').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        _joursAtt = jours; _dataAtt = null; window._admDataAtt = null;
        chargerTempsAttenteAdmin();
    };

    function fmtMin(v) {
        if (v === null || v === undefined || isNaN(parseFloat(v))) return '—';
        return Math.round(parseFloat(v)) + ' min';
    }
    function fmtJour(s) {
        const d = new Date(s + 'T00:00:00');
        return d.toLocaleDateString('fr-FR', { day:'2-digit', month:'2-digit' });
    }

    function mettreAJourCardsAtt(g) {
        const set = (id, v) => { const el=document.getElementById(id); if(el) el.textContent=fmtMin(v); };
        set('admAttenteGlobal', g.attente_moy_min);
        set('admAttente7j',     g.attente_7j_min);
        set('admAttente30j',    g.attente_30j_min);
        const nb = document.getElementById('admAttenteNb');
        if (nb) nb.textContent = g.nb_mesures || '—';
    }

    function afficherTendanceAtt(g) {
        const tend = document.getElementById('admAttenteTendance');
        if (!tend) return;
        if (g.attente_7j_min == null || g.attente_30j_min == null) { tend.style.display='none'; return; }
        const diff = parseFloat(g.attente_7j_min) - parseFloat(g.attente_30j_min);
        const abs  = Math.abs(diff).toFixed(1);
        let icon, couleur, msg;
        if (diff < -2) { icon='📉'; couleur='#16a34a'; msg=`Temps d'attente <strong>en baisse de ${abs} min</strong> ces 7 derniers jours. Bonne performance !`; }
        else if (diff > 2) { icon='📈'; couleur='#dc2626'; msg=`Temps d'attente <strong>en hausse de ${abs} min</strong> ces 7 derniers jours. Vigilance recommandée.`; }
        else { icon='➡️'; couleur='#0052a0'; msg=`Temps d'attente <strong>stable</strong> à l'échelle de l'hôpital.`; }
        if (g.premier_jour) {
            const pj = new Date(g.premier_jour+'T00:00:00').toLocaleDateString('fr-FR',{day:'2-digit',month:'long',year:'numeric'});
            msg += ` <span style="color:#94a3b8;font-size:.78rem;">Données depuis le ${pj}.</span>`;
        }
        tend.innerHTML = `<span style="font-size:1.1rem;">${icon}</span> <span style="color:${couleur};">${msg}</span>`;
        tend.style.display = 'flex';
        tend.style.gap = '10px';
        tend.style.alignItems = 'flex-start';
    }

    function detruireChart(id) { if(_chartsAtt[id]){_chartsAtt[id].destroy();delete _chartsAtt[id];} }

    function dessinerAttenteGlobal(ev) {
        detruireChart('attGlobal');
        const ctx = document.getElementById('admChartAttenteGlobal')?.getContext('2d');
        if (!ctx) return;
        const labels   = ev.map(r => fmtJour(r.jour));
        const attentes = ev.map(r => r.attente_moy_min!==null ? parseFloat(r.attente_moy_min) : null);
        const nbMes    = ev.map(r => parseInt(r.nb_mesures)||0);
        const movAvg   = attentes.map((v,i) => {
            const sl = attentes.slice(Math.max(0,i-6),i+1).filter(x=>x!==null);
            return sl.length ? parseFloat((sl.reduce((a,b)=>a+b,0)/sl.length).toFixed(1)) : null;
        });
        _chartsAtt['attGlobal'] = new Chart(ctx, {
            type:'bar', data:{ labels, datasets:[
                {label:'Attente moy. (min)', data:attentes, backgroundColor:'rgba(245,158,11,.35)', borderColor:'rgba(245,158,11,.7)', borderWidth:1, borderRadius:4, yAxisID:'y'},
                {label:'Moy. mobile 7j', data:movAvg, type:'line', borderColor:'#ef4444', backgroundColor:'transparent', borderWidth:2, tension:.4, pointRadius:2, borderDash:[5,3], yAxisID:'y'},
                {label:'Nb mesures', data:nbMes, type:'line', borderColor:'#0052a0', backgroundColor:'rgba(0,82,160,.06)', fill:true, tension:.3, pointRadius:2, borderWidth:1.5, yAxisID:'y2'},
            ]},
            options:{ responsive:true, maintainAspectRatio:true,
                plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:14}},tooltip:{mode:'index',intersect:false}},
                scales:{y:{beginAtZero:true,title:{display:true,text:'Minutes',font:{size:10}},grid:{color:'rgba(0,0,0,.04)'},position:'left'},y2:{beginAtZero:true,title:{display:true,text:'Consultations',font:{size:10}},grid:{display:false},position:'right'},x:{grid:{display:false}}}
            }
        });
    }

    function dessinerAttenteBar(parSS) {
        detruireChart('attBar');
        const ctx = document.getElementById('admChartAttenteBar')?.getContext('2d');
        if (!ctx || !parSS.length) return;
        const labels  = parSS.map(s => s.ss_nom);
        const global_ = parSS.map(s => s.attente_moy_min!==null ? parseFloat(s.attente_moy_min) : 0);
        const j7      = parSS.map(s => s.attente_7j_min!==null  ? parseFloat(s.attente_7j_min)  : 0);
        const j30     = parSS.map(s => s.attente_30j_min!==null ? parseFloat(s.attente_30j_min) : 0);
        _chartsAtt['attBar'] = new Chart(ctx, {
            type:'bar', data:{labels, datasets:[
                {label:'Global (tout temps)', data:global_, backgroundColor:'rgba(99,102,241,.7)', borderRadius:5},
                {label:'7 derniers jours',    data:j7,      backgroundColor:'rgba(59,130,246,.7)', borderRadius:5},
                {label:'30 derniers jours',   data:j30,     backgroundColor:'rgba(16,185,129,.7)', borderRadius:5},
            ]},
            options:{responsive:true,maintainAspectRatio:true,
                plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:14}},tooltip:{callbacks:{label:c=>` ${c.dataset.label}: ${c.raw} min`}}},
                scales:{y:{beginAtZero:true,title:{display:true,text:"Minutes d'attente"},grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}
            }
        });
    }

    function remplirTableauSS(parSS) {
        const tbody = document.getElementById('admAttenteTableBody');
        if (!tbody) return;
        if (!parSS.length) { tbody.innerHTML='<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">Aucune donnée disponible.</td></tr>'; return; }
        let html = '';
        parSS.forEach(ss => {
            const diff = (ss.attente_7j_min != null && ss.attente_30j_min != null) ? parseFloat(ss.attente_7j_min) - parseFloat(ss.attente_30j_min) : null;
            let badge;
            if (diff===null) badge='<span class="ta-badge ta-badge-neu">—</span>';
            else if(diff<-2) badge=`<span class="ta-badge ta-badge-good">↓ −${Math.abs(diff).toFixed(1)} min</span>`;
            else if(diff>2)  badge=`<span class="ta-badge ta-badge-bad">↑ +${diff.toFixed(1)} min</span>`;
            else             badge=`<span class="ta-badge ta-badge-warn">→ Stable</span>`;
            html += `<tr><td><strong>${ss.ss_nom}</strong></td><td>${fmtMin(ss.attente_moy_min)}</td><td>${fmtMin(ss.attente_7j_min)}</td><td>${fmtMin(ss.attente_30j_min)}</td><td>${badge}</td><td>${ss.nb_mesures||0}</td></tr>`;
        });
        tbody.innerHTML = html;
    }

    function construireOngletsSS(evSS) {
        const nav = document.getElementById('admAttenteSSTabs');
        if (!nav) return;
        if (!evSS.length) { nav.innerHTML='<span style="font-size:.8rem;color:#94a3b8;">Aucune données.</span>'; return; }
        nav.innerHTML = evSS.map((ss,i) =>
            `<button class="ss-tab-btn ${i===0?'active':''}" onclick="admSelSSAtt(this,${i})">${ss.ss_nom}</button>`
        ).join('');
        dessinerSSAttenteDetail(evSS[0]);
    }

    window.admSelSSAtt = function(btn, idx) {
        document.querySelectorAll('#admAttenteSSTabs .ss-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (_dataAtt && _dataAtt.evolution_ss && _dataAtt.evolution_ss[idx]) dessinerSSAttenteDetail(_dataAtt.evolution_ss[idx]);
    };

    function dessinerSSAttenteDetail(ss) {
        detruireChart('attSSDetail');
        const ctx = document.getElementById('admChartAttenteSSDetail')?.getContext('2d');
        if (!ctx) return;
        const ev       = ss.evolution || [];
        const labels   = ev.map(r => fmtJour(r.jour));
        const attentes = ev.map(r => r.attente_moy_min!==null ? parseFloat(r.attente_moy_min) : null);
        const nbMes    = ev.map(r => parseInt(r.nb_mesures)||0);
        const movAvg   = attentes.map((v,i) => {
            const sl = attentes.slice(Math.max(0,i-6),i+1).filter(x=>x!==null);
            return sl.length ? parseFloat((sl.reduce((a,b)=>a+b,0)/sl.length).toFixed(1)) : null;
        });
        _chartsAtt['attSSDetail'] = new Chart(ctx, {
            type:'bar', data:{labels, datasets:[
                {label:`Attente moy. — ${ss.ss_nom}`, data:attentes, backgroundColor:'rgba(5,150,105,.3)', borderColor:'rgba(5,150,105,.7)', borderWidth:1, borderRadius:4, yAxisID:'y'},
                {label:'Moy. mobile 7j', data:movAvg, type:'line', borderColor:'#ef4444', backgroundColor:'transparent', borderWidth:2, tension:.4, pointRadius:2, borderDash:[5,3], yAxisID:'y'},
                {label:'Nb mesures', data:nbMes, type:'line', borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,.06)', fill:true, tension:.3, pointRadius:2, borderWidth:1.5, yAxisID:'y2'},
            ]},
            options:{responsive:true,maintainAspectRatio:true,
                plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:14}},tooltip:{mode:'index',intersect:false},title:{display:true,text:`Sous-service : ${ss.ss_nom}`,font:{size:11},color:'#64748b'}},
                scales:{y:{beginAtZero:true,title:{display:true,text:'Minutes',font:{size:10}},grid:{color:'rgba(0,0,0,.04)'},position:'left'},y2:{beginAtZero:true,title:{display:true,text:'Consultations',font:{size:10}},grid:{display:false},position:'right'},x:{grid:{display:false}}}
            }
        });
    }

    window.admInitTempsAttente = function() { if (!_dataAtt) chargerTempsAttenteAdmin(); };

    // Charger automatiquement si le panneau stats est actif
    if (document.getElementById('tab-statistiques')?.classList.contains('active')) {
        chargerTempsAttenteAdmin();
    }
})();
</script>