<?php

namespace Database\Seeders;

use App\Models\CarteAction;
use App\Models\CarteVerite;
use App\Models\DefiEnveloppe;
use App\Models\Gage;
use App\Models\QuestionDuJour;
use App\Models\QuestionOuiNon;
use App\Models\QuestionQuiDeNous;
use App\Models\QuestionQuiz;
use Illuminate\Database\Seeder;

class ContenuJeuxSeeder extends Seeder
{
    public function run(): void
    {
        $this->cartesVerite();
        $this->cartesAction();
        $this->gages();
        $this->questionsOuiNon();
        $this->defisEnveloppes();
        $this->questionsQuiz();
        $this->questionsQuiDeNous();
        $this->questionsDuJour();
    }

    private function cartesVerite(): void
    {
        $verites = [
            // Niveau doux
            ['texte' => 'Quel est ton premier souvenir de moi ?', 'niveau' => 'doux'],
            ['texte' => 'Quelle est la chose la plus mignonne que j\'ai faite ?', 'niveau' => 'doux'],
            ['texte' => 'Quelle chanson te fait penser à nous ?', 'niveau' => 'doux'],
            ['texte' => 'Quel est mon petit défaut que tu adores ?', 'niveau' => 'doux'],
            ['texte' => 'Quelle est ta plus belle qualité ?', 'niveau' => 'doux'],
            ['texte' => 'Quel est ton moment préféré passé avec moi cette semaine ?', 'niveau' => 'doux'],
            ['texte' => 'Quelle est la première chose que tu as remarquée chez moi ?', 'niveau' => 'doux'],
            ['texte' => 'Si tu devais me décrire en 3 mots, lesquels ?', 'niveau' => 'doux'],
            ['texte' => 'Quel est mon plat préféré selon toi ?', 'niveau' => 'doux'],
            ['texte' => 'Qu\'est-ce qui te fait rire chez moi ?', 'niveau' => 'doux'],
            ['texte' => 'Quelle habitude de moi te manque le plus à distance ?', 'niveau' => 'doux'],
            ['texte' => 'Quel serait notre premier voyage ensemble idéal ?', 'niveau' => 'doux'],
            // Niveau chaud
            ['texte' => 'Quelle photo de moi regardes-tu le plus ?', 'niveau' => 'chaud'],
            ['texte' => 'Quel message de moi t\'a le plus excité(e) ?', 'niveau' => 'chaud'],
            ['texte' => 'Quelle partie de mon corps te manque le plus ?', 'niveau' => 'chaud'],
            ['texte' => 'Quel est le moment le plus romantique que je t\'ai offert ?', 'niveau' => 'chaud'],
            ['texte' => 'Quelle tenue de moi te fais le plus d\'effet ?', 'niveau' => 'chaud'],
            ['texte' => 'Quel est le baiser que tu n\'oublieras jamais ?', 'niveau' => 'chaud'],
            ['texte' => 'Qu\'est-ce qui t\'a fait craquer pour moi ?', 'niveau' => 'chaud'],
            ['texte' => 'Quelle est la chose la plus séduisante que je fasse sans m\'en rendre compte ?', 'niveau' => 'chaud'],
            ['texte' => 'Quel mot doux de ma part te fait fondre ?', 'niveau' => 'chaud'],
            ['texte' => 'Où as-tu eu envie de m\'embrasser la première fois ?', 'niveau' => 'chaud'],
            ['texte' => 'Quel est ton souvenir le plus sensuel de nous ?', 'niveau' => 'chaud'],
            ['texte' => 'Quelle promesse me ferais-tu les yeux fermés ?', 'niveau' => 'chaud'],
            // Niveau brûlant
            ['texte' => 'Quel est ton fantasme que tu ne m\'as jamais dit ?', 'niveau' => 'brulant'],
            ['texte' => 'Décris ce que tu me ferais si j\'étais là maintenant', 'niveau' => 'brulant'],
            ['texte' => 'Quel est ton rêve le plus fou pour nos retrouvailles ?', 'niveau' => 'brulant'],
            ['texte' => 'Quel est le souvenir intime que tu gardes précieusement ?', 'niveau' => 'brulant'],
            ['texte' => 'Quelle est ta position préférée entre nous ?', 'niveau' => 'brulant'],
            ['texte' => 'Quel endroit de mon corps voudrais-tu embrasser en premier au réveil ?', 'niveau' => 'brulant'],
            ['texte' => 'Quel est le message le plus osé que tu n\'as jamais osé m\'envoyer ?', 'niveau' => 'brulant'],
            ['texte' => 'Raconte ton fantasme lié à la distance', 'niveau' => 'brulant'],
            ['texte' => 'Quelle est la chose la plus audacieuse que je pourrais te faire pour te surprendre ?', 'niveau' => 'brulant'],
            ['texte' => 'Décris-moi notre nuit idéale de retrouvailles, du début à la fin', 'niveau' => 'brulant'],
            ['texte' => 'Quel est ton plus grand désir inavoué pour nous deux ?', 'niveau' => 'brulant'],
        ];

        foreach ($verites as $carte) {
            CarteVerite::create(['texte' => $carte['texte'], 'niveau' => $carte['niveau'], 'created_by' => null]);
        }
    }

