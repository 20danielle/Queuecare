<?php
require_once 'models/TicketModel.php';
require_once 'helpers/TicketHelper.php';

class TicketController {
    private $ticketModel;

    public function __construct() {
        $this->ticketModel = new TicketModel();
    }

    public function afficher($id) {
        $ticket = $this->ticketModel->obtenirParId($id);
        if (!$ticket) {
            die("Ticket introuvable.");
        }
        require_once 'views/tickets/ticket.php';
    }

    // Appel AJAX pour rafraîchir le statut
    public function statutAjax($id) {
        $ticket = $this->ticketModel->obtenirParId($id);
        echo json_encode(['statut' => $ticket['statut'], 'rang' => $ticket['rang']]);
        exit;
    }
}
?>