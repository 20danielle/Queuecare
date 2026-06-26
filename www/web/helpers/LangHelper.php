<?php
/**
 * helpers/LangHelper.php
 * Gestion du multilinguisme FR / EN pour QueueCare (Cameroun)
 *
 * Utilisation :
 *   LangHelper::init();           // à appeler en début de page
 *   __('key')                     // retourne la traduction
 *   LangHelper::getLang()         // retourne 'fr' ou 'en'
 *   LangHelper::setLang('en')     // force la langue
 */

class LangHelper
{
    private static string $lang = 'fr';

    // ────────────────────────────────────────────────────────────────
    //  Initialisation
    // ────────────────────────────────────────────────────────────────

    public static function init(): void
    {
        // Priorité : session → default fr
        if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['fr', 'en'])) {
            self::$lang = $_SESSION['lang'];
        } else {
            self::$lang = 'fr';
        }
    }

    public static function setLang(string $lang): void
    {
        if (in_array($lang, ['fr', 'en'])) {
            self::$lang = $lang;
            $_SESSION['lang'] = $lang;
        }
    }

    public static function getLang(): string
    {
        return self::$lang;
    }

    // ────────────────────────────────────────────────────────────────
    //  Traduction
    // ────────────────────────────────────────────────────────────────

    public static function t(string $key): string
    {
        $translations = self::getTranslations();
        return $translations[self::$lang][$key]
            ?? $translations['fr'][$key]
            ?? $key;
    }

    // ────────────────────────────────────────────────────────────────
    //  Toutes les traductions
    // ────────────────────────────────────────────────────────────────

    private static function getTranslations(): array
    {
        return [
            // ════════════════════════════════════════════════════════
            //  FRANÇAIS
            // ════════════════════════════════════════════════════════
            'fr' => [
                // Commun
                'lang_label'        => 'Langue',
                'lang_fr'           => 'Français',
                'lang_en'           => 'Anglais',
                'lang_choose'       => 'Choisissez votre langue',
                'save'              => 'Enregistrer',
                'cancel'            => 'Annuler',
                'back'              => 'Retour',
                'loading'           => 'Chargement...',
                'success'           => 'Succès',
                'error'             => 'Erreur',
                'required'          => 'Requis',

                // Auth — commun
                'email'             => 'Email',
                'password'          => 'Mot de passe',
                'confirm_password'  => 'Confirmer le mot de passe',
                'new_password'      => 'Nouveau mot de passe',
                'current_password'  => 'Mot de passe actuel',
                'login'             => 'Se connecter',
                'logout'            => 'Se déconnecter',
                'register'          => 'S\'inscrire',
                'already_account'   => 'Déjà inscrit ?',
                'no_account'        => 'Pas encore inscrit ?',
                'forgot_password'   => 'Mot de passe oublié ?',
                'back_to_home'      => 'Retour à l\'accueil',

                // Champs profil
                'nom'               => 'Nom',
                'prenom'            => 'Prénom',
                'telephone'         => 'Téléphone',
                'specialite'        => 'Spécialité',
                'photo'             => 'Photo de profil',
                'choose_photo'      => 'Choisir une photo',

                // Profil — actions
                'my_profile'        => 'Mon profil',
                'edit_profile'      => 'Modifier le profil',
                'profile_updated'   => 'Profil mis à jour avec succès !',
                'profile_locked'    => 'Vérification requise',
                'profile_locked_sub'=> 'Entrez votre mot de passe pour accéder à votre profil.',
                'unlock'            => 'Accéder au profil',
                'wrong_password'    => 'Mot de passe incorrect.',
                'change_password'   => 'Changer le mot de passe',
                'password_hint'     => 'Min 8 car., 1 maj., 1 chiffre',
                'password_confirm_hint' => 'Répétez le mot de passe',
                'current_pw_required' => 'Requis pour changer le mot de passe',
                'save_changes'      => 'Enregistrer les modifications',

                // Langue dans profil
                'language_settings' => 'Langue de l\'interface',
                'language_changed'  => 'Langue modifiée avec succès.',

                // Inscription médecin
                'doctor_register_title' => 'Créer un compte',
                'doctor_register_sub'   => 'Rejoignez l\'équipe médicale',
                'doctor_space'          => 'Espace Médecin',
                'sous_service'          => 'Spécialité / Sous-service',
                'select_sous_service'   => 'Sélectionner votre sous-service',
                'consultations_per_hour'=> 'consultations/heure',
                'create_account'        => 'Créer mon compte',
                'pw_too_short'          => 'Minimum 8 caractères.',
                'pw_need_upper'         => 'Au moins une majuscule requise.',
                'pw_need_digit'         => 'Au moins un chiffre requis.',
                'pw_no_match'           => 'Les mots de passe ne correspondent pas.',
                'photo_formats'         => 'Formats acceptés: JPG, PNG (max 2MB)',
                'phone_hint'            => 'Chiffres et le signe + uniquement',
                'specialite_hint'       => 'Sélectionnez votre spécialité médicale',
                'terms_text'            => 'En vous inscrivant, vous acceptez les',
                'terms_link'            => 'conditions d\'utilisation',
                'privacy_link'          => 'politique de confidentialité',
                'and'                   => 'et la',

                // Inscription gestionnaire
                'manager_register_title'=> 'Créer un compte gestionnaire',
                'manager_space'         => 'Espace Gestionnaire',
                'full_name'             => 'Nom complet',

                // Inscription admin
                'admin_setup_title'     => 'Configuration initiale',
                'hospital_name'         => 'Nom de l\'hôpital',
                'admin_name'            => 'Nom de l\'administrateur',
                'setup_submit'          => 'Créer l\'hôpital et le compte',

                // Dashboard médecin
                'dashboard'             => 'Tableau de bord',
                'welcome'               => 'Bienvenue',
                'patients_waiting'      => 'Patients en attente',
                'consultation'          => 'Consultation',
                'next_patient'          => 'Patient suivant',
                'no_patient'            => 'Aucun patient en attente',
                'status_available'      => 'Disponible',
                'status_busy'           => 'En consultation',
                'status_break'          => 'En pause',

                // Navigation
                'nav_dashboard'         => 'Tableau de bord',
                'nav_profile'           => 'Profil',
                'nav_planning'          => 'Planning',
                'nav_stats'             => 'Statistiques',
            ],

            // ════════════════════════════════════════════════════════
            //  ENGLISH
            // ════════════════════════════════════════════════════════
            'en' => [
                // Common
                'lang_label'        => 'Language',
                'lang_fr'           => 'French',
                'lang_en'           => 'English',
                'lang_choose'       => 'Choose your language',
                'save'              => 'Save',
                'cancel'            => 'Cancel',
                'back'              => 'Back',
                'loading'           => 'Loading...',
                'success'           => 'Success',
                'error'             => 'Error',
                'required'          => 'Required',

                // Auth — common
                'email'             => 'Email',
                'password'          => 'Password',
                'confirm_password'  => 'Confirm password',
                'new_password'      => 'New password',
                'current_password'  => 'Current password',
                'login'             => 'Log in',
                'logout'            => 'Log out',
                'register'          => 'Sign up',
                'already_account'   => 'Already registered?',
                'no_account'        => 'Not yet registered?',
                'forgot_password'   => 'Forgot password?',
                'back_to_home'      => 'Back to home',

                // Profile fields
                'nom'               => 'Last name',
                'prenom'            => 'First name',
                'telephone'         => 'Phone',
                'specialite'        => 'Specialty',
                'photo'             => 'Profile photo',
                'choose_photo'      => 'Choose a photo',

                // Profile — actions
                'my_profile'        => 'My profile',
                'edit_profile'      => 'Edit profile',
                'profile_updated'   => 'Profile updated successfully!',
                'profile_locked'    => 'Verification required',
                'profile_locked_sub'=> 'Enter your password to access your profile.',
                'unlock'            => 'Access profile',
                'wrong_password'    => 'Incorrect password.',
                'change_password'   => 'Change password',
                'password_hint'     => 'Min 8 chars., 1 uppercase, 1 digit',
                'password_confirm_hint' => 'Repeat password',
                'current_pw_required' => 'Required to change password',
                'save_changes'      => 'Save changes',

                // Language in profile
                'language_settings' => 'Interface language',
                'language_changed'  => 'Language changed successfully.',

                // Doctor registration
                'doctor_register_title' => 'Create an account',
                'doctor_register_sub'   => 'Join the medical team',
                'doctor_space'          => 'Doctor Portal',
                'sous_service'          => 'Specialty / Sub-service',
                'select_sous_service'   => 'Select your sub-service',
                'consultations_per_hour'=> 'consultations/hour',
                'create_account'        => 'Create my account',
                'pw_too_short'          => 'Minimum 8 characters.',
                'pw_need_upper'         => 'At least one uppercase letter required.',
                'pw_need_digit'         => 'At least one digit required.',
                'pw_no_match'           => 'Passwords do not match.',
                'photo_formats'         => 'Accepted formats: JPG, PNG (max 2MB)',
                'phone_hint'            => 'Digits and + sign only',
                'specialite_hint'       => 'Select your medical specialty',
                'terms_text'            => 'By signing up, you agree to the',
                'terms_link'            => 'terms of use',
                'privacy_link'          => 'privacy policy',
                'and'                   => 'and the',

                // Manager registration
                'manager_register_title'=> 'Create a manager account',
                'manager_space'         => 'Manager Portal',
                'full_name'             => 'Full name',

                // Admin registration
                'admin_setup_title'     => 'Initial setup',
                'hospital_name'         => 'Hospital name',
                'admin_name'            => 'Administrator name',
                'setup_submit'          => 'Create hospital and account',

                // Doctor dashboard
                'dashboard'             => 'Dashboard',
                'welcome'               => 'Welcome',
                'patients_waiting'      => 'Waiting patients',
                'consultation'          => 'Consultation',
                'next_patient'          => 'Next patient',
                'no_patient'            => 'No patients waiting',
                'status_available'      => 'Available',
                'status_busy'           => 'In consultation',
                'status_break'          => 'On break',

                // Navigation
                'nav_dashboard'         => 'Dashboard',
                'nav_profile'           => 'Profile',
                'nav_planning'          => 'Schedule',
                'nav_stats'             => 'Statistics',
            ],
        ];
    }
}

// Fonction globale raccourcie
if (!function_exists('__')) {
    function __(string $key): string {
        return LangHelper::t($key);
    }
}
