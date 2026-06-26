<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon ticket - QueueCare</title>
    <link rel="icon" type="image/png" href="public/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="public/img/favicon-64.png">
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        .ticket { max-width: 500px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px; text-align: center; }
        .ticket-logo { height: 56px; margin-bottom: 12px; }
        .numero { font-size: 3em; font-weight: bold; color: #2c3e50; }
        .info { margin: 15px 0; font-size: 1.2em; }
        .statut { font-weight: bold; }
        .en_attente { color: orange; }
        .en_cours { color: blue; }
        .termine { color: green; }
        @media (max-width: 480px) {
            .ticket { margin: 24px 16px; padding: 16px; }
            .numero { font-size: 2.2em; }
            .info { font-size: 1rem; }
            .ticket-logo { height: 46px; }
        }
        @media print {
            body { margin: 0; }
            .ticket { border: none; margin: 0; }
            button, a { display: none; }
        }
    </style>
</head>
<body>
<div class="ticket">
    <img class="ticket-logo" src="public/img/logo-queuecare-icon.png" alt="QueueCare">
    <h2>QueueCare - Ticket de consultation</h2>
    <div class="numero">#<?= str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) ?></div>
    <div class="info"><strong>Rang :</strong> <?= $ticket['rang'] ?></div>
    <div class="info"><strong>Temps d'attente estimé :</strong> <?= $ticket['temps_attente_minutes'] ?> min</div>
    <div class="info"><strong>Heure début estimée :</strong> <?= date('H:i', strtotime($ticket['heure_debut_estimee'])) ?></div>
    <div class="info"><strong>Heure fin estimée :</strong> <?= date('H:i', strtotime($ticket['heure_fin_estimee'])) ?></div>
    <div class="info statut <?= $ticket['statut'] ?>"><strong>Statut :</strong> <?= ucfirst($ticket['statut']) ?></div>
    <button onclick="window.print()">Imprimer</button>
    <a href="index.php">Accueil</a>
</div>
<script>
    // Rafraîchissement auto toutes les 30 secondes
    setInterval(() => {
        fetch('index.php?controller=ticket&action=statutAjax&id=<?= $ticket['id'] ?>')
            .then(r => r.json())
            .then(data => {
                document.querySelector('.statut').innerHTML = '<strong>Statut :</strong> ' + data.statut;
                document.querySelector('.info:first-child').innerHTML = '<strong>Rang :</strong> ' + data.rang;
            });
    }, 30000);
</script>
</body>
</html>