    private function cartesAction(): void
    {
        $actions = [
            // Niveau doux
            ['texte' => 'Envoie un vocal de 30 secondes expliquant pourquoi tu m\'aimes', 'niveau' => 'doux'],
            ['texte' => 'Prends un selfie en faisant ta plus belle tête', 'niveau' => 'doux'],
            ['texte' => 'Dessine-nous sur une feuille et envoie la photo', 'niveau' => 'doux'],
            ['texte' => 'Envoie une photo de ton plat préféré', 'niveau' => 'doux'],
            ['texte' => 'Fais une petite danse de 10 secondes en vidéo', 'niveau' => 'doux'],
            ['texte' => 'Envoie une photo de ton endroit préféré chez toi', 'niveau' => 'doux'],
            ['texte' => 'Chante un extrait de notre chanson en vocal', 'niveau' => 'doux'],
            ['texte' => 'Décris ton dressing entier en anecdote drôle', 'niveau' => 'doux'],
            ['texte' => 'Photo de toi avec un objet tout mignon qui te ressemble', 'niveau' => 'doux'],
            ['texte' => 'Écris un petit poème de 4 vers et envoie-le', 'niveau' => 'doux'],
            ['texte' => 'Fais-nous un défi impossible : un selfie avec ton reflet dans une cuillère', 'niveau' => 'doux'],
            ['texte' => 'Montre ton lit tout fait en photo', 'niveau' => 'doux'],
            // Niveau chaud
            ['texte' => 'Envoie une photo de la partie de ton corps que tu préfères', 'niveau' => 'chaud'],
            ['texte' => 'Écris un scénario coquin en 5 messages', 'niveau' => 'chaud'],
            ['texte' => 'Envoie un vocal en chuchotant', 'niveau' => 'chaud'],
            ['texte' => 'Envoie une photo de toi avec une tenue qui te met en valeur', 'niveau' => 'chaud'],
            ['texte' => 'Décris par écrit ce que tu ferais la prochaine fois qu\'on se voit', 'niveau' => 'chaud'],
            ['texte' => 'Envoie une photo de ton endroit le plus sensible (pas intime)', 'niveau' => 'chaud'],
            ['texte' => 'Fais un vocal de 20 secondes avec ta voix la plus douce', 'niveau' => 'chaud'],
            ['texte' => 'Écris un SMS comme si tu me draguais pour la première fois', 'niveau' => 'chaud'],
            ['texte' => 'Envoie une photo avec ton plus beau sourire coquin', 'niveau' => 'chaud'],
            ['texte' => 'Raconte en détail ce que tu ressens quand tu penses à moi', 'niveau' => 'chaud'],
            ['texte' => 'Fais une vidéo de 15 secondes en me soufflant des mots doux', 'niveau' => 'chaud'],
            ['texte' => 'Envoie une photo de toi dans un endroit inattendu de ta maison', 'niveau' => 'chaud'],
            // Niveau brûlant
            ['texte' => 'Fais un strip-tease en vidéo de 30 secondes', 'niveau' => 'brulant'],
            ['texte' => 'Envoie une photo très suggestive', 'niveau' => 'brulant'],
            ['texte' => 'Décris en détail ce que tu aimerais qu\'on fasse lors de notre prochaine nuit ensemble', 'niveau' => 'brulant'],
            ['texte' => 'Fais un vocal de 30 secondes en parlant très sensuellement', 'niveau' => 'brulant'],
            ['texte' => 'Envoie une photo de ton corps en sous-vêtements', 'niveau' => 'brulant'],
            ['texte' => 'Écris une histoire érotique de 5 lignes à mon intention', 'niveau' => 'brulant'],
            ['texte' => 'Fais une vidéo de 15 secondes avec un regard qui tue', 'niveau' => 'brulant'],
            ['texte' => 'Décris le fantasme que tu veux réaliser à deux en vocal', 'niveau' => 'brulant'],
            ['texte' => 'Envoie un message très osé que tu n\'osais pas dire', 'niveau' => 'brulant'],
            ['texte' => 'Simule un rendez-vous coquin en vidéo de 20 secondes', 'niveau' => 'brulant'],
            ['texte' => 'Envoie une photo de ton moment de la journée le plus intime', 'niveau' => 'brulant'],
            ['texte' => 'Écris 3 promesses osées pour nos prochaines retrouvailles', 'niveau' => 'brulant'],
        ];

        foreach ($actions as $carte) {
            CarteAction::create(['texte' => $carte['texte'], 'niveau' => $carte['niveau'], 'created_by' => null]);
        }
    }

