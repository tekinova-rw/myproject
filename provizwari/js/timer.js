// timer.js

/**
 * Tangiza timer y'ikizamini.
 *
 * @param {number} secondsLeft - Umubare w'amasegonda asigaye (nko 20 * 60)
 * @param {function} onTick - Callback yoherezwa buri segonda hamwe n’igihe gisigaye
 * @param {function} onTimeout - Callback igihe igihe kirangiye
 */
export function startExamTimer(secondsLeft, onTick, onTimeout) {
  const timerEl = document.getElementById('timer');

  function updateTimerDisplay(time) {
    const minutes = Math.floor(time / 60);
    const seconds = time % 60;
    timerEl.textContent = `Igihe gisigaye: ${minutes}:${seconds.toString().padStart(2, '0')}`;
  }

  updateTimerDisplay(secondsLeft);

  const interval = setInterval(() => {
    secondsLeft--;

    if (secondsLeft < 0) {
      clearInterval(interval);
      if (typeof onTimeout === 'function') {
        onTimeout();
      }
      return;
    }

    updateTimerDisplay(secondsLeft);

    if (typeof onTick === 'function') {
      onTick(secondsLeft);
    }
  }, 1000);
}
