<?php

use App\Http\Controllers\CardsController;
use App\Http\Controllers\CoupleController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EnveloppeController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\MeteoController;
use App\Http\Controllers\MissionSecreteController;
use App\Http\Controllers\MotsCroisesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OuiNonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\QuestionJourController;
use App\Http\Controllers\QuiDeNousDeuxController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\RecompenseController;
use App\Http\Controllers\VeriteActionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ---- Pages d'information publiques (légales, fonctionnelles, techniques) ----
Route::get('/info/{slug}', [InfoController::class, 'show'])->name('info.show');

Route::get('/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('manifest');
Route::get('/service-worker.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/offscreen', [PwaController::class, 'offscreen'])->name('pwa.offscreen');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [CoupleController::class, 'dashboard'])
        ->middleware(['verified', 'couple.linked'])
        ->name('dashboard');

    Route::get('/couple/configuration', [CoupleController::class, 'setup'])->name('couple.setup');
    Route::get('/couple/activite', [CoupleController::class, 'activite'])
        ->middleware(['verified', 'couple.linked'])
        ->name('couple.activite');
    Route::post('/couple/generer', [CoupleController::class, 'generate'])->name('couple.generate');
    Route::post('/couple/lier', [CoupleController::class, 'link'])->name('couple.link');
    Route::post('/couple/quitter', [CoupleController::class, 'leave'])->name('couple.leave');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
});