    private function gages(): void
    {
        $gages = [
            'Fais 20 pompes et envoie la vidéo',
            'Déclare ta flamme à un objet inanimé en vocal',
            'Fais un selfie avec la pire grimace possible',
            'Imite une célébrité pendant 30 secondes en vidéo',
            'Envoie un vocal en chantant une chanson enfantine',
            'Danse comme personne ne t\'a jamais vu danser',
            'Envoie une photo d\'un objet que tu n\'as jamais montré',
            'Pose une question embarrassante à ton reflet',
            'Fais semblant de parler en public à ton téléphone',
            'Raconte un secret drôle en vocal',
            'Fais 10 squats en te filmant',
            'Envoie un vocal avec une voix déformée amusante',
        ];

        foreach ($gages as $gage) {
            Gage::create(['texte' => $gage, 'created_by' => null]);
        }
    }

    private function questionsOuiNon(): void
    {
        $questions = [
            // Vie quotidienne
            ['texte' => 'Accepterais-tu de faire un appel vidéo de 30 minutes sans parler, juste en te regardant ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de m\'envoyer une photo de toi au réveil chaque matin pendant une semaine ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de me laisser choisir ton fond d\'écran de téléphone pendant 1 mois ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de me préparer un dîner surprise lors de nos retrouvailles ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de porter un bijou ou accessoire que je t\'offre tous les jours ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de me réveiller par un message vocal coquin pendant une semaine ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de me laisser te donner un gage par jour pendant une semaine ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de te coucher 15 minutes plus tôt chaque soir pour m\'appeler ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de cuisiner mon plat préféré en vidéo pour moi ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Accepterais-tu de partager ton écran pendant une heure ?', 'categorie' => 'vie_quotidienne'],
            // Intimité
            ['texte' => 'Accepterais-tu de faire une soirée pyjama en visio complète ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de porter une tenue que je choisirais ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de m\'écrire une lettre d\'amour manuscrite et de me l\'envoyer en photo ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de faire un strip-tease en visio pour mon anniversaire ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de répondre à toutes mes questions pendant 10 minutes sans mentir ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de me lire une histoire érotique en vocal ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de me montrer ton corps en visio sans réserve ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de danser pour moi uniquement en visio ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de me raconter tes rêves les plus intimes ?', 'categorie' => 'intimite'],
            ['texte' => 'Accepterais-tu de rester en visio pendant que tu te changes ?', 'categorie' => 'intimite'],
            // Fantasmes
            ['texte' => 'Accepterais-tu de réaliser un de mes fantasmes lors de nos prochaines retrouvailles ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de faire un shooting photo sexy pour moi ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de me laisser organiser une journée entière de défis ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de m\'envoyer un vocal coquin tous les jours avant de dormir ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de réaliser un scénario de rôle que je choisis ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de me donner des ordres coquins à suivre pendant une soirée ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de m\'accompagner dans un jeu de séduction interdit ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de porter une tenue surprise que je t\'enverrais ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de faire une mise à nu progressive en visio sur ma demande ?', 'categorie' => 'fantasmes'],
            ['texte' => 'Accepterais-tu de tester un jeu coquin proposé par moi chaque semaine ?', 'categorie' => 'fantasmes'],
            // Aventure
            ['texte' => 'Accepterais-tu qu\'on fasse un voyage surprise ensemble ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de faire une randonnée de nuit avec moi en visio ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de faire un parcours de nuit dans ta ville pour moi ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de camper en visio la même nuit que moi ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de réaliser une mission en ville que je te donnerais ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu d\'aller au cinéma seul(e) et de me raconter ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de prendre un cours en ligne ensemble ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de te lever à 5h pour voir le lever de soleil avec moi ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de découvrir ta ville comme un touriste avec moi en visio ?', 'categorie' => 'aventure'],
            ['texte' => 'Accepterais-tu de m\'embrasser sur un lieu insolite de ta ville lors de nos retrouvailles ?', 'categorie' => 'aventure'],
        ];

        foreach ($questions as $question) {
            QuestionOuiNon::create(['texte' => $question['texte'], 'categorie' => $question['categorie'], 'created_by' => null]);
        }
    }

