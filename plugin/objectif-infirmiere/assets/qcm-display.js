(() => {
 'use strict';
 // Presentation only: preserve source text, stable question codes and import fingerprints.
 // Strip recognised difficulty metadata at the start, never clinical bracket notation.
 window.OIQCMDisplay = {
  question(value) {
   return String(value ?? '').replace(/^\s*\[\s*(?:facile|moyen(?:ne)?|difficile|tr[èe]s\s+difficile)\s*\]\s*/iu, '');
  }
 };
})();
