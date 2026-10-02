<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Date de démarrage du catalogue
    |--------------------------------------------------------------------------
    |
    | Le catalogue de missions secrètes n'est servi qu'à partir de cette date,
    | comparée à la date locale de chaque joueur. Les missions déjà attribuées
    | avant cette date restent inchangées : c'est ce qui permet de basculer vers
    | le nouveau catalogue sans casser la journée en cours.
    |
    | La valeur sert aussi de point d'entrée aux tests, qui la surchargent pour
    | exercer la bascule à une date connue.
    |
    */

    'catalogue_start_date' => env('MISSIONS_CATALOGUE_START_DATE', '2026-10-03'),

];