    private function defisEnveloppes(): void
    {
        $defis = [
            // Rouge (Osé)
            ['texte' => 'Envoie une photo de la partie de ton corps que je préfère', 'couleur' => 'rouge'],
            ['texte' => 'Décris en 3 phrases ce que tu me ferais si j\'étais là', 'couleur' => 'rouge'],
            ['texte' => 'Fais un vocal de 15 secondes en mode séducteur/séductrice', 'couleur' => 'rouge'],
            ['texte' => 'Montre-moi ton endroit le plus sensible', 'couleur' => 'rouge'],
            ['texte' => 'Envoie un message que tu n\'as jamais osé envoyer', 'couleur' => 'rouge'],
            ['texte' => 'Envoie une photo suggestive mais classe', 'couleur' => 'rouge'],
            ['texte' => 'Décris un fantasme en 5 lignes minimum', 'couleur' => 'rouge'],
            ['texte' => 'Fais un vocal en chuchotant ce que tu me ferais ce soir', 'couleur' => 'rouge'],
            ['texte' => 'Envoie une photo de toi en sous-vêtements', 'couleur' => 'rouge'],
            // Bleue (Tendre)
            ['texte' => 'Écris un message de 5 lignes expliquant pourquoi tu m\'aimes', 'couleur' => 'bleue'],
            ['texte' => 'Envoie une photo de notre souvenir préféré (si tu en as un en photo)', 'couleur' => 'bleue'],
            ['texte' => 'Fais un vocal de 30 secondes en me chantant une chanson', 'couleur' => 'bleue'],
            ['texte' => 'Décris notre premier baiser avec tes mots', 'couleur' => 'bleue'],
            ['texte' => 'Dis-moi 3 choses que tu aimerais qu\'on fasse ensemble', 'couleur' => 'bleue'],
            ['texte' => 'Envoie une photo de toi qui me rappelle un bon souvenir', 'couleur' => 'bleue'],
            ['texte' => 'Écris une lettre d\'amour de 4 lignes', 'couleur' => 'bleue'],
            ['texte' => 'Raconte ton souvenir préféré de nous en vocal', 'couleur' => 'bleue'],
            ['texte' => 'Dis-moi ce que tu aimes physiquement chez moi', 'couleur' => 'bleue'],
            // Verte (Drôle)
            ['texte' => 'Envoie un selfie en faisant la grimace la plus drôle possible', 'couleur' => 'verte'],
            ['texte' => 'Envoie un vocal en imitant une célébrité', 'couleur' => 'verte'],
            ['texte' => 'Raconte une blague pourrie', 'couleur' => 'verte'],
            ['texte' => 'Envoie une photo de la chose la plus moche qui soit chez toi', 'couleur' => 'verte'],
            ['texte' => 'Fais un clip de 15 secondes en dansant sur une musique ringarde', 'couleur' => 'verte'],
            ['texte' => 'Fais un selfie imitant un animal', 'couleur' => 'verte'],
            ['texte' => 'Prouve que tu peux cligner des deux yeux alternativement en vidéo', 'couleur' => 'verte'],
            ['texte' => 'Montre ta collection un peu bizarre en photo', 'couleur' => 'verte'],
            ['texte' => 'Fais un vocal en imitant un enfant de 5 ans', 'couleur' => 'verte'],
            ['texte' => 'Envoie un selfie avec la coupe de cheveux la plus folle', 'couleur' => 'verte'],
        ];

        foreach ($defis as $defi) {
            DefiEnveloppe::create(['texte' => $defi['texte'], 'couleur' => $defi['couleur'], 'created_by' => null]);
        }
    }

