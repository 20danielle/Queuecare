<?php
class DateHelper {
    // Fuseau horaire fixe de l'application : Douala (Cameroun), WAT = UTC+1
    // toute l'année (pas d'heure d'été). Utiliser explicitement ce fuseau
    // ici évite tout décalage si le fuseau par défaut de PHP n'a pas encore
    // été positionné (date_default_timezone_set) au moment de l'appel.
    private const TZ = 'Africa/Douala';

    private static function tz(): DateTimeZone {
        return new DateTimeZone(self::TZ);
    }

    public static function now() {
        return (new DateTime('now', self::tz()))->format('Y-m-d H:i:s');
    }
    public static function ajouterMinutes($date, $minutes) {
        $d = new DateTime($date, self::tz());
        $d->modify("+{$minutes} minutes");
        return $d->format('Y-m-d H:i:s');
    }
    public static function differenceMinutes($debut, $fin) {
        $d1 = new DateTime($debut, self::tz());
        $d2 = new DateTime($fin, self::tz());
        return ($d2->getTimestamp() - $d1->getTimestamp()) / 60;
    }

    /**
     * Formate une date/heure (chaîne MySQL "Y-m-d H:i:s" ou compatible
     * strtotime) en heure de Douala, pour un affichage garanti sans
     * décalage côté client (dashboard web ou application mobile).
     *
     * @param string|null $date   Date/heure source.
     * @param string      $format Format PHP de sortie (par défaut 'H:i').
     * @return string '—' si $date est vide/invalide.
     */
    public static function formatDouala(?string $date, string $format = 'H:i'): string {
        if (empty($date)) {
            return '—';
        }
        try {
            return (new DateTime($date, self::tz()))->format($format);
        } catch (\Throwable $e) {
            return '—';
        }
    }
}
?>