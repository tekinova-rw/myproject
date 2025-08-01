let timeLeft = 20 * 60;
let timerInterval;

function startTimer(onFinish) {
  const timerEl = document.getElementById('timer');
  if (!timerEl) {
    console.error('Timer element not found');
    return;
  }
  if (timerInterval) clearInterval(timerInterval);
  timerInterval = setInterval(() => {
    if (timeLeft <= 0) {
      clearInterval(timerInterval);
      if (onFinish) onFinish();
    } else {
      const minutes = String(Math.floor(timeLeft / 60)).padStart(2, '0');
      const seconds = String(timeLeft % 60).padStart(2, '0');
      timerEl.textContent = `Iminota: ${minutes}:${seconds}`;
      timeLeft--;
    }
  }, 1000);
}

function stopTimer() {
  if (timerInterval) clearInterval(timerInterval);
}

window.timer = { startTimer, stopTimer };