    private function questionsQuiz(): void
    {
        $questions = [
            ['texte_soi' => 'Ton plat préféré ?', 'texte_partenaire' => 'Quel est son plat préféré ?'],
            ['texte_soi' => 'Ton film préféré ?', 'texte_partenaire' => 'Quel est son film préféré ?'],
            ['texte_soi' => 'Quel pays de rêve veux-tu visiter ?', 'texte_partenaire' => 'Quel pays de rêve voudrait-il/elle visiter ?'],
            ['texte_soi' => 'Quel était ton rêve d\'enfant ?', 'texte_partenaire' => 'Quel était son rêve d\'enfant ?'],
            ['texte_soi' => 'Ta plus grande peur ?', 'texte_partenaire' => 'Quelle est sa plus grande peur ?'],
            ['texte_soi' => 'Ton talent caché ?', 'texte_partenaire' => 'Quel est son talent caché ?'],
            ['texte_soi' => 'Ton artiste ou groupe préféré ?', 'texte_partenaire' => 'Quel est son artiste ou groupe préféré ?'],
            ['texte_soi' => 'Ton animal préféré ?', 'texte_partenaire' => 'Quel est son animal préféré ?'],
            ['texte_soi' => 'Qu\'est-ce qui t\'énerve le plus ?', 'texte_partenaire' => 'Qu\'est-ce qui l\'énerve le plus ?'],
            ['texte_soi' => 'Ton plus grand rêve ?', 'texte_partenaire' => 'Quel est son plus grand rêve ?'],
            ['texte_soi' => 'Ta boisson préférée ?', 'texte_partenaire' => 'Quelle est sa boisson préférée ?'],
            ['texte_soi' => 'Où aimes-tu te ressourcer ?', 'texte_partenaire' => 'Où aime-t-il/elle se ressourcer ?'],
            ['texte_soi' => 'Ton slogan de vie ?', 'texte_partenaire' => 'Quel est son slogan de vie ?'],
            ['texte_soi' => 'Ta plus grande fierté ?', 'texte_partenaire' => 'Quelle est sa plus grande fierté ?'],
        ];

        foreach ($questions as $question) {
            QuestionQuiz::create([
                'texte_soi' => $question['texte_soi'],
                'texte_partenaire' => $question['texte_partenaire'],
                'categorie' => null,
                'created_by' => null,
            ]);
        }
    }

