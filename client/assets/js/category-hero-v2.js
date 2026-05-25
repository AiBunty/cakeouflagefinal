(function () {
  const hero = document.querySelector('.category-hero-v2');
  if (!hero) {
    return;
  }

  const media = hero.querySelector('.category-hero-v2__media');
  if (!media) {
    return;
  }

  media.addEventListener('error', function () {
    // Fallback keeps layout stable if the featured hero media fails to load.
    media.src = '/client/assets/images/cakecenter.jpg';
  }, { once: true });
})();
