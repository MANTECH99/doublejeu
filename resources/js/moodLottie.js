/* ============ Émojis animés (Lottie) ============
 * Monte les animations Lottie sur les éléments [data-lottie] d'un conteneur.
 * L'émoji présent dans le conteneur sert de fallback tant que l'animation
 * ne s'est pas chargée (rien ne clignote si l'asset manque).
 * lottie-web n'est chargé (chunk Vite) qu'à la première utilisation.
 */
let lottieModulePromise = null;

function lottieLoader() {
    if (!lottieModulePromise) {
        lottieModulePromise = import('lottie-web')
            .then((m) => m.default)
            .catch(() => null);
    }
    return lottieModulePromise;
}

function mount(scope) {
    (scope || document).querySelectorAll('[data-lottie]').forEach((el) => {
        const src = el.getAttribute('data-lottie');
        if (!src || el.dataset.djLottie === src) return;

        const loaded = src;
        lottieLoader().then((lottie) => {
            if (!lottie) return; // lottie indisponible : on garde l'émoji fallback.
            if (!document.body.contains(el)) return;
            if (el.dataset.djLottie === loaded) return;

            el.dataset.djLottie = loaded;
            // lottie ajoute son <svg> SANS effacer le contenu existant : on retire
            // l'émoji fallback avant le montage, sinon il resterait à côté de l'animation.
            el.textContent = '';
            const anim = lottie.loadAnimation({
                container: el,
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: src,
            });
            el._djLottie = anim;
        });
    });
}

function destroy(scope) {
    (scope || document).querySelectorAll('[data-lottie]').forEach((el) => {
        if (el._djLottie) {
            try {
                el._djLottie.destroy();
            } catch (e) { /* déjà détruite */ }
            el._djLottie = null;
        }
        delete el.dataset.djLottie;
    });
}

window.moodLottie = { mount, destroy };