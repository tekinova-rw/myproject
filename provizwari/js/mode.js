const MODE_KEY = 'accessMode';

function getAccessMode() {
  return localStorage.getItem(MODE_KEY) || 'free';
}

function setAccessMode(mode) {
  if (mode === 'free' || mode === 'bonus') {
    localStorage.setItem(MODE_KEY, mode);
  } else {
    console.warn('Invalid mode:', mode);
  }
}

function hasBonusAccess() {
  return getAccessMode() === 'bonus';
}

function requireBonusAccess(redirectUrl = 'pay.html') {
  if (!hasBonusAccess()) {
    alert('Utabonye uburenganzira bwa Bonus, urasubizwa ku rupapuro rwa password.');
    window.location.href = redirectUrl;
  }
}

window.mode = { getAccessMode, setAccessMode, hasBonusAccess, requireBonusAccess };