Route::middleware(['auth', 'couple.linked'])->group(function () {

    // ---- Discussion ----
    Route::get('/discussion', [DiscussionController::class, 'index'])->name('discussion.index');
    Route::get('/discussion/etat', [DiscussionController::class, 'fetch'])->name('discussion.fetch');
    Route::post('/discussion/envoyer', [DiscussionController::class, 'send'])->name('discussion.send');
    Route::post('/discussion/photo', [DiscussionController::class, 'uploadPhoto'])->name('discussion.photo');
    Route::post('/discussion/audio', [DiscussionController::class, 'uploadAudio'])->name('discussion.audio');
    Route::post('/discussion/tape', [DiscussionController::class, 'typing'])->name('discussion.typing');
    Route::post('/discussion/enregistre', [DiscussionController::class, 'recording'])->name('discussion.recording');
    Route::get('/discussion/gifs', [DiscussionController::class, 'gifs'])->name('discussion.gifs');
    Route::get('/discussion/stickers', [DiscussionController::class, 'stickers'])->name('discussion.stickers');
    Route::get('/discussion/favoris', [DiscussionController::class, 'favorites'])->name('discussion.favorites');
    Route::post('/discussion/favoris', [DiscussionController::class, 'toggleFavorite'])->name('discussion.favorites.toggle');
    Route::get('/discussion/non-lus', [DiscussionController::class, 'nonLus'])->name('discussion.non-lus');
    Route::delete('/discussion/message/{id}', [DiscussionController::class, 'delete'])->name('discussion.delete');

    // ---- Vérité ou Action ----
    Route::get('/jeux/verite-action', [VeriteActionController::class, 'index'])->name('vo.index');
    Route::post('/jeux/verite-action', [VeriteActionController::class, 'start'])->name('vo.start');
    Route::get('/jeux/verite-action/{partie}', [VeriteActionController::class, 'play'])->name('vo.jouer');
    Route::get('/jeux/verite-action/{partie}/etat', [VeriteActionController::class, 'state'])->name('vo.state');
    Route::post('/jeux/verite-action/{partie}/choisir', [VeriteActionController::class, 'choisis'])->name('vo.choisir');
    Route::post('/jeux/verite-action/{partie}/repondre', [VeriteActionController::class, 'repond'])->name('vo.repondre');
    Route::post('/jeux/verite-action/{partie}/valider', [VeriteActionController::class, 'valider'])->name('vo.valider');
    Route::post('/jeux/verite-action/{partie}/invalider', [VeriteActionController::class, 'invalider'])->name('vo.invalider');
    Route::post('/jeux/verite-action/{partie}/terminer', [VeriteActionController::class, 'terminer'])->name('vo.terminer');

    // ---- Oui / Non ----
    Route::get('/jeux/oui-non', [OuiNonController::class, 'index'])->name('ouinon.index');
    Route::post('/jeux/oui-non', [OuiNonController::class, 'start'])->name('ouinon.start');
    Route::get('/jeux/oui-non/{partie}', [OuiNonController::class, 'play'])->name('ouinon.jouer');
    Route::get('/jeux/oui-non/{partie}/etat', [OuiNonController::class, 'state'])->name('ouinon.state');
    Route::post('/jeux/oui-non/{partie}/repondre', [OuiNonController::class, 'repond'])->name('ouinon.repondre');
    Route::post('/jeux/oui-non/{partie}/expliquer', [OuiNonController::class, 'expliquer'])->name('ouinon.expliquer');
    Route::post('/jeux/oui-non/missions/{mission}/realiser', [OuiNonController::class, 'realiserMission'])->name('ouinon.realiser-mission');

    // ---- Mission Secrète ----
    Route::get('/jeux/mission-secrete', [MissionSecreteController::class, 'index'])->name('mission.index');
    Route::post('/jeux/mission-secrete/nouvelle', [MissionSecreteController::class, 'nouvelle'])->name('mission.nouvelle');
    Route::post('/jeux/mission-secrete/{mission}/reveler', [MissionSecreteController::class, 'reveler'])->name('mission.reveler');
    Route::post('/jeux/mission-secrete/{mission}/accomplir', [MissionSecreteController::class, 'accomplir'])->name('mission.accomplir');
    Route::post('/jeux/mission-secrete/question-du-soir', [MissionSecreteController::class, 'questionDuSoir'])->name('mission.question');
    Route::post('/jeux/mission-secrete/{mission}/echouer', [MissionSecreteController::class, 'echouer'])->name('mission.echouer');

    // ---- Enveloppes ----
    Route::get('/jeux/enveloppes', [EnveloppeController::class, 'index'])->name('enveloppe.index');
    Route::post('/jeux/enveloppes/nouvelle', [EnveloppeController::class, 'nouvelle'])->name('enveloppe.nouvelle');
    Route::get('/jeux/enveloppes/{couple}/etat', [EnveloppeController::class, 'state'])->name('enveloppe.state');
    Route::post('/jeux/enveloppes/{couple}/enveloppes/{enveloppe}/ouvrir', [EnveloppeController::class, 'ouvrir'])->name('enveloppe.ouvrir');
    Route::post('/jeux/enveloppes/{couple}/enveloppes/{enveloppe}/repondre', [EnveloppeController::class, 'repondre'])->name('enveloppe.repondre');
    Route::post('/jeux/enveloppes/{couple}/recompense', [EnveloppeController::class, 'recompense'])->name('enveloppe.recompense');

    // ---- Quiz « Tu me connais ? » ----
    Route::get('/jeux/tu-me-connais', [QuizController::class, 'index'])->name('quiz.index');
    Route::post('/jeux/tu-me-connais', [QuizController::class, 'start'])->name('quiz.start');
    Route::get('/jeux/tu-me-connais/{session}', [QuizController::class, 'play'])->name('quiz.jouer');
    Route::get('/jeux/tu-me-connais/{session}/etat', [QuizController::class, 'state'])->name('quiz.state');
    Route::post('/jeux/tu-me-connais/{session}/repondre', [QuizController::class, 'repondre'])->name('quiz.repondre');
    Route::post('/jeux/tu-me-connais/{session}/juger', [QuizController::class, 'juger'])->name('quiz.juger');

    // ---- Qui de nous deux ? ----
    Route::get('/jeux/qui-nous-deux/questions', [QuiDeNousDeuxController::class, 'questions'])->name('qdn2.questions');
    Route::post('/jeux/qui-nous-deux/questions', [QuiDeNousDeuxController::class, 'creerQuestion'])->name('qdn2.questions.creer');
    Route::delete('/jeux/qui-nous-deux/questions/{question}', [QuiDeNousDeuxController::class, 'detruireQuestion'])->name('qdn2.questions.detruire');
    Route::get('/jeux/qui-nous-deux', [QuiDeNousDeuxController::class, 'index'])->name('qdn2.index');
    Route::post('/jeux/qui-nous-deux', [QuiDeNousDeuxController::class, 'start'])->name('qdn2.start');
    Route::get('/jeux/qui-nous-deux/{partie}', [QuiDeNousDeuxController::class, 'play'])->name('qdn2.jouer');
    Route::get('/jeux/qui-nous-deux/{partie}/etat', [QuiDeNousDeuxController::class, 'state'])->name('qdn2.state');
    Route::post('/jeux/qui-nous-deux/{partie}/repondre', [QuiDeNousDeuxController::class, 'repondre'])->name('qdn2.repondre');
    Route::post('/jeux/qui-nous-deux/{partie}/resoudre', [QuiDeNousDeuxController::class, 'resoudre'])->name('qdn2.resoudre');

    // ---- La Question du Jour ----
    Route::get('/jeux/question-du-jour', [QuestionJourController::class, 'index'])->name('question.index');
    Route::get('/jeux/question-du-jour/etat', [QuestionJourController::class, 'state'])->name('question.state');
    Route::post('/jeux/question-du-jour/repondre', [QuestionJourController::class, 'repondre'])->name('question.repondre');

    // ---- Météo du Couple ----
    Route::get('/jeux/meteo-du-couple', [MeteoController::class, 'index'])->name('meteo.index');
    Route::get('/jeux/meteo-du-couple/etat', [MeteoController::class, 'state'])->name('meteo.state');
    Route::post('/jeux/meteo-du-couple/checkin', [MeteoController::class, 'checkin'])->name('meteo.checkin');
    Route::post('/jeux/meteo-du-couple/suggestion', [MeteoController::class, 'suggestion'])->name('meteo.suggestion');

    // ---- Mots Croisés du Couple ----
    Route::get('/jeux/mots-croises', [MotsCroisesController::class, 'index'])->name('mots-croises.index');
    Route::get('/jeux/mots-croises/etat', [MotsCroisesController::class, 'state'])->name('mots-croises.state');
    Route::post('/jeux/mots-croises/verifier', [MotsCroisesController::class, 'verifier'])->name('mots-croises.verifier');
    Route::post('/jeux/mots-croises/verifier', [MotsCroisesController::class, 'verifier'])->name('mots-croises.verifier');
    Route::post('/jeux/mots-croises/generer', [MotsCroisesController::class, 'generer'])->name('mots-croises.generer');
    Route::get('/jeux/mots-croises/mots', [MotsCroisesController::class, 'mots'])->name('mots-croises.mots');
    Route::post('/jeux/mots-croises/mots', [MotsCroisesController::class, 'creerMot'])->name('mots-croises.mots.creer');
    Route::delete('/jeux/mots-croises/mots/{id}', [MotsCroisesController::class, 'detruireMot'])->name('mots-croises.mots.detruire');

    // ---- Récompenses ----
    Route::get('/recompenses', [RecompenseController::class, 'index'])->name('recompenses.index');
    Route::post('/recompenses', [RecompenseController::class, 'creer'])->name('recompenses.creer');
    Route::post('/recompenses/{recompense}/marquer', [RecompenseController::class, 'marquer'])->name('recompenses.marquer');

    // ---- Cartes personnalisées ----
    Route::get('/mes-cartes', [CardsController::class, 'index'])->name('cartes.index');
    Route::post('/mes-cartes/verite', [CardsController::class, 'creerVerite'])->name('cartes.verite');
    Route::post('/mes-cartes/action', [CardsController::class, 'creerAction'])->name('cartes.action');
    Route::post('/mes-cartes/defi', [CardsController::class, 'creerDefi'])->name('cartes.defi');
    Route::post('/mes-cartes/question', [CardsController::class, 'creerQuestion'])->name('cartes.question');
    Route::post('/mes-cartes/gage', [CardsController::class, 'creerGage'])->name('cartes.gage');
    Route::delete('/mes-cartes/{type}/{id}', [CardsController::class, 'detruire'])->name('cartes.detruire');

    // ---- Notifications push ----
    Route::post('/notifications/subscribe', [NotificationController::class, 'subscribe'])->name('notifications.subscribe');
    Route::post('/notifications/unsubscribe', [NotificationController::class, 'unsubscribe'])->name('notifications.unsubscribe');
    Route::post('/notifications/test', [NotificationController::class, 'test'])->name('notifications.test');
});

require __DIR__.'/auth.php';
