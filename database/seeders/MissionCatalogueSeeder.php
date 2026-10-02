<?php

namespace Database\Seeders;

use App\Models\Mission;
use Illuminate\Database\Seeder;

class MissionCatalogueSeeder extends Seeder
{
    /**
     * Catalogue des 100 missions secrètes.
     *
     * Le tirage ne se fait plus au hasard (MissionSecreteController) mais par
     * « première mission non encore servie » : une mission ne revient donc
     * jamais tant que le joueur n'a pas épuisé les 100.
     *
     * @var list<array{0: string, 1: string}> [texte, difficulte]
     */
    private const MISSIONS = [
        // ---------- 40 faciles ----------
        ['Envoie « Tu me manques » à un moment inattendu aujourd\'hui', 'facile'],
        ['Envoie un selfie en faisant un clin d\'œil', 'facile'],
        ['Raconte à ton/ta partenaire ton souvenir préféré de votre première rencontre', 'facile'],
        ['Appelle ton/ta partenaire pour lui dire « Je t\'aime » sans raison apparente', 'facile'],
        ['Envoie un emoji mystérieux et attends sa réaction', 'facile'],
        ['Fais un compliment très précis sur un détail du physique de ton/ta partenaire', 'facile'],
        ['Demande à ton/ta partenaire quel est son rêve le plus fou pour vous deux', 'facile'],
        ['Envoie un audio de toi en train de rire aux éclats', 'facile'],
        ['Dis à ton/ta partenaire de regarder la lune à la même heure ce soir', 'facile'],
        ['Écris trois mots qui décrivent ton/ta partenaire et envoie-les', 'facile'],
        ['Envoie la photo d\'un truc bleu que tu vois près de toi', 'facile'],
        ['Raconte la dernière chose qui t\'a fait rire aujourd\'hui', 'facile'],
        ['Envoie un message vocal de « bonne nuit » avant que ton/ta partenaire ne dorme', 'facile'],
        ['Demande à ton/ta partenaire sa couleur préférée et propose-lui de la porter', 'facile'],
        ['Envoie un emoji cœur puis un emoji sourire, sans rien écrire', 'facile'],
        ['Décris à ton/ta partenaire ce que tu vois par ta fenêtre là, maintenant', 'facile'],
        ['Raconte un truc que tu n\'as jamais osé dire à ton/ta partenaire', 'facile'],
        ['Envoie la photo de ce que tu manges et demande à ton/ta partenaire son avis', 'facile'],
        ['Propose à ton/ta partenaire un nom pour votre futur chat', 'facile'],
        ['Envoie un message avec seulement une couleur, et attends', 'facile'],
        ['Dis à ton/ta partenaire un truc que tu faisais à son âge', 'facile'],
        ['Demande à ton/ta partenaire ce qu\'il/elle aimerait faire ce week-end, sans proposer', 'facile'],
        ['Envoie une photo de deux objets de la même couleur', 'facile'],
        ['Raconte à ton/ta partenaire ta journée en trois mots', 'facile'],
        ['Fais deviner à ton/ta partenaire ton plat préféré en deux indices', 'facile'],
        ['Envoie un message avec l\'heure exacte où tu penses à ton/ta partenaire', 'facile'],
        ['Demande à ton/ta partenaire de choisir entre deux films pour ce soir', 'facile'],
        ['Écris un message qui commence par « tu devrais… »', 'facile'],
        ['Envoie la photo d\'un endroit où vous avez été ensemble', 'facile'],
        ['Raconte à ton/ta partenaire une chanson qui te rappelle quelqu\'un', 'facile'],
        ['Propose à ton/ta partenaire un jeu à faire ce soir', 'facile'],
        ['Envoie la photo d\'un objet bizarre et demande-lui ce que ça lui rappelle', 'facile'],
        ['Raconte en un message le meilleur compliment que tu as reçu cette semaine', 'facile'],
        ['Envoie la photo de ton écran d\'accueil et explique ce que tu y caches', 'facile'],
        ['Demande à ton/ta partenaire de te raconter sa journée avec des emojis seulement', 'facile'],
        ['Raconte à ton/ta partenaire un bruit que tu aimes et qu\'elle/il ne connaît pas', 'facile'],
        ['Propose à ton/ta partenaire de s\'appeler par un surnom aujourd\'hui', 'facile'],
        ['Envoie une photo de deux choses que tu as finies aujourd\'hui', 'facile'],
        ['Raconte à ton/ta partenaire la chose la plus difficile de ta semaine', 'facile'],
        ['Écris « bonne chance pour aujourd\'hui » avec une raison précise', 'facile'],

        // ---------- 35 moyennes ----------
        ['Écris un poème court sur ton/ta partenaire et envoie-le', 'moyen'],
        ['Envoie un vocal de 20 secondes en chuchotant', 'moyen'],
        ['Envoie une photo de l\'endroit où tu aimerais qu\'on se retrouve', 'moyen'],
        ['Envoie une photo de ce que tu portes en ce moment', 'moyen'],
        ['Envoie un message en langue étrangère et laisse ton/ta partenaire deviner', 'moyen'],
        ['Mets une photo de vous deux en fond d\'écran sans le mentionner', 'moyen'],
        ['Pose à ton/ta partenaire une question dont tu veux vraiment la réponse', 'moyen'],
        ['Raconte à ton/ta partenaire une idée de voyage que tu n\'as jamais proposée', 'moyen'],
        ['Fais une liste de trois choses que tu veux faire avec ton/ta partenaire ce mois-ci', 'moyen'],
        ['Envoie une photo de toi sans filtre et dis pourquoi', 'moyen'],
        ['Raconte le pire présent que tu as déjà offert et pourquoi', 'moyen'],
        ['Imite la voix de ton/ta partenaire en vocal', 'moyen'],
        ['Propose à ton/ta partenaire de changer de fond d\'écran l\'un pour l\'autre', 'moyen'],
        ['Écris une question que tu n\'oses pas poser à voix haute', 'moyen'],
        ['Fais deviner ta chanson favorite à ton/ta partenaire en fredonnant dans un vocal', 'moyen'],
        ['Envoie la photo de la couverture du livre que tu lis en ce moment', 'moyen'],
        ['Raconte une chose que tu faisais seul(e) avant de rencontrer ton/ta partenaire', 'moyen'],
        ['Propose un défi pour demain : qui envoie la meilleure photo de petit-déjeuner', 'moyen'],
        ['Envoie une liste de cinq choses que tu veux faire avant la fin de l\'année', 'moyen'],
        ['Raconte un moment gênant de ta journée et demande si ton/ta partenaire en a un', 'moyen'],
        ['Écris à ton/ta partenaire ce que tu fais exactement maintenant, en temps réel', 'moyen'],
        ['Demande à ton/ta partenaire de choisir une playlist pour vous deux', 'moyen'],
        ['Envoie une photo de ta journée en quatre images, sans commentaire', 'moyen'],
        ['Raconte pourquoi cette chanson te fait penser à ton/ta partenaire', 'moyen'],
        ['Propose à ton/ta partenaire de se raconter trois vérités et deux mensonges', 'moyen'],
        ['Écris ce que tu voudrais qu\'on fasse ensemble dans un an', 'moyen'],
        ['Envoie un audio où tu dis à voix basse ce que tu n\'oserais pas en face', 'moyen'],
        ['Propose un nom pour votre groupe de voyage imaginaire', 'moyen'],
        ['Raconte une histoire vraie qui a changé ta façon de voir les choses', 'moyen'],
        ['Demande à ton/ta partenaire quel jour elle/il a été le plus heureux cette semaine', 'moyen'],
        ['Envoie une photo de ce que tu garderais en cas de déménagement', 'moyen'],
        ['Raconte ce que tu veux faire de ta journée si tu étais libre demain', 'moyen'],
        ['Propose à ton/ta partenaire de regarder la même scène et de comparer vos reactions', 'moyen'],
        ['Écris trois choses que tu n\'as jamais dites et envoie-les en trois messages', 'moyen'],
        ['Raconte à ton/ta partenaire le meilleur compliment reçu cette semaine', 'moyen'],

        // ---------- 25 difficiles ----------
        ['Envoie un message coquin à 14h exactement', 'difficile'],
        ['Pose une question très intime à ton/ta partenaire', 'difficile'],
        ['Raconte ton meilleur souvenir de nuit avec elle/lui', 'difficile'],
        ['Écris à ton/ta partenaire ce que tu ferais si elle/il disparaissait une semaine', 'difficile'],
        ['Confie à ton/ta partenaire une peur que tu n\'as jamais exprimée', 'difficile'],
        ['Raconte une erreur que tu as commise et qui te hante encore', 'difficile'],
        ['Demande à ton/ta partenaire ce qu\'elle/il ferait si elle/il ne t\'aimait plus', 'difficile'],
        ['Envoie une photo de toi en train de pleurer et dis pourquoi', 'difficile'],
        ['Écris ce que tu veux entendre le jour où tout va mal, et envoie-le', 'difficile'],
        ['Propose à ton/ta partenaire de se dire la vérité la plus difficile', 'difficile'],
        ['Raconte le moment où tu as su que c\'était elle/lui', 'difficile'],
        ['Rappelle à ton/ta partenaire une promesse que tu as failli oublier', 'difficile'],
        ['Envoie une photo de l\'endroit où tu as le plus peur d\'aller seul(e)', 'difficile'],
        ['Écris la chose la plus honnête que tu n\'as jamais dite à personne', 'difficile'],
        ['Envoie un message où tu demandes pardon, même si tu ne sais pas pour quoi', 'difficile'],
        ['Raconte ton complexe le plus intime', 'difficile'],
        ['Écris à ton/ta partenaire ce que tu veux qu\'on fasse dans dix ans', 'difficile'],
        ['Raconte une fois où tu as été jaloux(e) de ton/ta partenaire', 'difficile'],
        ['Envoie une photo de toi sans visage et décris ton expression actuelle', 'difficile'],
        ['Raconte ce que tu veux qu\'on oublie de vous deux', 'difficile'],
        ['Demande à ton/ta partenaire de te dire ce qu\'elle/il pense de toi sans filtre', 'difficile'],
        ['Raconte la chose la plus difficile que tu as dite à ton/ta partenaire', 'difficile'],
        ['Propose à ton/ta partenaire de faire un voyage imprévu ce week-end', 'difficile'],
        ['Raconte à ton/ta partenaire ce que tu n\'as jamais dit à personne d\'autre', 'difficile'],
        ['Raconte le souvenir le plus embarrassing de ta vie à ton/ta partenaire', 'difficile'],
    ];

    public function run(): void
    {
        // Remplissage par clé naturelle (le texte) plutôt que par
        // suppression globale : le seeder est inoffensif à rejouer, y compris
        // depuis DatabaseSeeder à chaque déploiement. Effacer mission_tracks
        // ferait perdre aux joueurs les missions déjà parcourues et remettrait
        // le catalogue à zéro, ce qui n'est pas rattrapable.
        foreach (self::MISSIONS as [$texte, $difficulte]) {
            Mission::updateOrCreate(
                ['texte' => $texte],
                ['difficulte' => $difficulte],
            );
        }
    }
}
