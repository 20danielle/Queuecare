<?php
class DateHelper {
    public static function now() {
        return date('Y-m-d H:i:s');
    }
    public static function ajouterMinutes($date, $minutes) {
        return date('Y-m-d H:i:s', strtotime($date . " + $minutes minutes"));
    }
    public static function differenceMinutes($debut, $fin) {
        return (strtotime($fin) - strtotime($debut)) / 60;
    }
}
?>