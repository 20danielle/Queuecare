<?php
class JsonResponse {
    public static function send($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    public static function success($message = 'OK', $extra = []) {
        self::send(array_merge(['success' => true, 'message' => $message], $extra));
    }
    public static function error($message = 'Erreur', $code = 400) {
        self::send(['success' => false, 'message' => $message], $code);
    }
}
?>