    private function questionsQuiDeNous(): void
    {
        $questions = [
            // Personnalité
            ['texte' => 'Qui de nous deux est le plus bavard ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux est le plus têtu ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux est le plus romantique ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux est le plus drôle ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux s\'énerve le plus vite ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux pleure le plus facilement devant un film ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux est le plus organisé ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux est le plus timide en public ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux rêvasse le plus souvent ?', 'categorie' => 'personnalite'],
            ['texte' => 'Qui de nous deux est le plus spontané ?', 'categorie' => 'personnalite'],
            // Vie quotidienne
            ['texte' => 'Qui de nous deux se lève le plus tôt le week-end ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux prend la plus longue douche ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux laisse traîner ses affaires ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux cuisine le mieux ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux commande le plus souvent des plats à emporter ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux fait le plus de lessives ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux oublie le plus souvent son téléphone ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux regarde le plus la télévision ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux planifie le plus le week-end ?', 'categorie' => 'vie_quotidienne'],
            ['texte' => 'Qui de nous deux se couche le plus tard ?', 'categorie' => 'vie_quotidienne'],
            // Relation
            ['texte' => 'Qui de nous deux dit « je t\'aime » en premier le matin ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux est le plus jaloux ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux fait le premier pas après une dispute ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux prépare les plus belles surprises ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux se souvient le mieux des dates importantes ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux est le plus démonstratif en public ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux choisit le film le plus souvent ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux tient le plus la main de l\'autre ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux s\'ennuie le plus vite quand l\'autre est loin ?', 'categorie' => 'relation'],
            ['texte' => 'Qui de nous deux rêve le plus fort de l\'avenir à deux ?', 'categorie' => 'relation'],
            // Habitudes
            ['texte' => 'Qui de nous deux ronfle le plus ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux traîne le plus au lit le matin ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux fait le plus de siestes ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux boit le plus de café ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux grignote le plus le soir ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux passe le plus de temps sur son téléphone ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux chante le plus sous la douche ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux met le plus longtemps à se préparer ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux vérifie le plus souvent la météo ?', 'categorie' => 'habitudes'],
            ['texte' => 'Qui de nous deux marche le plus vite ?', 'categorie' => 'habitudes'],
        ];

        foreach ($questions as $question) {
            QuestionQuiDeNous::create(['texte' => $question['texte'], 'categorie' => $question['categorie'], 'created_by' => null]);
        }
    }

    private function questionsDuJour(): void
    {
        $droles = [
            'Si ton/ta partenaire devenait une application, laquelle serait-il/elle et pourquoi ?',
            'Quel est le truc le plus gênant que tu as fait devant la famille de ton/ta partenaire ?',
            'Si vous échangiez vos corps pendant 24 h, quelle serait la première action de chacun ?',
            'Quel message vocal ne devrais-tu jamais écouter en public ?',
            'Quelle est la pire idée de rendez-vous qui te fait rire à l\'avance ?',
            'Quelle vanne de ton/ta partenaire te fait toujours rire, même la 100e fois ?',
            'Si votre couple était un plat, lequel serait-il ?',
            'Quel surnom bizarre donnerais-tu à ton/ta partenaire devant tout le monde ?',
        ];

        $profondes = [
            'Quel est le moment où tu as été le/la plus fier(ère) de ton/ta partenaire ?',
            'Qu\'est-ce que tu aimerais que votre couple soit dans 10 ans ?',
            'Quel sacrifice as-tu fait que ton/ta partenaire ne connaît pas encore ?',
            'Quelle est ta peur la plus intime concernant votre avenir ?',
            'Qu\'est-ce que ton/ta partenaire fait qui te fait sentir aimé·e, sans qu\'il/elle s\'en doute ?',
            'Si vous n\'aviez plus jamais besoin de travailler, à quoi ressemblerait votre vie idéale ?',
            'Quel souvenir du début de votre relation gardes-tu précieusement ?',
            'Qu\'est-ce que tu aimerais entendre de la bouche de ton/ta partenaire ?',
            'Quel rêve veux-tu réaliser AVEC ton/ta partenaire avant vos 40 ans ?',
            'Pour quelle qualité de ton/ta partenaire es-tu le/la plus reconnaissant(e) ?',
        ];

        foreach ($droles as $texte) {
            QuestionDuJour::create(['texte' => $texte, 'categorie' => 'drole', 'created_by' => null]);
        }
        foreach ($profondes as $texte) {
            QuestionDuJour::create(['texte' => $texte, 'categorie' => 'profonde', 'created_by' => null]);
        }
    }
}
