<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InfoController extends Controller
{
    /**
     * Pages d'information publiques : légales, fonctionnelles et techniques.
     * Chaque clé est le slug d'URL autorisé, mappé vers sa vue dédiée.
     */
    private const PAGES = [
        'confidentialite' => ['view' => 'infos.confidentialite', 'title' => 'Politique de confidentialité'],
        'cgu' => ['view' => 'infos.cgu', 'title' => "Conditions d'utilisation"],
        'mentions-legales' => ['view' => 'infos.mentions-legales', 'title' => 'Mentions légales'],
        'cookies' => ['view' => 'infos.cookies', 'title' => 'Politique de cookies'],
        'securite' => ['view' => 'infos.securite', 'title' => 'Sécurité des données'],
        'contact' => ['view' => 'infos.contact', 'title' => 'Contact & support'],
        'a-propos' => ['view' => 'infos.a-propos', 'title' => 'À propos'],
        'installation' => ['view' => 'infos.installation', 'title' => "Installer l'application"],
        'modes-de-jeu' => ['view' => 'infos.modes-de-jeu', 'title' => 'Modes de jeu'],
        'categories-questions' => ['view' => 'infos.categories-questions', 'title' => 'Catégories de questions'],
    ];

    public function show(string $slug): View
    {
        if (! isset(self::PAGES[$slug])) {
            throw new NotFoundHttpException;
        }

        $page = self::PAGES[$slug];

        return view($page['view'], [
            'pageTitle' => $page['title'],
        ]);
    }
}
