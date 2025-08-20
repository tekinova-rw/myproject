// js/mode.js
function hasBonusAccess() {
  return localStorage.getItem('accessMode') === 'bonus';
}

function redirectIfNotAllowed() {
  if (!hasBonusAccess()) {
    window.location.href = "pay.html";
  }
}
