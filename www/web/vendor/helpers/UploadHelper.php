<?php
class UploadHelper {
    public static function uploadPhoto($file, $subdir = '') {
        $allowed = ['image/jpeg', 'image/png'];
        if (!in_array($file['type'], $allowed)) {
            return ['success' => false, 'error' => 'Format non supporté (JPEG ou PNG)'];
        }
        $maxSize = 2 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'Fichier trop volumineux (max 2 Mo)'];
        }
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('med_') . '.' . $extension;
        $uploadDir = 'uploads/' . ($subdir ? $subdir . '/' : '');
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return ['success' => true, 'filename' => $uploadDir . $filename];
        }
        return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
    }
}
?>