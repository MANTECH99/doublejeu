<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\UploadedFile;

abstract class Controller
{
    /**
     * Règle de validation pour les captures MediaRecorder (vocal).
     *
     * Le navigateur envoie un blob audio/webm ou audio/mp4. Son contenu n'est pas
     * toujours identifié par finfo selon le serveur, et un enregistrement tronqué
     * (morceaux non rassemblés) peut être un fragment sans en-tête : la règle
     * "mimes" le rejetait ou, à l'inverse, un contenu non identifié passait et
     * donnait un vocal illisible. On exige donc une vraie signature de conteneur
     * audio/vidéo (magic bytes), fiable quel que soit le verdict de finfo.
     */
    protected function regleCaptureRecorderAudio(): Closure
    {
        $extensions = ['webm', 'weba', 'mp4', 'm4a', 'wav', 'mp3', 'ogg', 'oga', 'opus', 'spx'];

        return function (string $attribute, mixed $value, Closure $fail) use ($extensions): void {
            if (! $value instanceof UploadedFile) {
                $fail('Fichier audio invalide.');

                return;
            }

            if (! in_array(strtolower((string) $value->getClientOriginalExtension()), $extensions, true)) {
                $fail('Fichier audio invalide (un enregistrement audio est attendu).');

                return;
            }

            $mime = strtolower((string) preg_replace('/;.*$/', '', (string) $value->getMimeType()));

            if (str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) {
                return;
            }

            if ($this->contientSignatureMedia($value)) {
                return;
            }

            $fail('Fichier audio invalide (format non reconnu).');
        };
    }

    /**
     * Vrai si les premiers octets du fichier correspondent à un conteneur
     * audio/vidéo courant (WebM/Matroska, MP4/M4A, Ogg/Opus, WAV, MP3, FLAC).
     */
    protected function contientSignatureMedia(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = (string) fread($handle, 16);
        fclose($handle);

        $signatures = [
            "\x1A\x45\xDF\xA3", // WebM / Matroska (EBML)
            'OggS',             // Ogg (Opus, Speex, Vorbis)
            'RIFF',             // WAV
            'ID3',              // MP3 (étiquette)
            "\xFF\xFB",         // MP3 (synchronisation de trame)
            "\xFF\xF3",
            "\xFF\xF2",
            'fLaC',             // FLAC
        ];

        foreach ($signatures as $signature) {
            if (str_starts_with($head, $signature)) {
                return true;
            }
        }

        // MP4 / M4A : l'atome "ftyp" se trouve à l'offset 4.
        return substr($head, 4, 4) === 'ftyp';
    }
}
